<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Integrations\N8n\N8nClient;
use App\Models\MarketingCampaignPost;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendMarketingCampaignPostToN8nJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        public MarketingCampaignPost $post,
        public array $payload,
        public ?string $temp_path = null,
        public bool $savedToClient = false,
        public array $previousState = []
    ) {}

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(N8nClient $client): void
    {
        $currentPost = $this->post->fresh();
        
        if (!$currentPost) {
            return;
        }

        if ($currentPost->n8n_request_id !== $this->payload['request_id'] || $currentPost->status->value !== \App\Enums\Social\MarketingCampaignPostStatus::PendingN8n->value) {
            return;
        }

        $client->submitMarketingCampaignPost($this->payload);

        MarketingCampaignPost::query()
            ->whereKey($this->post->id)
            ->where('status', \App\Enums\Social\MarketingCampaignPostStatus::PendingN8n->value)
            ->where('n8n_request_id', $this->payload['request_id'])
            ->update([
                'status' => \App\Enums\Social\MarketingCampaignPostStatus::SubmittedToN8n->value,
                'submitted_to_n8n_at' => now(),
            ]);
    }

    public function failed(Throwable $exception): void
    {
        $fileToDelete = null;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($exception, &$fileToDelete) {
                $lockedPost = MarketingCampaignPost::lockForUpdate()->find($this->post->id);
                
                if (!$lockedPost || $lockedPost->n8n_request_id !== $this->payload['request_id']) {
                    return;
                }

                if (in_array($lockedPost->status->value, [
                    \App\Enums\Social\MarketingCampaignPostStatus::PendingN8n->value,
                    \App\Enums\Social\MarketingCampaignPostStatus::SubmittedToN8n->value
                ])) {
                    if ($this->shouldDeleteTempFile($lockedPost)) {
                        $fileToDelete = $this->temp_path;
                    }

                    $lockedPost->update([
                        'status' => array_key_exists('status', $this->previousState) ? $this->previousState['status'] : ($lockedPost->n8n_previous_status ?? \App\Enums\Social\MarketingCampaignPostStatus::Generated->value),
                        'n8n_previous_status' => array_key_exists('n8n_previous_status', $this->previousState) ? $this->previousState['n8n_previous_status'] : null,
                        'n8n_request_id' => array_key_exists('n8n_request_id', $this->previousState) ? $this->previousState['n8n_request_id'] : null,
                        'approved_payload_snapshot' => array_key_exists('approved_payload_snapshot', $this->previousState) ? $this->previousState['approved_payload_snapshot'] : null,
                        'n8n_payload_hash' => array_key_exists('n8n_payload_hash', $this->previousState) ? $this->previousState['n8n_payload_hash'] : null,
                        'n8n_internal_context' => array_key_exists('n8n_internal_context', $this->previousState) ? $this->previousState['n8n_internal_context'] : null,
                        'submitted_to_n8n_at' => array_key_exists('submitted_to_n8n_at', $this->previousState) ? $this->previousState['submitted_to_n8n_at'] : null,
                        'n8n_completed_at' => array_key_exists('n8n_completed_at', $this->previousState) ? $this->previousState['n8n_completed_at'] : null,
                        'n8n_error' => "Errore invio a N8n: " . substr($exception->getMessage(), 0, 255),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Marketing submit compensation failed (DB)', [
                'error' => $e->getMessage(),
            ]);
        }

        if ($fileToDelete) {
            try {
                Storage::disk('public')->delete($fileToDelete);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Marketing submit compensation failed (Storage)', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function shouldDeleteTempFile(?MarketingCampaignPost $currentPost): bool
    {
        if (!$this->temp_path || $this->savedToClient || !$currentPost) {
            return false;
        }
        
        $n8nInternalContext = $currentPost->n8n_internal_context ?? [];
        $internalTempPath = $n8nInternalContext['_internal_temp_logo_path'] ?? null;
        
        return $internalTempPath === $this->temp_path && $currentPost->n8n_request_id === $this->payload['request_id'];
    }
}
