<?php

namespace App\Jobs\Social;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckInstagramContainerStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 10;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 120, 300, 900];
    }

    public function __construct(
        public MarketingCampaignPostPublication $publication
    ) {
        $this->onQueue('social-reconciliation');
    }

    public function handle(): void
    {
        $publication = $this->publication;

        if ($publication->status !== 'publishing' || !$publication->external_container_id || $publication->external_post_id) {
            return; // Niente da riconciliare
        }

        $account = $publication->socialAccount;

        $accessToken = $account?->access_token;
        $igAccountId = $account?->provider_account_id ?: $account?->instagram_business_account_id;

        if ($account && $account->connection_strategy?->value === 'agency_oauth' && $account->agencyAsset) {
            $accessToken = app(\App\Domain\Social\Actions\ResolveAssetAccessTokenAction::class)->execute($account->agencyAsset);
            $igAccountId = $account->agencyAsset->provider_asset_id;
        }

        if (!$account || !$accessToken || !$igAccountId) {
            $this->failPublication($publication, "Dati account Instagram mancanti o incompleti (o token irrisolvibile).");
            return;
        }

        // Verifica Max Lifecycle (se il container è pendente da troppo tempo -> fail)
        $maxLifecycle = config('services.meta.instagram.max_container_lifecycle', 15);
        if ($publication->created_at->diffInMinutes(now()) > $maxLifecycle) {
            $this->escalateToManualReview($publication, "Timeout processo Instagram Container. Superato Max Lifecycle di {$maxLifecycle} minuti.");
            return;
        }

        try {
            $client = Http::withHeaders([
                'X-Correlation-Id' => $publication->correlation_id ?? 'none'
            ]);

            $graphVersion = config('services.meta.graph_version', 'v19.0');
            $statusEndpoint = "https://graph.facebook.com/{$graphVersion}/{$publication->external_container_id}";
            $statusResponse = $client->get($statusEndpoint, [
                'fields' => 'status_code,status',
                'access_token' => $accessToken,
            ]);

            if (!$statusResponse->successful()) {
                // Errore HTTP: logghiamo ma riproviamo
                $errorData = $statusResponse->json();
                $publication->update(['provider_last_response' => $errorData]);
                throw new \Exception('Errore nel recupero status Container: ' . $statusResponse->body());
            }

            $statusData = $statusResponse->json();
            $statusCode = $statusData['status_code'] ?? 'UNKNOWN';

            if ($statusCode === 'FINISHED') {
                // Procediamo con la pubblicazione finale del container
                $publishEndpoint = "https://graph.facebook.com/{$graphVersion}/{$igAccountId}/media_publish";
                $publishResponse = $client->post($publishEndpoint, [
                    'creation_id' => $publication->external_container_id,
                    'access_token' => $accessToken,
                ]);

                if ($publishResponse->successful()) {
                    $publishData = $publishResponse->json();
                    $publication->update([
                        'status' => 'published',
                        'meta_processing_state' => 'FINISHED',
                        'external_post_id' => $publishData['id'],
                        'provider_last_response' => $publishData,
                        'published_at' => now(),
                    ]);
                } else {
                    $errorData = $publishResponse->json();
                    $publication->update(['provider_last_response' => $errorData]);
                    
                    // Se l'errore è permanente, passiamo in review. Altrimenti riproviamo.
                    $this->escalateToManualReview($publication, "Errore nella media_publish di Instagram: " . $publishResponse->body());
                }
            } elseif ($statusCode === 'ERROR') {
                $this->escalateToManualReview($publication, "Instagram ha riportato un errore nel container (status ERROR).", $statusData);
            } elseif ($statusCode === 'EXPIRED') {
                $this->escalateToManualReview($publication, "Il container Instagram è scaduto.", $statusData);
            } else {
                // IN_PROGRESS o PUBLISHED -> riprova
                $publication->update([
                    'meta_processing_state' => $statusCode,
                    'provider_state_payload' => $statusData
                ]);
                throw new \App\Exceptions\Social\ContainerProcessingException("Container IG ancora in progress... (Stato: {$statusCode})");
            }

        } catch (\Exception $e) {
            if ($e instanceof \App\Exceptions\Social\ContainerProcessingException) {
                throw $e; // Lasciamo che Laravel gestisca il backoff
            }

            Log::error('CheckInstagramContainerStatusJob Exception', [
                'error' => $e->getMessage(),
                'publication_id' => $publication->id,
                'correlation_id' => $publication->correlation_id
            ]);
            throw $e; // Propaghiamo l'errore per usare il backoff anche in questo caso
        }
    }

    private function failPublication(MarketingCampaignPostPublication $publication, string $error): void
    {
        $publication->update([
            'status' => 'failed',
            'error_message' => $error,
            'meta_processing_state' => 'FAILED',
        ]);
        
        Log::error("Instagram Publication Failed", ['publication_id' => $publication->id, 'error' => $error]);
    }

    private function escalateToManualReview(MarketingCampaignPostPublication $publication, string $error, ?array $response = null): void
    {
        $updateData = [
            'status' => 'needs_manual_review',
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
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $message = "Fallimento definitivo dopo i retry massimi.";
        if ($exception instanceof \App\Exceptions\Social\ContainerProcessingException) {
            $message = "Container Instagram rimasto in processing oltre il limite di tentativi.";
        }

        $this->publication->update([
            'status' => 'failed',
            'error_message' => $message,
            'meta_processing_state' => 'FAILED',
        ]);
        
        Log::error("Instagram Publication Definitively Failed", [
            'publication_id' => $this->publication->id, 
            'error' => $exception->getMessage()
        ]);
    }
}
