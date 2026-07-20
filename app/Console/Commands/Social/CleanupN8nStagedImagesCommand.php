<?php

namespace App\Console\Commands\Social;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanupN8nStagedImagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'social:cleanup-n8n-staged-images {--threshold-hours=24 : Età in ore oltre la quale un file temporaneo viene rimosso} {--delete : Effettua la cancellazione reale, altrimenti esegue solo un dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleans up orphan temporary images from N8n staging';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $thresholdHours = (int) $this->option('threshold-hours');
        $thresholdDate = now()->subHours($thresholdHours);
        $isDeleteMode = $this->option('delete');

        $this->info("=== Cleanup Immagini N8N ===");
        if (!$isDeleteMode) {
            $this->warn("MODALITA' DRY-RUN. Usa --delete per cancellare realmente i file.");
        }
        $this->info("Soglia di invecchiamento: {$thresholdHours} ore ({$thresholdDate})");

        if (!Storage::disk('local')->exists('temp/n8n_images')) {
            $this->info("La directory temp/n8n_images non esiste. Nessuna azione necessaria.");
            return;
        }

        $files = Storage::disk('local')->files('temp/n8n_images');
        
        $deletedCount = 0;
        $deletedSize = 0;

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp(Storage::disk('local')->lastModified($file));

            if ($lastModified->lessThan($thresholdDate)) {
                $size = Storage::disk('local')->size($file);
                
                if ($isDeleteMode) {
                    Storage::disk('local')->delete($file);
                }

                $deletedCount++;
                $deletedSize += $size;
                
                if ($this->getOutput()->isVerbose()) {
                    $action = $isDeleteMode ? 'Eliminato' : '[Dry-Run] Eliminerebbe';
                    $this->line("{$action}: {$file} (" . round($size / 1024, 2) . " KB)");
                }
            }
        }

        $actionWord = $isDeleteMode ? 'eliminati' : 'da eliminare (dry-run)';
        $this->info("File temporanei {$actionWord}: {$deletedCount}");
        $this->info("Spazio recuperato: " . round($deletedSize / 1024 / 1024, 2) . " MB");
    }
}
