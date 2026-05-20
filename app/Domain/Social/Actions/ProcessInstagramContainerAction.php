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
        if (!in_array($publication->status, [PublicationStatus::Publishing, PublicationStatus::NeedsManualReview]) || !$publication->external_container_id || $publication->external_post_id) {
            return; // Niente da riconciliare
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
            $this->escalateToManualReview($publication, "Timeout processo Instagram Container. Superato Max Lifecycle di {$maxLifecycle} minuti.");
            return;
        }

        $result = $this->service->checkAndPublishContainer(
            $publication->external_container_id,
            $accessToken,
            $igAccountId,
            $publication->correlation_id
        );

        if ($result->isFinished()) {
            $publication->update([
                'status' => PublicationStatus::Published->value,
                'meta_processing_state' => 'FINISHED',
                'external_post_id' => $result->externalPostId,
                'provider_last_response' => $result->publishResponse ?? $result->responseData,
                'published_at' => now(),
            ]);
            
            if ($publication->post) {
                $this->syncAction->execute($publication->post);
            }
        } elseif ($result->isTemporary()) {
            $publication->update([
                'meta_processing_state' => $result->status,
                'provider_state_payload' => $result->responseData
            ]);
            throw new ContainerProcessingException("Container IG ancora in progress o errore temporaneo... (Stato: {$result->status})");
        } else {
            $this->escalateToManualReview($publication, $result->errorMessage ?? "Errore permanente da Meta.", $result->publishResponse ?? $result->responseData);
        }
    }

    private function failPublication(MarketingCampaignPostPublication $publication, string $error): void
    {
        $publication->update([
            'status' => PublicationStatus::Failed->value,
            'error_message' => $error,
            'meta_processing_state' => 'FAILED',
        ]);
        
        Log::error("Instagram Publication Failed", ['publication_id' => $publication->id, 'error' => $error]);
        
        if ($publication->post) {
            $this->syncAction->execute($publication->post);
        }
    }

    private function escalateToManualReview(MarketingCampaignPostPublication $publication, string $error, ?array $response = null): void
    {
        $updateData = [
            'status' => PublicationStatus::NeedsManualReview->value,
            'error_message' => $error,
            'meta_processing_state' => 'FAILED',
        ];
        
        if ($response) {
            $updateData['provider_last_response'] = $response;
        }

        $publication->update($updateData);
        
        Log::error("Instagram Publication Escalatated to Manual Review", [
            'publication_id' => $publication->id, 
            'error' => $error,
            'correlation_id' => $publication->correlation_id
        ]);
        
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
