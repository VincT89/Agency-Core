<?php

namespace App\Domain\Social\Publishing;

use App\Models\MarketingCampaignPost;
use App\Models\ClientSocialAccount;
use App\Services\SocialMediaPublicUrlService;
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

    public function verifyConfiguration(ClientSocialAccount $account): bool
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

        if (!$this->verifyConfiguration($account)) {
            return PublishResult::failure('Account non configurato per la pubblicazione API (o token mancante).');
        }

        try {
            $isInstagram = $account->platform->value === 'instagram';
            $message = $post->caption ?? '';
            
            // Risolvi token e ID tramite metodo isolato
            [$accessToken, $providerAccountId] = $this->resolveTokenAndProviderId($account);

            $payload = [
                'access_token' => $accessToken,
                'message' => $message,
            ];

            // Media attachment handling
            $mediaItems = $post->orderedMediaItems;
            $primaryMedia = $mediaItems->first();
            $mediaUrl = null;
            $mediaType = null;

            if ($primaryMedia) {
                // Determine file content and extension
                $fileContent = null;
                $extension = null;

                if ($primaryMedia->source === 'nextcloud') {
                    /** @var \App\Services\Integrations\Nextcloud\NextcloudService $nextcloud */
                    $nextcloud = app(\App\Services\Integrations\Nextcloud\NextcloudService::class);
                    $fileContent = $nextcloud->downloadFile($primaryMedia->nextcloud_path);
                    $extension = strtolower(pathinfo($primaryMedia->nextcloud_path, PATHINFO_EXTENSION));
                } else if ($primaryMedia->path) {
                    $fileContent = Storage::disk('public')->get($primaryMedia->path);
                    $extension = strtolower(pathinfo($primaryMedia->path, PATHINFO_EXTENSION));
                }

                if ($fileContent && $extension) {
                    $mediaUrl = $this->mediaUrlService->getPublicUrl(
                        $primaryMedia->path ?? $primaryMedia->nextcloud_path ?? 'unknown',
                        $fileContent,
                        $extension,
                        $post->id,
                        $correlationId
                    );

                    $mediaType = in_array($extension, ['mp4', 'mov', 'webm']) ? 'VIDEO' : 'IMAGE';
                }
            }

            if ($isInstagram) {
                return $this->publishToInstagram($account, $payload, $mediaUrl, $mediaType, $correlationId, $providerAccountId);
            } else {
                return $this->publishToFacebook($account, $payload, $mediaUrl, $mediaType, $correlationId, $providerAccountId);
            }

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
        $endpoint = "https://graph.facebook.com/{$graphVersion}/{$providerAccountId}";
        
        if ($mediaUrl) {
            if ($mediaType === 'VIDEO') {
                $endpoint .= "/videos";
                $payload['file_url'] = $mediaUrl;
                $payload['description'] = $payload['message'];
                unset($payload['message']);
            } else {
                $endpoint .= "/photos";
                $payload['url'] = $mediaUrl;
            }
        } else {
            $endpoint .= "/feed";
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

    protected function publishToInstagram(ClientSocialAccount $account, array $payload, ?string $mediaUrl, ?string $mediaType, ?string $correlationId, string $providerAccountId): PublishResult
    {
        if (!$mediaUrl) {
            return PublishResult::failure('Instagram richiede un file multimediale (Immagine o Video).');
        }

        // Se l'account usa la vecchia config e manca l'ID, proviamo il fallback
        $igAccountId = $providerAccountId;
        if (empty($igAccountId)) {
             $igAccountId = $account->instagram_business_account_id;
        }

        // STEP 1: Creazione Container
        $graphVersion = config('services.meta.graph_version', 'v19.0');
        $containerEndpoint = "https://graph.facebook.com/{$graphVersion}/{$igAccountId}/media";
        $containerPayload = [
            'access_token' => $payload['access_token'],
            'caption' => $payload['message'],
        ];

        if ($mediaType === 'VIDEO') {
            $containerPayload['media_type'] = 'REELS'; // o VIDEO
            $containerPayload['video_url'] = $mediaUrl;
        } else {
            $containerPayload['image_url'] = $mediaUrl;
        }

        $client = Http::withHeaders([
            'X-Correlation-Id' => $correlationId ?? 'none'
        ]);

        $containerResponse = $client->post($containerEndpoint, $containerPayload);

        if (!$containerResponse->successful()) {
            return PublishResult::failure('Errore IG Container: ' . $containerResponse->body(), $containerResponse->json());
        }

        $containerData = $containerResponse->json();
        $containerId = $containerData['id'];

        // Ritorniamo un risultato di successo asincrono indicando che siamo in stato PROCESSING_CONTAINER
        // e salviamo il container_id per il job asincrono di riconciliazione.
        return PublishResult::processing($containerId, $containerData);
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
