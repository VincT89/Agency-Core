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

    public function failed(Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('Marketing regeneration failed', [
            'error' => $e->getMessage(),
        ]);

        MarketingCampaignPost::query()
            ->whereKey($this->post->id)
            ->where('n8n_request_id', $this->payload['request_id'])
            ->where('status', \App\Enums\Social\MarketingCampaignPostStatus::Regenerating->value)
            ->update([
                'status' => $this->previousStatus,
                'n8n_error' => substr('Rigenerazione fallita dopo 3 tentativi: ' . $e->getMessage(), 0, 255),
            ]);
    }
}
