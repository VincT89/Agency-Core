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
        public bool $savedToClient = false
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

    public function failed(Throwable $e): void
    {
        try {
            $newStatus = $this->post->n8n_previous_status?->value ?? \App\Enums\Social\MarketingCampaignPostStatus::Draft->value;
            if (in_array($newStatus, [\App\Enums\Social\MarketingCampaignPostStatus::PendingN8n->value, \App\Enums\Social\MarketingCampaignPostStatus::SubmittedToN8n->value, \App\Enums\Social\MarketingCampaignPostStatus::Regenerating->value])) {
                $newStatus = \App\Enums\Social\MarketingCampaignPostStatus::Draft->value;
            }

            MarketingCampaignPost::query()
                ->whereKey($this->post->id)
                ->where('n8n_request_id', $this->payload['request_id'])
                ->whereIn('status', [
                    \App\Enums\Social\MarketingCampaignPostStatus::PendingN8n->value,
                    \App\Enums\Social\MarketingCampaignPostStatus::SubmittedToN8n->value
                ])
                ->update([
                    'status' => $newStatus,
                    'n8n_error' => substr($e->getMessage(), 0, 255)
                ]);
        } finally {
            if ($this->shouldDeleteTempFile()) {
                Storage::disk('public')->delete($this->temp_path);
                
                $currentPost = $this->post->fresh();
                if ($currentPost) {
                    $n8nInternalContext = $currentPost->n8n_internal_context ?? [];
                    if (isset($n8nInternalContext['_internal_temp_logo_path'])) {
                        unset($n8nInternalContext['_internal_temp_logo_path']);
                        $currentPost->update(['n8n_internal_context' => $n8nInternalContext]);
                    }
                }
            }
        }
    }

    private function shouldDeleteTempFile(): bool
    {
        return $this->temp_path && !$this->savedToClient;
    }
}
