<?php

namespace App\Domain\Social\Publishing;

use App\Models\MarketingCampaignPost;
use App\Models\ClientSocialAccount;
use App\Domain\Social\TikTok\TikTokContentPostingService;
use App\Domain\Social\TikTok\TikTokVideoValidationService;
use App\Domain\Social\TikTok\TikTokPhotoValidationService;
use App\Domain\Social\Services\PublicationMediaDeliveryService;
use App\Domain\Social\Services\TikTokTokenRefreshService;
use App\Domain\Social\TikTok\Strategies\PullFromUrlStrategy;
use App\Enums\Social\SocialPlatform;
use App\Enums\Social\SocialConnectionStrategy;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\PublicationFailureClassification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TikTokPublisher implements SocialPublisherInterface
{
    private TikTokContentPostingService $contentService;
    private TikTokVideoValidationService $videoValidationService;
    private TikTokPhotoValidationService $photoValidationService;
    private PublicationMediaDeliveryService $mediaDeliveryService;
    private TikTokTokenRefreshService $tokenRefreshService;

    public function __construct(
        TikTokContentPostingService $contentService,
        TikTokVideoValidationService $videoValidationService,
        TikTokPhotoValidationService $photoValidationService,
        PublicationMediaDeliveryService $mediaDeliveryService,
        TikTokTokenRefreshService $tokenRefreshService
    ) {
        $this->contentService = $contentService;
        $this->videoValidationService = $videoValidationService;
        $this->photoValidationService = $photoValidationService;
        $this->mediaDeliveryService = $mediaDeliveryService;
        $this->tokenRefreshService = $tokenRefreshService;
    }

    public function verifyAccountCapabilities(ClientSocialAccount $account): bool
    {
        if ($account->platform !== SocialPlatform::Tiktok) {
            return false;
        }

        if (config('services.tiktok.delivery_mode', 'disabled') === 'disabled') {
            Log::info("TikTokPublisher verifyAccountCapabilities: publishing disabilitato da config.");
            return false;
        }

        if (!$account->isApiConnected() || $account->connection_strategy !== SocialConnectionStrategy::PlatformOauth) {
            return false;
        }

        return $account->canPublishTikTokVideo() || $account->canPublishTikTokPhoto();
    }

    public function publish(\App\Models\MarketingCampaignPostPublication $publication, ClientSocialAccount $account, ?string $correlationId = null): PublishResult
    {
        // 1. Blocco se disabilitato
        if (config('services.tiktok.delivery_mode', 'disabled') === 'disabled') {
            return PublishResult::failure(
                "TikTok publishing non configurato o non ancora abilitato.",
                PublicationFailureClassification::Permanent
            );
        }

        if (config('social.publishing.dry_run', false) || config('services.tiktok.mock_publishing', false)) {
            return PublishResult::processingTask(
                'dryrun_tiktok_' . $publication->id . '_' . now()->timestamp,
                [
                    'dry_run' => true,
                    'platform' => 'tiktok',
                    'delivery_mode' => config('services.tiktok.delivery_mode'),
                    'publication_id' => $publication->id,
                    'account_id' => $account->id,
                ],
                [
                    'phase' => 'dry_run',
                    'delivery_mode' => config('services.tiktok.delivery_mode'),
                ]
            );
        }

        // 2. Controllo base
        if (!$account->isApiConnected()) {
            return PublishResult::failure("Account TikTok non valido, disconnesso o token scaduto.", PublicationFailureClassification::ManualReview);
        }

        $snapshot = $publication->payload_snapshot ?? [];
        $mediaItemsData = $snapshot['media'] ?? [];
        
        $hasVideo = false;
        $hasPhoto = false;
        foreach ($mediaItemsData as $m) {
            $ext = strtolower(pathinfo($m['path'] ?? '', PATHINFO_EXTENSION));
            $isVid = strtolower($m['media_type'] ?? '') === 'video' || in_array($ext, ['mp4', 'mov', 'webm']);
            if ($isVid) $hasVideo = true;
            else $hasPhoto = true;
        }

        if ($hasVideo && $hasPhoto) {
            return PublishResult::failure("TikTok non supporta media misti. Carica solo un video o un set di foto.", PublicationFailureClassification::Permanent);
        }

        if (!$hasVideo && !$hasPhoto) {
            return PublishResult::failure("Nessun media fornito per la pubblicazione TikTok.", PublicationFailureClassification::Permanent);
        }

        $publishMethod = null;
        $title = $snapshot['title'] ?? ($snapshot['caption'] ?? '');
        $postData = ['title' => $title];
        $diagnosticPayload = null;

        if ($hasVideo) {
            if (!$account->canPublishTikTokVideo()) {
                return PublishResult::failure("L'account non ha i permessi (capability) per pubblicare video su TikTok.", PublicationFailureClassification::ManualReview);
            }
            
            $media = $mediaItemsData[0];
            $deliveryResults = $this->mediaDeliveryService->deliver($publication);
            $result = $deliveryResults[0];
            
            if (!$result->passed) {
                return PublishResult::failure(implode(', ', $result->errors), PublicationFailureClassification::Temporary);
            }
            
            $postData['video_url'] = $result->url;
            $diagnosticPayload = $result->diagnosticPayload;
            $publishMethod = 'initializeVideoPost';
        } else {
            if (!$account->canPublishTikTokPhoto()) {
                return PublishResult::failure("La pubblicazione foto su TikTok non è supportata o disabilitata dalle configurazioni di questo account.", PublicationFailureClassification::ManualReview);
            }

            $maxCapability = $account->publishing_capabilities['tiktok']['max_photo_count'] ?? 10;
            if (count($mediaItemsData) > $maxCapability) {
                return PublishResult::failure("Troppe foto per TikTok. Limite: {$maxCapability}.", PublicationFailureClassification::Permanent);
            }
            
            $urls = [];
            $diagnostics = [];
            $deliveryResults = $this->mediaDeliveryService->deliver($publication);
            
            // Per TikTok photo, ci aspettiamo che il result sia ok per tutte
            foreach ($deliveryResults as $result) {
                if (!$result->passed) {
                    return PublishResult::failure(implode(', ', $result->errors), PublicationFailureClassification::Temporary);
                }
                $urls[] = $result->url;
                $diagnostics[] = $result->diagnosticPayload;
            }
            if (empty($urls)) return PublishResult::failure("Impossibile generare URL per le foto TikTok.", PublicationFailureClassification::Temporary);
            
            $postData['photo_urls'] = $urls;
            $diagnosticPayload = $diagnostics;
            $publishMethod = 'initializePhotoPost';
        }

        // 4. Lock di idempotenza per evitare post doppi (sprint 2 hardening)
        $lockKey = "tiktok_publish_lock_{$publication->id}_{$account->id}";
        $lock = Cache::lock($lockKey, 300); // 5 minuti TTL anti-zombie/overlapping
        
        if (!$lock->get()) {
            Log::warning("TikTok publish abortito: lock attivo (già in corso)", [
                'publication_id' => $publication->id,
                'account_id' => $account->id
            ]);
            return PublishResult::failure("Pubblicazione già in corso, attendere.", PublicationFailureClassification::Temporary);
        }

        // 5. Esecuzione
        try {
            // Assicuriamoci che il token sia aggiornato (bloccante se fallisce)
            if (!$this->tokenRefreshService->ensureValidToken($account)) {
                return PublishResult::failure('Token TikTok scaduto o non rinnovabile. Ricollegare account.', PublicationFailureClassification::ManualReview);
            }
            // Refresh in mem per avere il nuovo access token
            $account->refresh();

            // Scegliamo la strategia di upload basandoci sulla config
            $uploadMode = config('services.tiktok.upload_mode', 'PullFromUrl');
            if ($uploadMode === 'PullFromUrl') {
                $strategy = new PullFromUrlStrategy();
            } else {
                throw new \Exception("Upload mode '{$uploadMode}' non ancora supportata per TikTok.");
            }

            $response = $this->contentService->{$publishMethod}($account->access_token, $postData, $strategy);
            
            // Il publish TikTok API è asincrono. Ritorniamo success con il publish_id 
            // ma con metadato async_processing così sappiamo che va pollato.
            return PublishResult::processingTask(
                $response['publish_id'], 
                [
                    'publish_task_id' => $response['publish_id'],
                    'provider_raw_response' => $response,
                    'media_diagnostic' => $diagnosticPayload
                ]
            );

        } catch (\Exception $e) {
            Log::error("TikTok Publisher Error", [
                'publication_id' => $publication->id,
                'error' => $e->getMessage()
            ]);
            
            return PublishResult::failure($e->getMessage(), PublicationFailureClassification::Temporary);
        } finally {
            // Rilascio il lock in ogni caso (successo o errore) per pulizia (TTL lungo anti-overlapping garantito da 300s max)
            isset($lock) && $lock->release();
        }
    }
}
