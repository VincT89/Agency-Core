<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingCampaignPostPublication;
use App\Enums\Social\PublicationStatus;
use App\Domain\Social\Services\InstagramContainerStatusService;
use App\Exceptions\Social\ContainerProcessingException;
use Illuminate\Support\Facades\Log;

class ProcessInstagramContainerAction
{
    public function __construct(
        private InstagramContainerStatusService $service,
        private SyncMarketingCampaignPostPublicationStatusAction $syncAction,
        private ResolveAssetAccessTokenAction $resolveTokenAction
    ) {}

    public function execute(MarketingCampaignPostPublication $publication): void
    {
        if (!in_array($publication->status, [PublicationStatus::Publishing]) || $publication->external_post_id) {
            return;
        }

        $account = $publication->socialAccount;
        [$accessToken, $igAccountId] = $this->resolvePublishingCredentials($account);

        if (!$account || !$accessToken || !$igAccountId) {
            $this->failPublication($publication, "Dati account Instagram mancanti o incompleti (o token irrisolvibile).");
            return;
        }

        // Verifica Max Lifecycle
        $maxLifecycle = config('services.meta.instagram.max_container_lifecycle', 15);
        if ($publication->created_at->diffInMinutes(now()) > $maxLifecycle) {
            $this->failPublication($publication, "Timeout processo Instagram Container. Superato Max Lifecycle di {$maxLifecycle} minuti.");
            return;
        }

        $publication->increment('poll_count');

        $statePayload = $publication->provider_state_payload ?? [];
        $phase = $statePayload['phase'] ?? 'single_container_processing';

        try {
            if ($phase === 'carousel_children_processing') {
                $this->processCarouselChildren($publication, $accessToken, $igAccountId, $statePayload);
            } elseif ($phase === 'carousel_parent_processing') {
                $this->processCarouselParent($publication, $accessToken, $igAccountId);
            } elseif ($phase === 'single_container_processing') {
                $this->processSingleContainer($publication, $accessToken, $igAccountId);
            }
        } catch (\Exception $e) {
            if ($e instanceof ContainerProcessingException) {
                throw $e;
            }
            $this->failPublication($publication, $e->getMessage());
        }
    }

    private function processCarouselChildren(MarketingCampaignPostPublication $publication, string $accessToken, string $igAccountId, array $statePayload): void
    {
        $children = $statePayload['children'] ?? [];
        $allFinished = true;
        
        foreach ($children as $child) {
            $statusResult = $this->service->getContainerStatus($child['id'], $accessToken, $publication->correlation_id);
            if ($statusResult->isPermanentError) {
                $this->failPublication($publication, "Errore permanente in uno dei child container ({$child['id']}): {$statusResult->errorMessage}");
                return;
            }
            if ($statusResult->status !== 'FINISHED') {
                $allFinished = false;
                break;
            }
        }

        if ($allFinished) {
            // Tutti i child pronti, creiamo il parent
            $childIds = array_column($children, 'id');
            $caption = $statePayload['caption'] ?? '';
            
            $parentResponse = $this->service->createCarouselParent($igAccountId, $childIds, $caption, $accessToken, $publication->correlation_id);
            
            $statePayload['phase'] = 'carousel_parent_processing';
            $publication->update([
                'external_container_id' => $parentResponse['id'],
                'provider_state_payload' => $statePayload,
                'meta_processing_state' => 'IN_PROGRESS'
            ]);

            throw new ContainerProcessingException("Carousel parent creato. In attesa di elaborazione...");
        }

        throw new ContainerProcessingException("Carousel children in elaborazione...");
    }

    private function processCarouselParent(MarketingCampaignPostPublication $publication, string $accessToken, string $igAccountId): void
    {
        $this->processSingleContainer($publication, $accessToken, $igAccountId);
    }

    private function processSingleContainer(MarketingCampaignPostPublication $publication, string $accessToken, string $igAccountId): void
    {
        if (!$publication->external_container_id) {
            $this->failPublication($publication, "Manca external_container_id per processSingleContainer.");
            return;
        }

        $statusResult = $this->service->getContainerStatus($publication->external_container_id, $accessToken, $publication->correlation_id);

        if ($statusResult->isPermanentError) {
            $this->failPublication($publication, $statusResult->errorMessage ?? "Errore permanente da Meta.", $statusResult->responseData);
            return;
        }

        if ($statusResult->status === 'FINISHED') {
            // Pubblichiamo il container
            $publishResponse = $this->service->publishContainer($igAccountId, $publication->external_container_id, $accessToken, $publication->correlation_id);
            
            $statePayload = $publication->provider_state_payload ?? [];
            $statePayload['phase'] = 'published';

            $publication->update([
                'status' => PublicationStatus::Published->value,
                'meta_processing_state' => 'FINISHED',
                'external_post_id' => $publishResponse['id'],
                'provider_last_response' => $publishResponse,
                'provider_state_payload' => $statePayload,
                'published_at' => now(),
            ]);
            
            if ($publication->post) {
                $this->syncAction->execute($publication->post);
            }
        } else {
            $publication->update([
                'meta_processing_state' => $statusResult->status,
                'provider_last_response' => $statusResult->responseData
            ]);
            throw new ContainerProcessingException("Container IG ancora in progress o errore temporaneo... (Stato: {$statusResult->status})");
        }
    }

    private function failPublication(MarketingCampaignPostPublication $publication, string $error, ?array $response = null): void
    {
        $statePayload = $publication->provider_state_payload ?? [];
        $statePayload['phase'] = 'failed';

        $updateData = [
            'status' => PublicationStatus::Failed->value,
            'error_message' => $error,
            'meta_processing_state' => 'FAILED',
            'provider_state_payload' => $statePayload,
        ];
        
        if ($response) {
            $updateData['provider_last_response'] = $response;
        }

        $publication->update($updateData);
        
        Log::error("Instagram Publication Failed", ['publication_id' => $publication->id, 'error' => $error]);
        
        if ($publication->post) {
            $this->syncAction->execute($publication->post);
        }
    }

    private function resolvePublishingCredentials(?\App\Models\ClientSocialAccount $account): array
    {
        $accessToken = $account?->access_token;
        $providerAccountId = $account?->provider_account_id ?: $account?->instagram_business_account_id;

        if ($account && $account->connection_strategy?->value === 'agency_oauth' && $account->agencyAsset) {
            $accessToken = $this->resolveTokenAction->execute($account->agencyAsset);
            $providerAccountId = $account->agencyAsset->provider_asset_id;
        }

        return [$accessToken, $providerAccountId];
    }
}
