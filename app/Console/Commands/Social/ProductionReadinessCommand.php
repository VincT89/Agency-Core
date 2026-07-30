<?php

namespace App\Console\Commands\Social;

use App\Domain\Social\Actions\ResolveFrozenPublicationTargetAction;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignStatus;
use App\Enums\Social\PublicationMode;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostPublication;
use App\Models\SystemHeartbeat;
use App\Support\Monitoring\TracksSystemCommandRuns;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductionReadinessCommand extends Command
{
    use TracksSystemCommandRuns;

    protected $signature = 'social:production-readiness
        {--allow-auto-disabled : Consente di verificare l’ambiente prima di attivare il kill switch}';

    protected $description = 'Verifica configurazione e stato operativo prima di attivare la pubblicazione automatica';

    public function handle(): int
    {
        return $this->runTracked($this->getName(), function (): int {
            $checks = [];

            $this->checkDatabase($checks);
            $this->checkApplicationUrl($checks);
            $this->checkQueue($checks);
            $this->checkPublishingMode($checks);
            $this->checkScheduler($checks);
            $this->checkStalePublications($checks);
            $this->checkAutomaticPosts($checks);

            $this->table(
                ['Controllo', 'Esito', 'Dettaglio'],
                array_map(
                    fn (array $check): array => [
                        $check['name'],
                        $check['passed'] ? 'OK' : 'ERRORE',
                        $check['detail'],
                    ],
                    $checks
                )
            );

            $failed = count(array_filter(
                $checks,
                fn (array $check): bool => ! $check['passed']
            ));

            if ($failed > 0) {
                $this->error(
                    "Prontezza alla produzione non confermata: {$failed} controlli non superati."
                );

                return self::FAILURE;
            }

            $this->info('Prontezza alla produzione confermata.');

            return self::SUCCESS;
        }, [
            'allow_auto_disabled' => (bool) $this->option(
                'allow-auto-disabled'
            ),
        ]);
    }

    private function checkDatabase(array &$checks): void
    {
        try {
            DB::select('SELECT 1');
            $this->addCheck(
                $checks,
                'Database',
                true,
                'Connessione disponibile.'
            );
        } catch (Throwable $exception) {
            $this->addCheck(
                $checks,
                'Database',
                false,
                'Connessione non disponibile: '.$exception->getMessage()
            );
        }
    }

    private function checkApplicationUrl(array &$checks): void
    {
        $url = (string) config('app.url');
        $isHttps = parse_url($url, PHP_URL_SCHEME) === 'https';

        $this->addCheck(
            $checks,
            'URL applicazione',
            $isHttps,
            $isHttps
                ? "URL pubblico HTTPS configurato: {$url}"
                : "APP_URL deve essere un URL HTTPS pubblico; valore attuale: {$url}"
        );
    }

    private function checkQueue(array &$checks): void
    {
        $connection = (string) config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");
        $asynchronous = is_string($driver)
            && ! in_array(
                $driver,
                ['sync', 'null', 'deferred', 'background'],
                true
            );
        $queueTableAvailable = true;

        if ($driver === 'database') {
            $table = (string) config(
                "queue.connections.{$connection}.table",
                'jobs'
            );
            $queueTableAvailable = Schema::hasTable($table);
        }

        $passed = $asynchronous && $queueTableAvailable;
        $detail = $passed
            ? "Connessione {$connection} con driver {$driver}."
            : "Serve una coda asincrona operativa; connessione={$connection}, driver="
                .($driver ?: 'non configurato')
                .($queueTableAvailable ? '.' : ', tabella jobs mancante.');

        $this->addCheck($checks, 'Coda asincrona', $passed, $detail);
    }

    private function checkPublishingMode(array &$checks): void
    {
        $dryRun = (bool) config('social.publishing.dry_run', false);
        $this->addCheck(
            $checks,
            'Modalità provider',
            ! $dryRun,
            $dryRun
                ? 'SOCIAL_PUBLISHING_DRY_RUN è attivo.'
                : 'Invio reale ai provider abilitato.'
        );

        $automaticEnabled = (bool) config(
            'social.auto_publish_enabled',
            false
        );
        $allowDisabled = (bool) $this->option('allow-auto-disabled');
        $this->addCheck(
            $checks,
            'Pubblicazione automatica',
            $automaticEnabled || $allowDisabled,
            $automaticEnabled
                ? 'SOCIAL_AUTO_PUBLISH_ENABLED è attivo.'
                : ($allowDisabled
                    ? 'Kill switch disattivato, ammesso esplicitamente dal comando.'
                    : 'SOCIAL_AUTO_PUBLISH_ENABLED è disattivato.')
        );
    }

    private function checkScheduler(array &$checks): void
    {
        $timeoutMinutes = max(
            1,
            (int) config('system-monitoring.scheduler_timeout_minutes', 5)
        );
        $heartbeat = SystemHeartbeat::query()
            ->where('name', 'scheduler')
            ->first();
        $fresh = $heartbeat?->last_seen_at?->gte(
            now()->subMinutes($timeoutMinutes)
        ) ?? false;

        $this->addCheck(
            $checks,
            'Scheduler',
            $fresh,
            ! $heartbeat
                ? 'Heartbeat scheduler mancante.'
                : ($fresh
                    ? 'Heartbeat aggiornato alle '
                        .$heartbeat->last_seen_at->toIso8601String().'.'
                    : "Heartbeat più vecchio di {$timeoutMinutes} minuti: "
                        .$heartbeat->last_seen_at->toIso8601String().'.')
        );
    }

    private function checkStalePublications(array &$checks): void
    {
        $pendingMinutes = max(
            1,
            (int) config(
                'social.production_readiness.pending_stale_minutes',
                15
            )
        );
        $publishingMinutes = max(
            1,
            (int) config(
                'social.production_readiness.publishing_without_deadline_minutes',
                30
            )
        );

        $stale = MarketingCampaignPostPublication::query()
            ->where(function ($query) use (
                $pendingMinutes,
                $publishingMinutes
            ): void {
                $query
                    ->where(function ($pending) use ($pendingMinutes): void {
                        $pending
                            ->where('status', PublicationStatus::Pending)
                            ->where(function ($stalePending) use (
                                $pendingMinutes
                            ): void {
                                $stalePending
                                    ->where(
                                        'created_at',
                                        '<',
                                        now()->subMinutes($pendingMinutes)
                                    )
                                    ->orWhere(
                                        'stale_deadline_at',
                                        '<',
                                        now()
                                    );
                            });
                    })
                    ->orWhere(function ($publishing) use (
                        $publishingMinutes
                    ): void {
                        $publishing
                            ->where('status', PublicationStatus::Publishing)
                            ->where(function ($stalePublishing) use (
                                $publishingMinutes
                            ): void {
                                $stalePublishing
                                    ->where(
                                        'stale_deadline_at',
                                        '<',
                                        now()
                                    )
                                    ->orWhere(function ($withoutDeadline) use (
                                        $publishingMinutes
                                    ): void {
                                        $withoutDeadline
                                            ->whereNull('stale_deadline_at')
                                            ->where(
                                                'publishing_started_at',
                                                '<',
                                                now()->subMinutes(
                                                    $publishingMinutes
                                                )
                                            );
                                    });
                            });
                    });
            })
            ->count();

        $this->addCheck(
            $checks,
            'Publication bloccate',
            $stale === 0,
            $stale === 0
                ? 'Nessuna publication pending o publishing oltre soglia.'
                : "{$stale} publication risultano bloccate oltre soglia."
        );
    }

    private function checkAutomaticPosts(array &$checks): void
    {
        $posts = MarketingCampaignPost::query()
            ->with(['campaign', 'currentVersion'])
            ->whereHas('campaign', function ($query): void {
                $query
                    ->where('publication_mode', PublicationMode::Automatic)
                    ->where('status', MarketingCampaignStatus::Active);
            })
            ->whereIn('status', [
                MarketingCampaignPostStatus::Approved,
                MarketingCampaignPostStatus::ClientApproved,
            ])
            ->orderBy('id')
            ->get()
            ->filter(function (MarketingCampaignPost $post): bool {
                if ($post->campaign->client_review_required) {
                    return $post->status
                        === MarketingCampaignPostStatus::ClientApproved;
                }

                return in_array($post->status, [
                    MarketingCampaignPostStatus::Approved,
                    MarketingCampaignPostStatus::ClientApproved,
                ], true);
            })
            ->values();

        $accounts = ClientSocialAccount::query()
            ->with('agencyAsset')
            ->whereIn(
                'client_id',
                $posts
                    ->map(fn (MarketingCampaignPost $post): int => $post->campaign->client_id)
                    ->unique()
            )
            ->get()
            ->groupBy(function (ClientSocialAccount $account): string {
                $platform = $account->platform instanceof SocialPlatform
                    ? $account->platform->value
                    : (string) $account->platform;

                return "{$account->client_id}|{$platform}";
            });

        $issues = [];
        $usedPlatforms = [];
        foreach ($posts as $post) {
            if (! $post->current_version_id || ! $post->currentVersion) {
                $issues[] = "post {$post->id}: versione corrente mancante";
            }

            if (! $post->scheduled_date || ! $post->scheduled_time) {
                $issues[] = "post {$post->id}: programmazione incompleta";
            }

            $rawPlatforms = is_array($post->publishing_platforms)
                ? $post->publishing_platforms
                : [];
            $platforms = collect($rawPlatforms)
                ->map(fn (mixed $value): ?SocialPlatform => is_string($value) ? SocialPlatform::tryFrom($value) : null)
                ->filter()
                ->unique(fn (SocialPlatform $platform): string => $platform->value)
                ->values();

            if (
                ! array_is_list($rawPlatforms)
                ||
                $platforms->isEmpty()
                || $platforms->count() !== count($rawPlatforms)
            ) {
                $issues[] = "post {$post->id}: piattaforme mancanti o non valide";
            }

            foreach ($platforms as $platform) {
                $usedPlatforms[$platform->value] = true;
                $key = "{$post->campaign->client_id}|{$platform->value}";
                $candidateAccounts = $accounts->get($key, collect());

                try {
                    $readyAccounts = $candidateAccounts
                        ->filter(
                            fn (ClientSocialAccount $account): bool => $account->isReadyToPublish()
                        );
                    $readyCount = $readyAccounts->count();
                } catch (Throwable $exception) {
                    $issues[] = "post {$post->id}, {$platform->value}: "
                        .'impossibile verificare gli account';

                    continue;
                }

                if ($readyCount !== 1) {
                    $issues[] = "post {$post->id}, {$platform->value}: "
                        ."account pronti={$readyCount}, atteso=1";

                    continue;
                }

                try {
                    app(
                        ResolveFrozenPublicationTargetAction::class
                    )->execute($platform, $readyAccounts->sole());
                } catch (Throwable) {
                    $issues[] = "post {$post->id}, {$platform->value}: "
                        .'target provider non risolvibile';
                }
            }
        }

        $this->checkProviderConfiguration($usedPlatforms, $issues);
        $this->checkNextcloudConfiguration($posts, $issues);

        $detail = $issues === []
            ? "Configurazione valida per {$posts->count()} post automatici autorizzati."
            : implode('; ', array_slice($issues, 0, 10))
                .(count($issues) > 10
                    ? '; altri '.(count($issues) - 10).' problemi'
                    : '');

        $this->addCheck(
            $checks,
            'Post automatici',
            $issues === [],
            $detail
        );
    }

    private function checkProviderConfiguration(
        array $usedPlatforms,
        array &$issues
    ): void {
        if (
            isset($usedPlatforms[SocialPlatform::Facebook->value])
            || isset($usedPlatforms[SocialPlatform::Instagram->value])
        ) {
            if (
                blank(config('services.meta.client_id'))
                || blank(config('services.meta.client_secret'))
            ) {
                $issues[] = 'credenziali applicazione Meta mancanti';
            }
        }

        if (isset($usedPlatforms[SocialPlatform::Tiktok->value])) {
            if (
                blank(config('services.tiktok.client_key'))
                || blank(config('services.tiktok.client_secret'))
            ) {
                $issues[] = 'credenziali applicazione TikTok mancanti';
            }

            $deliveryMode = (string) config(
                'services.tiktok.delivery_mode',
                'disabled'
            );
            if (! in_array($deliveryMode, ['draft', 'direct'], true)) {
                $issues[] = 'delivery mode TikTok deve essere draft o direct';
            }

            if ((bool) config('services.tiktok.mock_publishing', false)) {
                $issues[] = 'mock publishing TikTok ancora attivo';
            }

            if (
                (string) config(
                    'services.tiktok.upload_mode',
                    'PullFromUrl'
                ) !== 'PullFromUrl'
            ) {
                $issues[] = 'upload mode TikTok non supportata';
            }

            if (
                $deliveryMode === 'direct'
                && ! (bool) config(
                    'services.tiktok.direct_publish_enabled',
                    false
                )
            ) {
                $issues[] = 'direct publish TikTok non abilitato';
            }
        }
    }

    private function checkNextcloudConfiguration(
        Collection $posts,
        array &$issues
    ): void {
        $versionIds = $posts
            ->pluck('current_version_id')
            ->filter()
            ->unique()
            ->values();

        if ($versionIds->isEmpty()) {
            return;
        }

        $usesNextcloud = MarketingCampaignPostMedia::query()
            ->where('source', 'nextcloud')
            ->whereHas('versions', function ($query) use ($versionIds): void {
                $query->whereIn(
                    'marketing_campaign_post_versions.id',
                    $versionIds
                );
            })
            ->exists();

        if (! $usesNextcloud) {
            return;
        }

        foreach (['base_url', 'username', 'password'] as $key) {
            if (blank(config("services.nextcloud.{$key}"))) {
                $issues[] = "configurazione Nextcloud {$key} mancante";
            }
        }
    }

    private function addCheck(
        array &$checks,
        string $name,
        bool $passed,
        string $detail
    ): void {
        $checks[] = compact('name', 'passed', 'detail');
    }
}
