<?php

namespace App\Console\Commands;

use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use App\Domain\Social\Services\SocialPublicationTelemetry;
use App\Enums\Social\PublicationFailureClassification;
use App\Enums\Social\PublicationStatus;
use App\Models\MarketingCampaignPostPublication;
use App\Support\Monitoring\TracksSystemCommandRuns;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FailStalePublicationsCommand extends Command
{
    use TracksSystemCommandRuns;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'social:fail-stale-publications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trova e abortisce le pubblicazioni rimaste incastrate in coda oltre la loro scadenza stale.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        return $this->runTracked($this->getName(), function () {
            $this->info('Inizio scansione delle pubblicazioni stale...');

            $processedCount = 0;

            MarketingCampaignPostPublication::whereIn('status', [PublicationStatus::Pending, PublicationStatus::Publishing])
                ->whereNotNull('stale_deadline_at')
                ->where('stale_deadline_at', '<', now())
                ->chunkById(50, function ($publications) use (&$processedCount) {
                    foreach ($publications as $pub) {
                        DB::transaction(function () use ($pub, &$processedCount) {
                            // Rileggiamo con lock per evitare race condition con i worker
                            $lockedPub = MarketingCampaignPostPublication::where('id', $pub->id)
                                ->whereIn('status', [PublicationStatus::Pending, PublicationStatus::Publishing])
                                ->lockForUpdate()
                                ->first();

                            if (! $lockedPub) {
                                return; // Già risolta
                            }

                            $oldStatus = $lockedPub->status->value;

                            $errorMessage = "Timeout: elaborazione bloccata o interrotta oltre il limite previsto ({$lockedPub->stale_deadline_at}).";
                            $wasDispatchedToProvider = $lockedPub->status ===
                                PublicationStatus::Publishing;
                            $newStatus = $wasDispatchedToProvider
                                ? PublicationStatus::NeedsManualReview
                                : PublicationStatus::Failed;

                            $lockedPub->update([
                                'status' => $newStatus,
                                'error_message' => $errorMessage,
                                'failure_classification' => $wasDispatchedToProvider
                                    ? PublicationFailureClassification::ManualReview->value
                                    : PublicationFailureClassification::Temporary->value,
                            ]);

                            app(SocialPublicationTelemetry::class)
                                ->record(
                                    $lockedPub,
                                    'publication.stale_intercepted',
                                    $oldStatus
                                );

                            // Sincronizza lo stato master del Post
                            app(SyncMarketingCampaignPostPublicationStatusAction::class)->execute($lockedPub->post);

                            $processedCount++;
                        });
                    }
                });

            $this->info("Scansione completata. Pubblicazioni stale recuperate: {$processedCount}");

            return self::SUCCESS;
        });
    }
}
