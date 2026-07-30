<?php

namespace App\Domain\Social\Actions;

use App\Domain\Social\Services\InstagramContainerStatusService;
use App\Enums\Social\PublicationFailureClassification;
use App\Enums\Social\PublicationStatus;
use App\Exceptions\Social\ContainerProcessingException;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessInstagramContainerAction
{
    public function __construct(
        private InstagramContainerStatusService $service,
        private SyncMarketingCampaignPostPublicationStatusAction $syncAction,
        private ResolveAssetAccessTokenAction $resolveTokenAction
    ) {}

    public function execute(int $publicationId): void
    {
        $publication = MarketingCampaignPostPublication::find($publicationId);
        if (! $publication || $publication->status !== PublicationStatus::Publishing) {
            return;
        }

        try {
            [$accessToken, $igAccountId] = $this->resolvePublishingCredentials(
                $publication->socialAccount
            );

            if (! $accessToken || ! $igAccountId) {
                $this->failPublication(
                    $publicationId,
                    'Credenziali Instagram mancanti o invalide.',
                    null,
                    PublicationFailureClassification::ManualReview
                );

                return;
            }

            $phase = ($publication->provider_state_payload ?? [])['phase']
                ?? 'single_container_processing';

            match ($phase) {
                'carousel_children_processing' => $this->processCarouselChildren(
                    $publicationId,
                    $accessToken,
                    $igAccountId
                ),
                'carousel_parent_processing', 'single_container_processing' => $this->processSingleContainer($publicationId, $accessToken, $igAccountId),
                'published', 'failed' => null,
                default => throw new \RuntimeException(
                    "Fase Instagram sconosciuta: {$phase}"
                ),
            };
        } catch (ContainerProcessingException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->failPublication(
                $publicationId,
                $e->getMessage(),
                null,
                PublicationFailureClassification::ManualReview
            );
        }
    }

    private function processCarouselChildren(
        int $publicationId,
        string $accessToken,
        string $igAccountId
    ): void {
        $publication = $this->publishingPublication($publicationId);
        if (! $publication) {
            return;
        }

        $statePayload = $publication->provider_state_payload ?? [];
        $children = $statePayload['children'] ?? [];
        if (! is_array($children) || $children === []) {
            $this->failPublication(
                $publicationId,
                'Elenco child container Instagram mancante o non valido.'
            );

            return;
        }

        foreach ($children as $child) {
            $childId = is_array($child) ? ($child['id'] ?? null) : null;
            if (! is_string($childId) || $childId === '') {
                $this->failPublication(
                    $publicationId,
                    'Identificativo child container Instagram non valido.'
                );

                return;
            }

            $statusResult = $this->service->getContainerStatus(
                $childId,
                $accessToken,
                $publication->correlation_id
            );

            if ($statusResult->isPermanentError) {
                $this->failPublication(
                    $publicationId,
                    "Errore permanente nel child container {$childId}: ".
                    ($statusResult->errorMessage ?? 'errore sconosciuto'),
                    $statusResult->responseData
                );

                return;
            }

            if ($statusResult->status !== 'FINISHED') {
                $this->persistProviderResponse(
                    $publicationId,
                    $statusResult->status,
                    $statusResult->responseData
                );

                throw new ContainerProcessingException(
                    "Child container Instagram {$childId} ancora in elaborazione."
                );
            }
        }

        $claimUuid = DB::transaction(function () use ($publicationId) {
            $publication = MarketingCampaignPostPublication::whereKey($publicationId)
                ->lockForUpdate()
                ->firstOrFail();
            $payload = $publication->provider_state_payload ?? [];

            if (
                $publication->status !== PublicationStatus::Publishing ||
                ($payload['phase'] ?? null) !== 'carousel_children_processing'
            ) {
                return null;
            }

            if (isset($payload['carousel_parent_claim_uuid'])) {
                if (
                    isset($payload['carousel_parent_claim_expires_at']) &&
                    now()->greaterThan($payload['carousel_parent_claim_expires_at'])
                ) {
                    throw new \RuntimeException(
                        'Claim di creazione carousel parent scaduto: esito ambiguo.'
                    );
                }

                throw new ContainerProcessingException(
                    'Creazione carousel parent già in corso da un altro worker.'
                );
            }

            $claimUuid = Str::uuid()->toString();
            $payload['carousel_parent_claim_uuid'] = $claimUuid;
            $payload['carousel_parent_claim_expires_at'] = now()
                ->addMinutes(5)
                ->toDateTimeString();
            $publication->update(['provider_state_payload' => $payload]);

            return $claimUuid;
        });

        if ($claimUuid === null) {
            return;
        }

        $childIds = array_map(
            static fn (array $child): string => $child['id'],
            $children
        );
        $parentResponse = $this->service->createCarouselParent(
            $igAccountId,
            $childIds,
            (string) ($statePayload['caption'] ?? ''),
            $accessToken,
            $publication->correlation_id
        );
        $parentId = $parentResponse['id'] ?? null;

        if (! is_string($parentId) || $parentId === '') {
            throw new \RuntimeException(
                'Meta non ha restituito l’identificativo del carousel parent.'
            );
        }

        DB::transaction(function () use ($publicationId, $claimUuid, $parentId, $parentResponse) {
            $publication = MarketingCampaignPostPublication::whereKey($publicationId)
                ->lockForUpdate()
                ->firstOrFail();
            $payload = $publication->provider_state_payload ?? [];

            if ($publication->status !== PublicationStatus::Publishing) {
                return;
            }

            if (($payload['carousel_parent_claim_uuid'] ?? null) !== $claimUuid) {
                throw new \RuntimeException(
                    'Claim carousel parent non corrispondente: esito ambiguo.'
                );
            }

            $payload['phase'] = 'carousel_parent_processing';
            unset(
                $payload['carousel_parent_claim_uuid'],
                $payload['carousel_parent_claim_expires_at']
            );

            $publication->update([
                'external_container_id' => $parentId,
                'provider_state_payload' => $payload,
                'provider_last_response' => $parentResponse,
                'meta_processing_state' => 'IN_PROGRESS',
            ]);
        });

        throw new ContainerProcessingException(
            'Carousel parent creato; elaborazione ancora in corso.'
        );
    }

    private function processSingleContainer(
        int $publicationId,
        string $accessToken,
        string $igAccountId
    ): void {
        $publication = $this->publishingPublication($publicationId);
        if (! $publication) {
            return;
        }

        if (! $publication->external_container_id) {
            $this->failPublication(
                $publicationId,
                'Manca external_container_id per il container Instagram.'
            );

            return;
        }

        $statusResult = $this->service->getContainerStatus(
            $publication->external_container_id,
            $accessToken,
            $publication->correlation_id
        );

        if ($statusResult->isPermanentError || $statusResult->status === 'ERROR') {
            $this->failPublication(
                $publicationId,
                $statusResult->errorMessage
                    ?? 'Errore permanente durante l’elaborazione del container Meta.',
                $statusResult->responseData
            );

            return;
        }

        if ($statusResult->status !== 'FINISHED') {
            $this->persistProviderResponse(
                $publicationId,
                $statusResult->status,
                $statusResult->responseData
            );

            throw new ContainerProcessingException(
                "Container Instagram ancora in elaborazione: {$statusResult->status}."
            );
        }

        $claimUuid = DB::transaction(function () use ($publicationId) {
            $publication = MarketingCampaignPostPublication::whereKey($publicationId)
                ->lockForUpdate()
                ->firstOrFail();
            $payload = $publication->provider_state_payload ?? [];

            if (
                $publication->status !== PublicationStatus::Publishing ||
                ! in_array(
                    $payload['phase'] ?? 'single_container_processing',
                    ['single_container_processing', 'carousel_parent_processing'],
                    true
                )
            ) {
                return null;
            }

            if (isset($payload['publish_claim_uuid'])) {
                if (
                    isset($payload['publish_claim_expires_at']) &&
                    now()->greaterThan($payload['publish_claim_expires_at'])
                ) {
                    throw new \RuntimeException(
                        'Claim di pubblicazione Instagram scaduto: esito ambiguo.'
                    );
                }

                throw new ContainerProcessingException(
                    'Pubblicazione Instagram già in corso da un altro worker.'
                );
            }

            $claimUuid = Str::uuid()->toString();
            $payload['publish_claim_uuid'] = $claimUuid;
            $payload['publish_claim_expires_at'] = now()
                ->addMinutes(5)
                ->toDateTimeString();
            $publication->update(['provider_state_payload' => $payload]);

            return $claimUuid;
        });

        if ($claimUuid === null) {
            return;
        }

        $publishResponse = $this->service->publishContainer(
            $igAccountId,
            $publication->external_container_id,
            $accessToken,
            $publication->correlation_id
        );
        $externalPostId = $publishResponse['id'] ?? null;

        if (! is_string($externalPostId) || $externalPostId === '') {
            throw new \RuntimeException(
                'Meta non ha restituito l’identificativo del post pubblicato.'
            );
        }

        $published = DB::transaction(function () use (
            $publicationId,
            $claimUuid,
            $publishResponse,
            $externalPostId
        ) {
            $publication = MarketingCampaignPostPublication::whereKey($publicationId)
                ->lockForUpdate()
                ->firstOrFail();
            $payload = $publication->provider_state_payload ?? [];

            if ($publication->status !== PublicationStatus::Publishing) {
                return false;
            }

            if (($payload['publish_claim_uuid'] ?? null) !== $claimUuid) {
                throw new \RuntimeException(
                    'Claim di pubblicazione Instagram non corrispondente: esito ambiguo.'
                );
            }

            $payload['phase'] = 'published';
            unset($payload['publish_claim_uuid'], $payload['publish_claim_expires_at']);

            $publication->update([
                'status' => PublicationStatus::Published->value,
                'meta_processing_state' => 'FINISHED',
                'external_post_id' => $externalPostId,
                'provider_last_response' => $publishResponse,
                'provider_state_payload' => $payload,
                'published_at' => now(),
                'error_message' => null,
                'failure_classification' => null,
            ]);

            return true;
        });

        if ($published) {
            $this->syncPublicationPost($publicationId);
        }
    }

    private function persistProviderResponse(
        int $publicationId,
        string $processingState,
        ?array $response
    ): void {
        DB::transaction(function () use ($publicationId, $processingState, $response) {
            $publication = MarketingCampaignPostPublication::whereKey($publicationId)
                ->lockForUpdate()
                ->first();

            if (! $publication || $publication->status !== PublicationStatus::Publishing) {
                return;
            }

            $publication->update([
                'meta_processing_state' => $processingState,
                'provider_last_response' => $response,
            ]);
        });
    }

    private function failPublication(
        int $publicationId,
        string $error,
        ?array $response = null,
        PublicationFailureClassification $classification =
            PublicationFailureClassification::Permanent
    ): void {
        $updated = DB::transaction(function () use (
            $publicationId,
            $error,
            $response,
            $classification
        ) {
            $publication = MarketingCampaignPostPublication::whereKey($publicationId)
                ->lockForUpdate()
                ->first();

            if (! $publication || $publication->status !== PublicationStatus::Publishing) {
                return false;
            }

            $payload = $publication->provider_state_payload ?? [];
            $payload['phase'] = 'failed';

            $updateData = [
                'status' => $classification === PublicationFailureClassification::ManualReview
                    ? PublicationStatus::NeedsManualReview->value
                    : PublicationStatus::Failed->value,
                'error_message' => $error,
                'meta_processing_state' => 'FAILED',
                'provider_state_payload' => $payload,
                'failure_classification' => $classification->value,
            ];

            if ($response !== null) {
                $updateData['provider_last_response'] = $response;
            }

            $publication->update($updateData);

            return true;
        });

        if (! $updated) {
            return;
        }

        Log::error('Instagram publication failed', [
            'publication_id' => $publicationId,
            'error' => $error,
            'failure_classification' => $classification->value,
        ]);

        $this->syncPublicationPost($publicationId);
    }

    private function publishingPublication(
        int $publicationId
    ): ?MarketingCampaignPostPublication {
        $publication = MarketingCampaignPostPublication::find($publicationId);

        return $publication?->status === PublicationStatus::Publishing
            ? $publication
            : null;
    }

    private function syncPublicationPost(int $publicationId): void
    {
        $publication = MarketingCampaignPostPublication::find($publicationId);
        if ($publication?->post) {
            $this->syncAction->execute($publication->post);
        }
    }

    private function resolvePublishingCredentials(?ClientSocialAccount $account): array
    {
        $accessToken = $account?->access_token;
        $providerAccountId = $account?->instagram_business_account_id
            ?: $account?->provider_account_id;

        if (
            $account &&
            $account->connection_strategy?->value === 'agency_oauth' &&
            $account->agencyAsset
        ) {
            $accessToken = $this->resolveTokenAction->execute($account->agencyAsset);
            $providerAccountId = $account->agencyAsset->instagram_business_account_id
                ?: $account->agencyAsset->provider_asset_id;
        }

        return [$accessToken, $providerAccountId];
    }
}
