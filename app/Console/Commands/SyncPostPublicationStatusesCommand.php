<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketingCampaignPost;
use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;

class SyncPostPublicationStatusesCommand extends Command
{
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
        $this->info('Inizio sincronizzazione stati dei Post in base alle Publication...');

        $posts = MarketingCampaignPost::whereHas('publications')->get();
        
        $count = 0;
        foreach ($posts as $post) {
            $oldStatus = $post->status;
            $action->execute($post);
            
            // Re-fetch per vedere se è cambiato
            $newStatus = $post->refresh()->status;
            
            if ($oldStatus !== $newStatus) {
                $this->line("Aggiornato Post ID {$post->id}: {$oldStatus->value} -> {$newStatus->value}");
                $count++;
            }
        }

        $this->info("Sincronizzazione completata. Post aggiornati: {$count}.");
    }
}
