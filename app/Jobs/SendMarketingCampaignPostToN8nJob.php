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

    public function handle(N8nClient $client): void
    {
        $client->submitMarketingCampaignPost($this->payload);

        $this->post->update([
            'status' => \App\Enums\Social\MarketingCampaignPostStatus::SubmittedToN8n->value,
            'submitted_to_n8n_at' => now(),
        ]);

    }

    public function failed(Throwable $e): void
    {
        $this->post->update([
            'status' => \App\Enums\Social\MarketingCampaignPostStatus::Draft->value,
        ]);

        if ($this->shouldDeleteTempFile()) {
            Storage::disk('public')->delete($this->temp_path);
        }
    }

    private function shouldDeleteTempFile(): bool
    {
        return $this->temp_path && !$this->savedToClient;
    }
}
