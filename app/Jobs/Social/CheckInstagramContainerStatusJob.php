<?php

namespace App\Jobs\Social;

use App\Domain\Social\Actions\ProcessInstagramContainerAction;
use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use App\Enums\Social\PublicationFailureClassification;
use App\Enums\Social\PublicationStatus;
use App\Exceptions\Social\ContainerProcessingException;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckInstagramContainerStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 10;

    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->publicationId))->dontRelease()->expireAfter(600)];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 120, 300, 900];
    }

    public function __construct(
        public readonly int $publicationId
    ) {
        $this->onQueue('social-reconciliation');
    }

    public function handle(ProcessInstagramContainerAction $action): void
    {
        $publication = MarketingCampaignPostPublication::find($this->publicationId);
        if (! $publication) {
            return;
        }

        try {
            $action->execute($this->publicationId);
        } catch (\Exception $e) {
            if ($e instanceof ContainerProcessingException) {
                throw $e; // Lasciamo che Laravel gestisca il backoff
            }

            Log::error('CheckInstagramContainerStatusJob Exception', [
                'error' => $e->getMessage(),
                'publication_id' => $this->publicationId,
                'correlation_id' => $publication->correlation_id,
            ]);
            throw $e; // Propaghiamo l'errore per usare il backoff anche in questo caso
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $message = 'Fallimento definitivo dopo i retry massimi.';
        if ($exception instanceof ContainerProcessingException) {
            $message = 'Container Instagram rimasto in processing oltre il limite di tentativi.';
        }

        $updated = DB::transaction(function () use ($message) {
            $publication = MarketingCampaignPostPublication::whereKey($this->publicationId)
                ->lockForUpdate()
                ->first();

            if (! $publication || $publication->status !== PublicationStatus::Publishing) {
                return false;
            }

            $payload = $publication->provider_state_payload ?? [];
            $hasAmbiguousClaim = isset($payload['publish_claim_uuid']) ||
                isset($payload['carousel_parent_claim_uuid']);

            $publication->update([
                'status' => $hasAmbiguousClaim
                    ? PublicationStatus::NeedsManualReview->value
                    : PublicationStatus::Failed->value,
                'error_message' => $message,
                'meta_processing_state' => 'FAILED',
                'failure_classification' => $hasAmbiguousClaim
                    ? PublicationFailureClassification::ManualReview->value
                    : PublicationFailureClassification::Temporary->value,
            ]);

            return true;
        });

        if (! $updated) {
            return;
        }

        $publication = MarketingCampaignPostPublication::find($this->publicationId);
        if ($publication?->post) {
            app(SyncMarketingCampaignPostPublicationStatusAction::class)->execute($publication->post);
        }

        Log::error('Instagram Publication Definitively Failed', [
            'publication_id' => $this->publicationId,
            'error' => $exception->getMessage(),
        ]);
    }
}
