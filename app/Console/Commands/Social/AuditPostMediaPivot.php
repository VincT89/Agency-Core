<?php

namespace App\Console\Commands\Social;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('social:audit-post-media-pivot')]
#[Description('Verifica che tutte le versioni abbiano media associati correttamente')]
class AuditPostMediaPivot extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Inizio Audit Media Pivot");

        $totalVersions = \App\Models\MarketingCampaignPostVersion::count();
        
        $versionsWithoutMediaButPostHasMedia = \App\Models\MarketingCampaignPostVersion::whereDoesntHave('mediaItems')
            ->whereHas('post', function ($q) {
                $q->has('mediaItems');
            })
            ->count();
            
        $versionsWithMedia = \App\Models\MarketingCampaignPostVersion::has('mediaItems')->count();
        $versionsWithoutAnyMedia = \App\Models\MarketingCampaignPostVersion::whereDoesntHave('mediaItems')
            ->whereHas('post', function ($q) {
                $q->doesntHave('mediaItems');
            })
            ->count();

        $this->table(
            ['Metrica', 'Valore'],
            [
                ['Totale Versioni', $totalVersions],
                ['Versioni con Media in Pivot', $versionsWithMedia],
                ['Versioni senza Media in Pivot MA con Media nel Post (DA BACKFILLARE)', $versionsWithoutMediaButPostHasMedia],
                ['Versioni senza alcun Media (Ok se post testuali)', $versionsWithoutAnyMedia],
            ]
        );

        if ($versionsWithoutMediaButPostHasMedia > 0) {
            $this->warn("Attenzione: Ci sono {$versionsWithoutMediaButPostHasMedia} versioni che necessitano del backfill.");
            $this->info("Esegui: php artisan social:backfill-post-media-pivot");
        } else {
            $this->info("Audit superato! Nessuna anomalia rilevata nei collegamenti media.");
        }
    }
}
