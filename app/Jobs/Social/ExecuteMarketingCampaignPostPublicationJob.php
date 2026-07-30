<?php

namespace App\Jobs\Social;

use App\Domain\Social\Actions\ExecuteMarketingCampaignPostPublicationAction;
use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use App\Enums\Social\PublicationFailureClassification;
use App\Enums\Social\PublicationStatus;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use App\Services\SocialCircuitBreaker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExecuteMarketingCampaignPostPublicationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public ?MarketingCampaignPost $post = null;

    public ?string $platform = null;

    public ?string $correlationId = null;

    public function __construct(
        public ?int $publicationId = null,
        ?MarketingCampaignPost $post = null,
        ?string $platform = null,
        ?string $correlationId = null
    ) {
        if ($publicationId === null && $post !== null) {
            $this->post = $post;
            $this->platform = $platform;
            $this->correlationId = $correlationId;
        }

        $this->onQueue('social-publishing');
    }

    public function uniqueId(): string
    {
        if ($this->publicationId) {
            return 'exec_pub_'.$this->publicationId;
        }

        return 'exec_pub_legacy_'.
            ($this->post?->id ?? 'unknown').
            '_'.
            ($this->platform ?? 'unknown');
    }

    public function middleware(): array
    {
        return [new RateLimited('social-publishing')];
    }

    public function handle(
        ExecuteMarketingCampaignPostPublicationAction $action,
        SocialCircuitBreaker $circuitBreaker
    ): void {
        if (! $this->publicationId) {
            if (! $this->post || ! $this->platform) {
                Log::warning(
                    'Job legacy di pubblicazione invocato senza i parametri richiesti.'
                );

                return;
            }

            $publication = MarketingCampaignPostPublication::query()
                ->where('marketing_campaign_post_id', $this->post->id)
                ->where('platform', $this->platform)
                ->whereIn('status', [
                    PublicationStatus::Pending->value,
                    PublicationStatus::Publishing->value,
                ])
                ->latest()
                ->first();

            if (! $publication) {
                Log::info('Il job legacy non ha trovato publication attive.', [
                    'post_id' => $this->post->id,
                    'platform' => $this->platform,
                ]);

                return;
            }

            $this->publicationId = $publication->id;
        }

        $publication = MarketingCampaignPostPublication::query()
            ->with('post.campaign')
            ->find($this->publicationId);

        if (! $publication) {
            Log::warning('Publication non trovata dal job di esecuzione.', [
                'publication_id' => $this->publicationId,
            ]);

            return;
        }

        $scopedCircuitBreaker = $circuitBreaker->scoped(
            $publication->platform->value,
            ($publication->post?->campaign?->client_id ?? 'unknown')
                .':'.$publication->client_social_account_id
        );

        if (! $scopedCircuitBreaker->isAvailable()) {
            $this->release(300);

            return;
        }

        try {
            $outcome = $action->execute($this->publicationId);

            if ($outcome->success) {
                $scopedCircuitBreaker->recordSuccess();
            } elseif (
                $outcome->classification ===
                PublicationFailureClassification::Temporary
            ) {
                $scopedCircuitBreaker->recordFailure();
            }
        } catch (\Throwable $e) {
            $scopedCircuitBreaker->recordFailure();

            Log::error('Errore infrastrutturale nel job di pubblicazione social', [
                'publication_id' => $this->publicationId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        try {
            $updated = DB::transaction(function () use ($exception) {
                $publication = MarketingCampaignPostPublication::whereKey(
                    $this->publicationId
                )
                    ->lockForUpdate()
                    ->first();

                if (! $publication || ! in_array(
                    $publication->status,
                    [PublicationStatus::Pending, PublicationStatus::Publishing],
                    true
                )) {
                    return false;
                }

                $ambiguous = $publication->status === PublicationStatus::Publishing;

                $publication->update([
                    'status' => $ambiguous
                        ? PublicationStatus::NeedsManualReview->value
                        : PublicationStatus::Failed->value,
                    'error_message' => 'Esecuzione fallita definitivamente: '.$exception->getMessage(),
                    'failure_classification' => $ambiguous
                        ? PublicationFailureClassification::ManualReview->value
                        : PublicationFailureClassification::Temporary->value,
                ]);

                return true;
            });

            if ($updated) {
                $publication = MarketingCampaignPostPublication::find(
                    $this->publicationId
                );

                if ($publication?->post) {
                    app(SyncMarketingCampaignPostPublicationStatusAction::class)
                        ->execute($publication->post);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Compensazione fallita nel job di pubblicazione social', [
                'publication_id' => $this->publicationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
