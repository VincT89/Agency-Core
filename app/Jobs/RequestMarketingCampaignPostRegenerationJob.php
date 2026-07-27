<?php

namespace App\Jobs;

use App\Models\MarketingCampaignPost;
use App\Services\Integrations\N8n\N8nClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RequestMarketingCampaignPostRegenerationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public MarketingCampaignPost $post,
        public array $payload,
        public string $previousStatus,
        public int $commentId,
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

        if ($currentPost->n8n_request_id !== $this->payload['request_id'] || $currentPost->status->value !== \App\Enums\Social\MarketingCampaignPostStatus::Regenerating->value) {
            return;
        }

        \Illuminate\Support\Facades\Log::info('Dispatching marketing regeneration', [
            'post_id' => $this->post->id,
            'type' => $this->payload['regeneration_type'] ?? null,
        ]);

        $client->requestMarketingCampaignPostRegeneration($this->payload);

        MarketingCampaignPost::query()
            ->whereKey($this->post->id)
            ->where('status', \App\Enums\Social\MarketingCampaignPostStatus::Regenerating->value)
            ->where('n8n_request_id', $this->payload['request_id'])
            ->update([
                'submitted_to_n8n_at' => now(),
            ]);
    }

    public function failed(Throwable $exception): void
    {
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($exception) {
                $lockedPost = MarketingCampaignPost::lockForUpdate()->find($this->post->id);
                
                if (!$lockedPost || $lockedPost->n8n_request_id !== $this->payload['request_id']) {
                    return;
                }

                if ($lockedPost->status->value === \App\Enums\Social\MarketingCampaignPostStatus::Regenerating->value) {
                    $lockedPost->update([
                        'status' => array_key_exists('status', $this->previousState) ? $this->previousState['status'] : ($lockedPost->n8n_previous_status ?? \App\Enums\Social\MarketingCampaignPostStatus::Generated->value),
                        'n8n_previous_status' => array_key_exists('n8n_previous_status', $this->previousState) ? $this->previousState['n8n_previous_status'] : null,
                        'n8n_request_id' => array_key_exists('n8n_request_id', $this->previousState) ? $this->previousState['n8n_request_id'] : null,
                        'approved_payload_snapshot' => array_key_exists('approved_payload_snapshot', $this->previousState) ? $this->previousState['approved_payload_snapshot'] : null,
                        'n8n_payload_hash' => array_key_exists('n8n_payload_hash', $this->previousState) ? $this->previousState['n8n_payload_hash'] : null,
                        'n8n_internal_context' => array_key_exists('n8n_internal_context', $this->previousState) ? $this->previousState['n8n_internal_context'] : null,
                        'submitted_to_n8n_at' => array_key_exists('submitted_to_n8n_at', $this->previousState) ? $this->previousState['submitted_to_n8n_at'] : null,
                        'n8n_completed_at' => array_key_exists('n8n_completed_at', $this->previousState) ? $this->previousState['n8n_completed_at'] : null,
                        'n8n_error' => "Errore invio richiesta rigenerazione a N8n: " . substr($exception->getMessage(), 0, 255),
                    ]);

                    $lockedPost->comments()->where('id', $this->commentId)->delete();
                }
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Marketing regeneration compensation failed (DB)', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
