<?php

namespace App\Domain\Social\Publishing;

use App\Models\MarketingCampaignPost;
use App\Models\ClientSocialAccount;
use App\Domain\Social\Services\SocialMediaPublicUrlService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MetaPublisher implements SocialPublisherInterface
{
    protected SocialMediaPublicUrlService $mediaUrlService;

    public function __construct(SocialMediaPublicUrlService $mediaUrlService)
    {
        $this->mediaUrlService = $mediaUrlService;
    }

    public function verifyAccountCapabilities(ClientSocialAccount $account): bool
    {
        if ($account->connection_strategy?->value === 'agency_oauth') {
            if (!$account->agencyAsset || !$account->agencyAsset->connection) return false;
            if (!$account->agencyAsset->is_active) return false;
            if ($account->agencyAsset->connection->requires_reauth) return false;
            return !empty(app(\App\Domain\Social\Actions\ResolveAssetAccessTokenAction::class)->execute($account->agencyAsset));
        }

        return $account->isApiConnected() 
            && !empty($account->access_token) 
            && !empty($account->provider_account_id);
    }

    public function publish(MarketingCampaignPost $post, ClientSocialAccount $account, ?string $correlationId = null): PublishResult
    {
        if ($account->connection_strategy?->value === 'agency_oauth' && $account->agencyAsset && $account->agencyAsset->connection) {
            if ($account->agencyAsset->connection->requires_reauth) {
                return PublishResult::failure('La connessione dell\'agenzia Meta richiede un nuovo accesso (Token Scaduto/Revocato). Ricollega l\'account dal pannello Admin.');
            }
        }

        if (!$this->verifyAccountCapabilities($account)) {
            return PublishResult::failure('Account non configurato per la pubblicazione API (o token mancante).');
        }

        if (config('social.publishing.dry_run', false)) {
            return PublishResult::success(
                'dryrun_meta_' . $post->id . '_' . now()->timestamp,
                null,
                [
                    'dry_run' => true,
                    'platform' => $account->platform->value,
                    'post_id' => $post->id,
                    'account_id' => $account->id,
                    'should_not_count_as_real_publication' => true,
                ]
            );
        }

        try {
            $isInstagram = $account->platform->value === 'instagram';
            $message = $post->resolved_caption;
            if ($post->currentVersion?->hashtags) {
                $message = trim($message . "\n\n" . collect($post->currentVersion->hashtags)
                    ->map(fn ($h) => str_starts_with($h, '#') ? $h : "#{$h}")
                    ->implode(' '));
            }
            
            // Risolvi token e ID tramite metodo isolato
            [$accessToken, $providerAccountId] = $this->resolveTokenAndProviderId($account);

            $payload = [
                'access_token' => $accessToken,
                'message' => $message,
            ];

            // Media attachment handling
            $mediaItems = $post->orderedMediaItems;
            $mediaUrls = [];
            $mediaType = null;
            $diagnosticPayloads = [];

            $mediaDescriptors = [];

            if ($mediaItems->count() > 0) {
                // Prendi il tipo dal primo media per Facebook
                $primaryMedia = $mediaItems->first();
                if (isset($primaryMedia->media_type) && in_array(strtolower($primaryMedia->media_type), ['video', 'image'])) {
                    $mediaType = strtolower($primaryMedia->media_type) === 'video' ? 'VIDEO' : 'IMAGE';
                } else {
                    $mediaType = in_array(strtolower(pathinfo($primaryMedia->path ?? '', PATHINFO_EXTENSION)), ['mp4', 'mov', 'webm']) ? 'VIDEO' : 'IMAGE';
                }

                // Genera public URLs per tutti i media
                $validatedItems = $this->mediaUrlService->getValidatedPublicUrls($mediaItems, $correlationId);
                foreach ($mediaItems as $idx => $media) {
                    $v = $validatedItems[$idx] ?? null;
                    if (!$v) continue;
                    $mediaUrls[] = $v['url'];
                    $diagnosticPayloads[] = $v['diagnostic'];
                    
                    $ext = strtolower(pathinfo($media->path ?? '', PATHINFO_EXTENSION));
                    $isVideo = strtolower($media->media_type ?? '') === 'video' || in_array($ext, ['mp4', 'mov', 'webm']);
                    $mediaDescriptors[] = [
                        'id' => $media->id,
                        'url' => $v['url'],
                        'type' => $isVideo ? 'video' : 'image'
                    ];
                }
            }

            $contentTypeStr = $post->content_type instanceof \App\Enums\Social\MarketingCampaignPostType 
                ? $post->content_type->value 
                : $post->content_type;

            if ($contentTypeStr === 'reel' && $mediaType !== 'VIDEO') {
                return PublishResult::failure('Dominio Meta: Un Reel richiede obbligatoriamente un file video.');
            }

            if ($isInstagram) {
                $result = $this->publishToInstagram($account, $payload, $mediaDescriptors, $contentTypeStr, $correlationId, $providerAccountId);
            } else {
                $result = $this->publishToFacebook($account, $payload, $mediaUrls[0] ?? null, $mediaType, $correlationId, $providerAccountId);
            }

            if (!empty($diagnosticPayloads)) {
                $snapshot = $result->responseSnapshot ?? [];
                $snapshot['media_diagnostics'] = $diagnosticPayloads;
                $result = new PublishResult(
                    $result->success,
                    $result->externalPostId,
                    $result->externalContainerId,
                    $result->externalPermalink,
                    $result->errorMessage,
                    $snapshot,
                    $result->isProcessing(),
                    $result->externalTaskId,
                    $result->providerStatePayload
                );
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Meta Publisher Exception', [
                'error' => $e->getMessage(),
                'post_id' => $post->id,
                'correlation_id' => $correlationId
            ]);
            return PublishResult::failure('Eccezione durante la pubblicazione: ' . $e->getMessage());
        }
    }

    protected function publishToFacebook(ClientSocialAccount $account, array $payload, ?string $mediaUrl, ?string $mediaType, ?string $correlationId, string $providerAccountId): PublishResult
    {
        $graphVersion = config('services.meta.graph_version', 'v19.0');
        $baseEndpoint = "https://graph.facebook.com/{$graphVersion}/{$providerAccountId}";
        
        if ($mediaUrl) {
            if ($mediaType === 'VIDEO') {
                $endpoint = "{$baseEndpoint}/videos";
                $payload['file_url'] = $mediaUrl;
                $payload['description'] = $payload['message'];
                unset($payload['message']);
            } else {
                $endpoint = "{$baseEndpoint}/photos";
                $payload['url'] = $mediaUrl;
            }
        } else {
            $endpoint = "{$baseEndpoint}/feed";
        }

        $client = Http::withHeaders([
            'X-Correlation-Id' => $correlationId ?? 'none'
        ]);

        $response = $client->post($endpoint, $payload);

        if (!$response->successful()) {
            return PublishResult::failure('Errore API Facebook: ' . $response->body(), $response->json());
        }

        $data = $response->json();
        return PublishResult::success($data['id'] ?? null, null, $data);
    }

    protected function publishToInstagram(ClientSocialAccount $account, array $payload, array $mediaDescriptors, ?string $contentTypeStr, ?string $correlationId, string $providerAccountId): PublishResult
    {
        if (empty($mediaDescriptors)) {
            return PublishResult::failure('Instagram richiede un file multimediale (Immagine o Video).');
        }

        // Se l'account usa la vecchia config e manca l'ID, proviamo il fallback
        $igAccountId = $providerAccountId;
        if (empty($igAccountId)) {
             $igAccountId = $account->instagram_business_account_id;
        }

        $graphVersion = config('services.meta.graph_version', 'v19.0');
        $baseEndpoint = "https://graph.facebook.com/{$graphVersion}/{$igAccountId}";

        $client = Http::withHeaders([
            'X-Correlation-Id' => $correlationId ?? 'none'
        ]);

        if (count($mediaDescriptors) > 1) {
            // STEP 1: Creazione Item Container(s)
            $itemContainerIds = [];
            $childrenPayload = [];
            foreach ($mediaDescriptors as $index => $media) {
                $itemPayload = [
                    'access_token' => $payload['access_token'],
                    'is_carousel_item' => 'true',
                ];

                if ($media['type'] === 'video') {
                    $itemPayload['media_type'] = 'VIDEO';
                    $itemPayload['video_url'] = $media['url'];
                } else {
                    $itemPayload['image_url'] = $media['url'];
                }

                $itemResponse = $client->post("{$baseEndpoint}/media", $itemPayload);

                if (!$itemResponse->successful()) {
                    return PublishResult::failure("Errore IG Carousel Item Container (Indice {$index}): " . $itemResponse->body(), $itemResponse->json());
                }

                $childId = $itemResponse->json('id');
                $itemContainerIds[] = $childId;
                $childrenPayload[] = ['id' => $childId, 'type' => $media['type']];
            }

            $providerStatePayload = [
                'phase' => 'carousel_children_processing',
                'children' => $childrenPayload,
                'caption' => $payload['message'] ?? ''
            ];

            return PublishResult::processing(null, ['message' => 'Carousel children processing'], null, $providerStatePayload);

        } else {
            // STEP 1: Creazione Single Container
            $media = $mediaDescriptors[0];
            $containerPayload = [
                'access_token' => $payload['access_token'],
                'caption' => $payload['message'] ?? '',
            ];

            if ($media['type'] === 'video') {
                $containerPayload['media_type'] = $contentTypeStr === 'reel' ? 'REELS' : 'VIDEO';
                $containerPayload['video_url'] = $media['url'];
            } else {
                $containerPayload['image_url'] = $media['url'];
            }

            $containerResponse = $client->post("{$baseEndpoint}/media", $containerPayload);

            if (!$containerResponse->successful()) {
                return PublishResult::failure('Errore IG Single Container: ' . $containerResponse->body(), $containerResponse->json());
            }

            $containerData = $containerResponse->json();
            $containerId = $containerData['id'];

            $providerStatePayload = [
                'phase' => 'single_container_processing'
            ];

            return PublishResult::processing($containerId, $containerData, null, $providerStatePayload);
        }
    }

    /**
     * Isola la logica di risoluzione del token e dell'ID Provider.
     * Decoupling richiesto per pulizia strutturale.
     */
    private function resolveTokenAndProviderId(ClientSocialAccount $account): array
    {
        if ($account->connection_strategy?->value === 'agency_oauth' && $account->agencyAsset) {
            $accessToken = app(\App\Domain\Social\Actions\ResolveAssetAccessTokenAction::class)->execute($account->agencyAsset);
            $providerAccountId = $account->agencyAsset->provider_asset_id;
            return [$accessToken, $providerAccountId];
        }

        return [$account->access_token, $account->provider_account_id];
    }
}
