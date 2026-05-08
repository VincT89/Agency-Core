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

    public function handle(N8nClient $client): void
    {
        $client->requestMarketingCampaignPostRegeneration($this->payload);

        $this->post->forceFill([
            'submitted_to_n8n_at' => now(),
        ])->saveQuietly();
    }

    public function failed(Throwable $e): void
    {
        $this->post->refresh();

        $this->post->update([
            'status' => $this->previousStatus,
            'n8n_error' => 'Rigenerazione fallita dopo 3 tentativi: ' . $e->getMessage(),
        ]);
    }
}
