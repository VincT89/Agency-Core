<?php

namespace App\Jobs\Social;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MarketingCampaignPost;
use App\Domain\Social\Actions\PublishMarketingCampaignPostAction;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class PublishMarketingCampaignPostJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1m, 5m, 15m

    public function __construct(
        public MarketingCampaignPost $post,
        public string $platform,
        public ?string $correlationId = null
    ) {
        $this->onQueue('social-publishing');
        if (!$this->correlationId) {
            $this->correlationId = \Illuminate\Support\Str::uuid()->toString();
        }
    }

    public function uniqueId(): string
    {
        return $this->post->id . '-' . $this->platform;
    }

    public function middleware(): array
    {
        return [new \Illuminate\Queue\Middleware\RateLimited('meta-publishing')];
    }

    public function handle(PublishMarketingCampaignPostAction $action, \App\Services\SocialCircuitBreaker $circuitBreaker): void
    {
        if (!$circuitBreaker->isAvailable()) {
            $this->release(300); // 5 minuti di backoff se il circuito è aperto
            return;
        }

        try {
            // Correlation ID is propagated to action
            $publication = $action->execute($this->post, $this->platform, $this->correlationId);

            if ($publication->status === 'failed') {
                $circuitBreaker->recordFailure();
            } else {
                $circuitBreaker->recordSuccess();
            }
        } catch (\Exception $e) {
            $circuitBreaker->recordFailure();
            throw $e;
        }
    }
}
