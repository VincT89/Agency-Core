<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketingCampaignPostMedia;

class BackfillMediaDiskCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:backfill-disk';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill del campo disk per i media legacy che hanno source locale e disk nullo.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Inizio backfill dei media con disk mancante...');

        $mediaList = MarketingCampaignPostMedia::whereNull('disk')
            ->where('source', 'local')
            ->get();

        $count = $mediaList->count();

        if ($count === 0) {
            $this->info('Nessun media trovato da aggiornare.');
            return Command::SUCCESS;
        }

        $this->withProgressBar($mediaList, function ($media) {
            $media->disk = 'public';
            $media->save();
        });

        $this->newLine();
        $this->info("Completato! Sono stati aggiornati $count record.");

        return Command::SUCCESS;
    }
}
