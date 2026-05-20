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

    public function handle(\App\Domain\Social\Actions\ProcessInstagramContainerAction $action): void
    {
        try {
            $action->execute($this->publication);
        } catch (\Exception $e) {
            if ($e instanceof \App\Exceptions\Social\ContainerProcessingException) {
                throw $e; // Lasciamo che Laravel gestisca il backoff
            }

            Log::error('CheckInstagramContainerStatusJob Exception', [
                'error' => $e->getMessage(),
                'publication_id' => $this->publication->id,
                'correlation_id' => $this->publication->correlation_id
            ]);
            throw $e; // Propaghiamo l'errore per usare il backoff anche in questo caso
        }
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
            'status' => \App\Enums\Social\PublicationStatus::Failed->value,
            'error_message' => $message,
            'meta_processing_state' => 'FAILED',
        ]);
        
        if ($this->publication->post) {
            app(\App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction::class)->execute($this->publication->post);
        }
        
        Log::error("Instagram Publication Definitively Failed", [
            'publication_id' => $this->publication->id, 
            'error' => $exception->getMessage()
        ]);
    }
}
