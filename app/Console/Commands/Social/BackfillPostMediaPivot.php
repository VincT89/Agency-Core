<?php

namespace App\Console\Commands\Social;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

class BackfillPostMediaPivot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'social:backfill-post-media-pivot {--dry-run : Esegui senza salvare}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Esegue il backfill dei media sulle versioni legacy collegandoli tramite pivot';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info("Inizio Backfill Media Pivot " . ($dryRun ? '(DRY-RUN)' : ''));

        $versionsCount = 0;
        $mediaAttachedCount = 0;
        
        // Retrieve versions that don't have media attached but whose post has media
        $versions = \App\Models\MarketingCampaignPostVersion::whereDoesntHave('mediaItems')
            ->whereHas('post', function ($q) {
                $q->has('mediaItems'); // Use post's legacy media relation
            })
            ->with(['post.mediaItems'])
            ->get();

        $this->info("Trovate {$versions->count()} versioni da backfillare.");

        foreach ($versions as $version) {
            $postMediaItems = $version->post->mediaItems;
            
            if ($postMediaItems->isEmpty()) {
                continue;
            }

            if (!$dryRun) {
                // Attacca i media del post alla versione in pivot
                $version->mediaItems()->syncWithoutDetaching($postMediaItems->pluck('id')->toArray());
            }

            $versionsCount++;
            $mediaAttachedCount += $postMediaItems->count();
        }

        $this->info("Backfill completato.");
        $this->info("- Versioni aggiornate: {$versionsCount}");
        $this->info("- Media associati in pivot: {$mediaAttachedCount}");
    }
}
