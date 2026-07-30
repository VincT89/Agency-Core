<?php

namespace App\Console\Commands\Social;

use App\Domain\Social\Actions\CreateMarketingCampaignPostPublicationAction;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignStatus;
use App\Enums\Social\PublicationMode;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use App\Jobs\Social\ExecuteMarketingCampaignPostPublicationJob;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Support\Monitoring\TracksSystemCommandRuns;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchDuePublicationsCommand extends Command
{
    use TracksSystemCommandRuns;

    protected $signature = 'social:dispatch-due-publications';

    protected $description = 'Crea e accoda le publication automatiche giunte alla data e ora programmate';

    public function handle(
        CreateMarketingCampaignPostPublicationAction $createPublication
    ): int {
        return $this->runTracked($this->getName(), function () use (
            $createPublication
        ): int {
            if (! (bool) config('social.auto_publish_enabled', false)) {
                $this->warn('Pubblicazione automatica disabilitata dal kill switch.');

                return self::SUCCESS;
            }

            $now = CarbonImmutable::now(config('app.timezone'));
            $batchSize = max(
                1,
                min(1000, (int) config('social.auto_dispatch_batch_size', 100))
            );
            $posts = MarketingCampaignPost::query()
                ->with(['campaign.client', 'currentVersion'])
                ->whereHas('campaign', function ($query): void {
                    $query->where('publication_mode', PublicationMode::Automatic)
                        ->where('status', MarketingCampaignStatus::Active);
                })
                ->where(function ($query): void {
                    $query
                        ->where(
                            'status',
                            MarketingCampaignPostStatus::ClientApproved
                        )
                        ->orWhere(function ($approved): void {
                            $approved
                                ->where(
                                    'status',
                                    MarketingCampaignPostStatus::Approved
                                )
                                ->whereHas('campaign', function ($campaign): void {
                                    $campaign->where(
                                        'client_review_required',
                                        false
                                    );
                                });
                        });
                })
                ->whereNotNull('current_version_id')
                ->whereNotNull('scheduled_date')
                ->whereNotNull('scheduled_time')
                ->where(function ($query) use ($now): void {
                    $query->whereDate('scheduled_date', '<', $now->toDateString())
                        ->orWhere(function ($sameDay) use ($now): void {
                            $sameDay->whereDate(
                                'scheduled_date',
                                '=',
                                $now->toDateString()
                            )->whereTime(
                                'scheduled_time',
                                '<=',
                                $now->format('H:i:s')
                            );
                        });
                })
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            $dispatched = 0;
            $skipped = 0;
            $failed = 0;

            foreach ($posts as $post) {
                $lock = Cache::lock("social_auto_dispatch_post_{$post->id}", 55);
                if (! $lock->get()) {
                    $skipped++;

                    continue;
                }

                try {
                    $dispatched += $this->dispatchPost(
                        $post,
                        $createPublication,
                        $now
                    );
                } catch (Throwable $exception) {
                    $failed++;
                    Log::error('social.automation.dispatch_failed', [
                        'post_id' => $post->id,
                        'event' => 'automatic_dispatch_failed',
                        'error' => $exception->getMessage(),
                    ]);
                    $this->warn(
                        "post={$post->id} non accodato: {$exception->getMessage()}"
                    );
                } finally {
                    $lock->release();
                }
            }

            $this->info(
                "Publication accodate: {$dispatched}; post saltati: {$skipped}; errori: {$failed}."
            );

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        });
    }

    private function dispatchPost(
        MarketingCampaignPost $post,
        CreateMarketingCampaignPostPublicationAction $createPublication,
        CarbonImmutable $now
    ): int {
        $post = MarketingCampaignPost::query()
            ->with(['campaign.client', 'currentVersion'])
            ->findOrFail($post->id);

        if (
            $post->campaign->publication_mode !== PublicationMode::Automatic
            || $post->campaign->status !== MarketingCampaignStatus::Active
            || ! $this->isAuthorizedStatus($post)
            || ! $this->isDue($post, $now)
        ) {
            return 0;
        }

        $rawPlatforms = $post->publishing_platforms;
        if (
            ! is_array($rawPlatforms)
            || ! array_is_list($rawPlatforms)
            || $rawPlatforms === []
        ) {
            throw new \RuntimeException(
                'Nessuna piattaforma valida configurata.'
            );
        }

        $platforms = collect($rawPlatforms)
            ->map(fn (mixed $value): ?SocialPlatform => is_string($value)
                ? SocialPlatform::tryFrom($value)
                : null)
            ->filter()
            ->unique(fn (SocialPlatform $platform): string => $platform->value)
            ->values();

        if ($platforms->count() !== count($rawPlatforms)) {
            throw new \RuntimeException(
                'La configurazione contiene piattaforme non valide o duplicate.'
            );
        }

        $targets = [];
        foreach ($platforms as $platform) {
            $readyAccounts = ClientSocialAccount::query()
                ->with('agencyAsset')
                ->where('client_id', $post->campaign->client_id)
                ->where('platform', $platform)
                ->orderBy('id')
                ->get()
                ->filter(
                    fn (ClientSocialAccount $account): bool => $account->isReadyToPublish()
                )
                ->values();

            if ($readyAccounts->count() !== 1) {
                throw new \RuntimeException(
                    "La piattaforma {$platform->value} richiede esattamente un account pronto; trovati {$readyAccounts->count()}."
                );
            }

            $targets[] = [$platform, $readyAccounts->first()];
        }

        $publications = [];
        foreach ($targets as [$platform, $account]) {
            $publications[] = $createPublication->execute(
                $post,
                $post->currentVersion,
                $platform,
                $account
            );
        }

        $dispatchCount = 0;
        foreach ($publications as $publication) {
            if (
                $publication->status !== PublicationStatus::Pending
                || $publication->publishing_started_at !== null
            ) {
                continue;
            }

            $queuedMarker = "social_auto_dispatch_publication_{$publication->id}";
            if (! Cache::add($queuedMarker, true, now()->addHour())) {
                continue;
            }

            try {
                ExecuteMarketingCampaignPostPublicationJob::dispatch(
                    $publication->id
                );
                $dispatchCount++;
            } catch (Throwable $exception) {
                Cache::forget($queuedMarker);

                throw $exception;
            }
        }

        return $dispatchCount;
    }

    private function isAuthorizedStatus(MarketingCampaignPost $post): bool
    {
        if ($post->campaign->client_review_required) {
            return $post->status === MarketingCampaignPostStatus::ClientApproved;
        }

        return in_array($post->status, [
            MarketingCampaignPostStatus::Approved,
            MarketingCampaignPostStatus::ClientApproved,
        ], true);
    }

    private function isDue(
        MarketingCampaignPost $post,
        CarbonImmutable $now
    ): bool {
        if (! $post->scheduled_date || ! $post->scheduled_time) {
            return false;
        }

        $date = $post->scheduled_date->toDateString();
        $time = is_string($post->scheduled_time)
            ? $post->scheduled_time
            : $post->scheduled_time->format('H:i:s');
        $scheduledAt = CarbonImmutable::parse(
            "{$date} {$time}",
            config('app.timezone')
        );

        return $scheduledAt->lessThanOrEqualTo($now);
    }
}
