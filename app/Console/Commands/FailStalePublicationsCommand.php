<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketingCampaignPostPublication;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use Illuminate\Support\Facades\DB;
use App\Support\Monitoring\TracksSystemCommandRuns;

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
            $this->info("Inizio scansione delle pubblicazioni stale...");

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

                            if (!$lockedPub) {
                                return; // Già risolta
                            }

                            $oldStatus = $lockedPub->status->value;

                            // Facebook è sincrono, se è bloccato da 5 minuti è fallito e basta.
                            // TikTok e Instagram (container) sono asincroni, un blocco potrebbe significare timeout 
                            // dell'API senza risposta d'errore, o worker morto.
                            $newStatus = PublicationStatus::Failed;
                            $errorMessage = "Timeout: elaborazione bloccata o interrotta oltre il limite previsto ({$lockedPub->stale_deadline_at}).";

                            if ($lockedPub->platform === SocialPlatform::Facebook) {
                                $newStatus = PublicationStatus::Failed;
                            }

                            $lockedPub->update([
                                'status' => $newStatus,
                                'error_message' => $errorMessage
                            ]);

                            app(\App\Domain\Social\Services\SocialPublicationTelemetry::class)
                                ->record(
                                    $lockedPub,
                                    'publication.stale_intercepted',
                                    $oldStatus
                                );

                            // Sincronizza lo stato master del Post
                            app(\App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction::class)->execute($lockedPub->post);

                            $processedCount++;
                        });
                    }
                });

            $this->info("Scansione completata. Pubblicazioni stale recuperate: {$processedCount}");
            
            return self::SUCCESS;
        });
    }
}
