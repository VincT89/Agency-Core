<?php

namespace App\Console\Commands;

use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use App\Models\MarketingCampaignPost;
use App\Support\Monitoring\TracksSystemCommandRuns;
use Illuminate\Console\Command;

class SyncPostPublicationStatusesCommand extends Command
{
    use TracksSystemCommandRuns;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'social:sync-post-publication-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizza lo stato di tutte le publication verso il relativo Post';

    /**
     * Execute the console command.
     */
    public function handle(SyncMarketingCampaignPostPublicationStatusAction $action)
    {
        return $this->runTracked($this->getName(), function () use ($action) {
            $this->info('Inizio sincronizzazione stati dei Post in base alle Publication...');

            $count = 0;
            MarketingCampaignPost::query()
                ->whereHas('publications')
                ->select(['id', 'status'])
                ->chunkById(200, function ($posts) use ($action, &$count) {
                    foreach ($posts as $post) {
                        $oldStatus = $post->status;
                        $action->execute($post);

                        // Re-fetch per vedere se è cambiato
                        $newStatus = $post->refresh()->status;

                        if ($oldStatus !== $newStatus) {
                            $this->line(
                                "Aggiornato Post ID {$post->id}: ".
                                "{$oldStatus->value} -> {$newStatus->value}"
                            );
                            $count++;
                        }
                    }
                }
                );

            $this->info("Sincronizzazione completata. Post aggiornati: {$count}.");

            return self::SUCCESS;
        });
    }
}
