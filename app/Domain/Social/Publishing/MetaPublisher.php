<?php

namespace App\Domain\Social\Publishing;

use App\Domain\Social\Actions\ResolveAssetAccessTokenAction;
use App\Domain\Social\Services\PublicationMediaDeliveryService;
use App\Enums\Social\PublicationFailureClassification;
use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaPublisher implements SocialPublisherInterface
{
    protected PublicationMediaDeliveryService $mediaDeliveryService;

    public function __construct(PublicationMediaDeliveryService $mediaDeliveryService)
    {
        $this->mediaDeliveryService = $mediaDeliveryService;
    }

    public function verifyAccountCapabilities(ClientSocialAccount $account): bool
    {
        if ($account->connection_strategy?->value === 'agency_oauth') {
            if (! $account->agencyAsset || ! $account->agencyAsset->connection) {
                return false;
            }
            if (! $account->agencyAsset->is_active) {
                return false;
            }
            if ($account->agencyAsset->connection->requires_reauth) {
                return false;
            }

            return ! empty(app(ResolveAssetAccessTokenAction::class)->execute($account->agencyAsset));
        }

        $hasPlatformTarget = match ($account->platform) {
            SocialPlatform::Facebook => filled($account->facebook_page_id)
                || filled($account->provider_account_id),
            SocialPlatform::Instagram => filled(
                $account->instagram_business_account_id
            ) || filled($account->provider_account_id),
            default => false,
        };

        return $account->isApiConnected() && $hasPlatformTarget;
    }

    public function publish(MarketingCampaignPostPublication $publication, ClientSocialAccount $account, ?string $correlationId = null): PublishResult
    {
        if ($account->connection_strategy?->value === 'agency_oauth' && ! $account->agencyAsset) {
            return PublishResult::failure(
                'Nessun asset Meta assegnato all’account del cliente.',
                PublicationFailureClassification::ManualReview
            );
        }

        if ($account->connection_strategy?->value === 'agency_oauth' && $account->agencyAsset && $account->agencyAsset->connection) {
            if ($account->agencyAsset->connection->requires_reauth) {
                return PublishResult::failure('La connessione dell\'agenzia Meta richiede un nuovo accesso (Token Scaduto/Revocato). Ricollega l\'account dal pannello Admin.', PublicationFailureClassification::ManualReview);
            }
        }

        if (! $this->verifyAccountCapabilities($account)) {
            return PublishResult::failure('Account non configurato per la pubblicazione API (o token mancante).', PublicationFailureClassification::ManualReview);
        }

        if (config('social.publishing.dry_run', false)) {
            return PublishResult::success(
                'dryrun_meta_'.$publication->id.'_'.now()->timestamp,
                null,
                [
                    'dry_run' => true,
                    'platform' => $account->platform->value,
                    'publication_id' => $publication->id,
                    'account_id' => $account->id,
                    'should_not_count_as_real_publication' => true,
                ]
            );
        }

        try {
            $isInstagram = $account->platform->value === 'instagram';

            $snapshot = $publication->payload_snapshot ?? [];

            // Resolve Target Account live: use frozen target data
            // To respect user feedback: "MetaPublisher deve utilizzare proprio il target congelato dopo la verifica."
            $snapshotTarget = $snapshot['target'];

            $message = $snapshot['caption'];
            $hashtags = $snapshot['hashtags'];
            $privacyOptions = $snapshotTarget['privacy_options'] ?? [];
            if (! empty($hashtags)) {
                $message = trim($message."\n\n".collect($hashtags)
                    ->map(fn ($h) => str_starts_with($h, '#') ? $h : "#{$h}")
                    ->implode(' '));
            }

            // Risolvi token
            [$accessToken, $defaultProviderId] = $this->resolveTokenAndProviderId($account);

            // USE FROZEN TARGET ID
            $providerAccountId = $snapshotTarget['external_id'] ?? throw new \Exception('Missing frozen target external_id for Meta publishing');

            $payload = [
                'access_token' => $accessToken,
                'message' => $message,
            ];

            // Media attachment handling from snapshot
            $mediaItemsData = collect($snapshot['media'] ?? []);

            // Generate public URLs for the media items from the snapshot directly
            $mediaUrls = [];
            $mediaType = null;
            $diagnosticPayloads = [];
            $mediaDescriptors = [];
            $contentTypeStr = $snapshotTarget['publication_type'];

            if (count($mediaItemsData) > 0) {
                $primaryMedia = $mediaItemsData[0];
                $mediaType = strtolower($primaryMedia['media_type']) === 'video' ? 'VIDEO' : 'IMAGE';

                if ($contentTypeStr === 'reel' && $mediaType !== 'VIDEO') {
                    return PublishResult::failure(
                        'Dominio Meta: Un Reel richiede obbligatoriamente un file video.',
                        PublicationFailureClassification::Permanent
                    );
                }

                $deliveryResults = $this->mediaDeliveryService->deliver($publication);

                foreach ($deliveryResults as $idx => $result) {
                    if (! $result->passed) {
                        return PublishResult::failure(implode(', ', $result->errors), PublicationFailureClassification::Temporary);
                    }

                    $mediaUrls[] = $result->url;
                    $diagnosticPayloads[] = $result->diagnosticPayload;

                    $mediaDescriptors[] = [
                        'id' => $mediaItemsData[$idx]['media_id'] ?? $idx,
                        'url' => $result->url,
                        'type' => $result->type,
                    ];
                }
            }

            if ($isInstagram) {
                $result = $this->publishToInstagram($account, $payload, $mediaDescriptors, $contentTypeStr, $correlationId, $providerAccountId);
            } else {
                $result = $this->publishToFacebook(
                    $payload,
                    $mediaDescriptors,
                    $correlationId,
                    $providerAccountId,
                    $privacyOptions
                );
            }

            if (! empty($diagnosticPayloads)) {
                $snapshotDiag = $result->responseSnapshot ?? [];
                $snapshotDiag['media_diagnostics'] = $diagnosticPayloads;
                $result = new PublishResult(
                    $result->success,
                    $result->externalPostId,
                    $result->externalContainerId,
                    $result->externalPermalink,
                    $result->errorMessage,
                    $snapshotDiag,
                    $result->isProcessing(),
                    $result->externalTaskId,
                    $result->providerStatePayload,
                    $result->failureClassification
                );
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Meta Publisher Exception', [
                'error' => $e->getMessage(),
                'publication_id' => $publication->id,
                'correlation_id' => $correlationId,
            ]);

            return PublishResult::failure('Eccezione durante la pubblicazione: '.$e->getMessage(), PublicationFailureClassification::Temporary);
        }
    }

    protected function classifyErrorResponse(Response $response): PublicationFailureClassification
    {
        $status = $response->status();
        if ($status >= 500 || $status === 429 || $status === 408) {
            return PublicationFailureClassification::Temporary;
        }
        if ($status === 401 || $status === 403) {
            return PublicationFailureClassification::ManualReview;
        }

        return PublicationFailureClassification::Permanent;
    }

    protected function publishToFacebook(
        array $payload,
        array $mediaDescriptors,
        ?string $correlationId,
        string $providerAccountId,
        array $privacyOptions = []
    ): PublishResult {
        $graphVersion = config('services.meta.graph_version', 'v19.0');
        $baseEndpoint = "https://graph.facebook.com/{$graphVersion}/{$providerAccountId}";

        $client = Http::withHeaders([
            'X-Correlation-Id' => $correlationId ?? 'none',
        ]);

        if (count($mediaDescriptors) > 1) {
            if (collect($mediaDescriptors)->contains(
                fn (array $media): bool => strtolower($media['type'] ?? '') !== 'image'
            )) {
                return PublishResult::failure(
                    'Facebook supporta carousel multipli solo con immagini.',
                    PublicationFailureClassification::Permanent
                );
            }

            $attachedMedia = [];
            foreach ($mediaDescriptors as $index => $media) {
                $uploadResponse = $client->post("{$baseEndpoint}/photos", [
                    'access_token' => $payload['access_token'],
                    'url' => $media['url'],
                    'published' => false,
                ]);

                if (! $uploadResponse->successful()) {
                    return PublishResult::failure(
                        "Errore API Facebook durante l’upload dell’immagine {$index}: ".
                            $uploadResponse->body(),
                        $this->classifyErrorResponse($uploadResponse),
                        $uploadResponse->json()
                    );
                }

                $photoId = $uploadResponse->json('id');
                if (! is_string($photoId) || $photoId === '') {
                    return PublishResult::failure(
                        "Facebook non ha restituito l’ID dell’immagine {$index}.",
                        PublicationFailureClassification::Permanent,
                        $uploadResponse->json()
                    );
                }

                $attachedMedia[] = $photoId;
            }

            $endpoint = "{$baseEndpoint}/feed";
            foreach ($attachedMedia as $index => $photoId) {
                $payload["attached_media[{$index}]"] = json_encode(
                    ['media_fbid' => $photoId],
                    JSON_THROW_ON_ERROR
                );
            }
        } elseif (count($mediaDescriptors) === 1) {
            $media = $mediaDescriptors[0];
            if (strtolower($media['type'] ?? '') === 'video') {
                $endpoint = "{$baseEndpoint}/videos";
                $payload['file_url'] = $media['url'];
                $payload['description'] = $payload['message'];
                unset($payload['message']);
            } else {
                $endpoint = "{$baseEndpoint}/photos";
                $payload['url'] = $media['url'];
            }
        } else {
            $endpoint = "{$baseEndpoint}/feed";
        }

        if (! empty($privacyOptions)) {
            $payload['privacy'] = json_encode($privacyOptions);
        }

        $response = $client->post($endpoint, $payload);

        if (! $response->successful()) {
            return PublishResult::failure('Errore API Facebook: '.$response->body(), $this->classifyErrorResponse($response), $response->json());
        }

        $data = $response->json();
        $postId = $this->providerIdentifier(
            is_array($data) ? ($data['id'] ?? null) : null
        );
        if ($postId === null) {
            return PublishResult::failure(
                'Facebook non ha restituito lâ€™ID del contenuto pubblicato.',
                PublicationFailureClassification::Permanent,
                is_array($data) ? $data : null
            );
        }

        return PublishResult::success($postId, null, $data);
    }

    protected function publishToInstagram(ClientSocialAccount $account, array $payload, array $mediaDescriptors, ?string $contentTypeStr, ?string $correlationId, string $providerAccountId): PublishResult
    {
        if (empty($mediaDescriptors)) {
            return PublishResult::failure('Instagram richiede un file multimediale (Immagine o Video).', PublicationFailureClassification::Permanent);
        }

        $igAccountId = $providerAccountId;

        $graphVersion = config('services.meta.graph_version', 'v19.0');
        $baseEndpoint = "https://graph.facebook.com/{$graphVersion}/{$igAccountId}";

        $client = Http::withHeaders([
            'X-Correlation-Id' => $correlationId ?? 'none',
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

                if (! $itemResponse->successful()) {
                    return PublishResult::failure("Errore IG Carousel Item Container (Indice {$index}): ".$itemResponse->body(), $this->classifyErrorResponse($itemResponse), $itemResponse->json());
                }

                $childId = $this->providerIdentifier(
                    $itemResponse->json('id')
                );
                if ($childId === null) {
                    return PublishResult::failure(
                        "Instagram non ha restituito lâ€™ID del child container {$index}.",
                        PublicationFailureClassification::Permanent,
                        $itemResponse->json()
                    );
                }
                $itemContainerIds[] = $childId;
                $childrenPayload[] = ['id' => $childId, 'type' => $media['type']];
            }

            $providerStatePayload = [
                'phase' => 'carousel_children_processing',
                'children' => $childrenPayload,
                'caption' => $payload['message'] ?? '',
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

            if (! $containerResponse->successful()) {
                return PublishResult::failure('Errore IG Single Container: '.$containerResponse->body(), $this->classifyErrorResponse($containerResponse), $containerResponse->json());
            }

            $containerData = $containerResponse->json();
            $containerId = $this->providerIdentifier(
                is_array($containerData)
                    ? ($containerData['id'] ?? null)
                    : null
            );
            if ($containerId === null) {
                return PublishResult::failure(
                    'Instagram non ha restituito lâ€™ID del container.',
                    PublicationFailureClassification::Permanent,
                    is_array($containerData) ? $containerData : null
                );
            }

            $providerStatePayload = [
                'phase' => 'single_container_processing',
            ];

            return PublishResult::processing($containerId, $containerData, null, $providerStatePayload);
        }
    }

    private function providerIdentifier(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $identifier = trim((string) $value);

        return $identifier !== '' ? $identifier : null;
    }

    /**
     * Isola la logica di risoluzione del token e dell'ID Provider.
     * Decoupling richiesto per pulizia strutturale.
     */
    private function resolveTokenAndProviderId(ClientSocialAccount $account): array
    {
        if ($account->connection_strategy?->value === 'agency_oauth' && $account->agencyAsset) {
            $accessToken = app(ResolveAssetAccessTokenAction::class)->execute($account->agencyAsset);
            $providerAccountId = $account->agencyAsset->provider_asset_id;

            return [$accessToken, $providerAccountId];
        }

        return [$account->access_token, $account->provider_account_id];
    }
}
