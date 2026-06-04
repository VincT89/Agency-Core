<?php

namespace App\Domain\Social\Publishing;

use App\Models\MarketingCampaignPost;
use App\Models\ClientSocialAccount;
use App\Domain\Social\TikTok\TikTokContentPostingService;
use App\Domain\Social\TikTok\TikTokVideoValidationService;
use App\Domain\Social\TikTok\TikTokPhotoValidationService;
use App\Domain\Social\Services\SocialMediaPublicUrlService;
use App\Domain\Social\Services\TikTokTokenRefreshService;
use App\Domain\Social\TikTok\Strategies\PullFromUrlStrategy;
use App\Enums\Social\SocialPlatform;
use App\Enums\Social\SocialConnectionStrategy;
use App\Enums\Social\SocialApiStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TikTokPublisher implements SocialPublisherInterface
{
    private TikTokContentPostingService $contentService;
    private TikTokVideoValidationService $videoValidationService;
    private TikTokPhotoValidationService $photoValidationService;
    private SocialMediaPublicUrlService $publicUrlService;
    private TikTokTokenRefreshService $tokenRefreshService;

    public function __construct(
        TikTokContentPostingService $contentService,
        TikTokVideoValidationService $videoValidationService,
        TikTokPhotoValidationService $photoValidationService,
        SocialMediaPublicUrlService $publicUrlService,
        TikTokTokenRefreshService $tokenRefreshService
    ) {
        $this->contentService = $contentService;
        $this->videoValidationService = $videoValidationService;
        $this->photoValidationService = $photoValidationService;
        $this->publicUrlService = $publicUrlService;
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

    public function publish(MarketingCampaignPost $post, ClientSocialAccount $account, ?string $correlationId = null): PublishResult
    {
        // 1. Blocco se disabilitato
        if (config('services.tiktok.delivery_mode', 'disabled') === 'disabled') {
            return PublishResult::failure(
                "TikTok publishing non configurato o non ancora abilitato."
            );
        }

        // 2. Controllo base
        if (!$account->isApiConnected()) {
            return PublishResult::failure("Account TikTok non valido, disconnesso o token scaduto.");
        }

        // 3. Identificazione e validazione Media
        $mediaCollection = $post->orderedMediaItems;
        
        $hasVideo = $mediaCollection->contains(fn($m) => $m->isVideo() || $m->media_type === 'video');
        $hasPhoto = $mediaCollection->contains(fn($m) => !$m->isVideo() && $m->media_type !== 'video');

        if ($hasVideo && $hasPhoto) {
            return PublishResult::failure("TikTok non supporta media misti. Carica solo un video o un set di foto.");
        }

        if (!$hasVideo && !$hasPhoto) {
            return PublishResult::failure("Nessun media fornito per la pubblicazione TikTok.");
        }

        $publishMethod = null;
        $postData = ['title' => $post->resolved_title ?: $post->resolved_caption];
        $diagnosticPayload = null;

        if ($hasVideo) {
            if (!$account->canPublishTikTokVideo()) {
                return PublishResult::failure("L'account non ha i permessi (capability) per pubblicare video su TikTok.");
            }
            
            $media = $mediaCollection->first();
            $validation = $this->videoValidationService->validate($media);
            if (!$validation['isValid']) {
                return PublishResult::failure($validation['error']);
            }
            $validated = $this->publicUrlService->getValidatedPublicUrl($media);
            $postData['video_url'] = $validated['url'];
            $diagnosticPayload = $validated['diagnostic'];
            $publishMethod = 'initializeVideoPost';
        } else {
            if (!$account->canPublishTikTokPhoto()) {
                return PublishResult::failure("La pubblicazione foto su TikTok non è supportata o disabilitata dalle configurazioni di questo account.");
            }

            $maxCapability = $account->publishing_capabilities['tiktok']['max_photo_count'] ?? 10;
            $validation = $this->photoValidationService->validate($mediaCollection, $maxCapability);
            if (!$validation['isValid']) {
                return PublishResult::failure($validation['error']);
            }
            
            $validatedArray = $this->publicUrlService->getValidatedPublicUrls($mediaCollection);
            $postData['photo_urls'] = array_column($validatedArray, 'url');
            $diagnosticPayload = array_column($validatedArray, 'diagnostic');
            $publishMethod = 'initializePhotoPost';
        }

        // 4. Lock di idempotenza per evitare post doppi (sprint 2 hardening)
        $lockKey = "tiktok_publish_lock_{$post->id}_{$account->id}";
        $lock = Cache::lock($lockKey, 300); // 5 minuti TTL anti-zombie/overlapping
        
        if (!$lock->get()) {
            Log::warning("TikTok publish abortito: lock attivo (già in corso)", [
                'post_id' => $post->id,
                'account_id' => $account->id
            ]);
            return PublishResult::failure("Pubblicazione già in corso, attendere.");
        }

        // 5. Esecuzione
        try {
            // Assicuriamoci che il token sia aggiornato (bloccante se fallisce)
            if (!$this->tokenRefreshService->ensureValidToken($account)) {
                return PublishResult::failure('Token TikTok scaduto o non rinnovabile. Ricollegare account.');
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
                'post_id' => $post->id,
                'error' => $e->getMessage()
            ]);
            
            return PublishResult::failure($e->getMessage());
        } finally {
            // Rilascio il lock in ogni caso (successo o errore) per pulizia (TTL lungo anti-overlapping garantito da 300s max)
            isset($lock) && $lock->release();
        }
    }
}

