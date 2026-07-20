<?php

namespace App\Console\Commands\Social;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AuditN8nStagedImagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'social:audit-n8n-staged-images {--threshold-hours=24 : Età in ore oltre la quale un file temporaneo è considerato vecchio}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audits staged and promoted images from N8n';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $thresholdHours = (int) $this->option('threshold-hours');
        $thresholdDate = now()->subHours($thresholdHours);

        $this->info("=== Audit Immagini N8N ===");
        $this->info("Soglia di invecchiamento: {$thresholdHours} ore ({$thresholdDate})");

        $this->auditTempDir($thresholdDate);
        $this->auditPromotedDir();
    }

    private function auditTempDir(Carbon $thresholdDate): void
    {
        $this->info("\n--- Directory Temporanea (temp/n8n_images/) ---");

        if (!Storage::disk('local')->exists('temp/n8n_images')) {
            $this->warn("Directory non esiste.");
            return;
        }

        $files = Storage::disk('local')->files('temp/n8n_images');
        
        $totalFiles = count($files);
        $totalSize = 0;
        $oldFiles = 0;
        $partFiles = 0;
        $oldestTimestamp = null;

        foreach ($files as $file) {
            $size = Storage::disk('local')->size($file);
            $totalSize += $size;

            $lastModified = Carbon::createFromTimestamp(Storage::disk('local')->lastModified($file));

            if ($lastModified->lessThan($thresholdDate)) {
                $oldFiles++;
            }

            if (str_ends_with($file, '.part') || str_ends_with($file, '.tmp')) {
                $partFiles++;
            }

            if ($oldestTimestamp === null || $lastModified->timestamp < $oldestTimestamp) {
                $oldestTimestamp = $lastModified->timestamp;
            }
        }

        $this->table(
            ['Metrica', 'Valore'],
            [
                ['Numero di file', $totalFiles],
                ['Spazio totale', round($totalSize / 1024 / 1024, 2) . ' MB'],
                ['Età del più vecchio', $oldestTimestamp ? Carbon::createFromTimestamp($oldestTimestamp)->diffForHumans() : 'N/A'],
                ["File più vecchi di {$this->option('threshold-hours')}h", $oldFiles],
                ['File incompleti (.part/.tmp)', $partFiles],
            ]
        );
    }

    private function auditPromotedDir(): void
    {
        $this->info("\n--- Directory Definitiva (marketing_campaigns/posts/) ---");

        if (!Storage::disk('public')->exists('marketing_campaigns/posts')) {
            $this->warn("Directory non esiste.");
            return;
        }

        // Recuperiamo tutti i file ricorsivamente
        $files = Storage::disk('public')->allFiles('marketing_campaigns/posts');

        $totalFiles = count($files);
        $totalSize = 0;
        $oldestTimestamp = null;

        foreach ($files as $file) {
            $size = Storage::disk('public')->size($file);
            $totalSize += $size;

            $lastModified = Storage::disk('public')->lastModified($file);

            if ($oldestTimestamp === null || $lastModified < $oldestTimestamp) {
                $oldestTimestamp = $lastModified;
            }
        }

        $this->table(
            ['Metrica', 'Valore'],
            [
                ['Numero di file', $totalFiles],
                ['Spazio totale', round($totalSize / 1024 / 1024, 2) . ' MB'],
                ['Età del più vecchio', $oldestTimestamp ? Carbon::createFromTimestamp($oldestTimestamp)->diffForHumans() : 'N/A'],
            ]
        );
        
        $this->warn("I file definitivi non vengono analizzati per orfani in questa fase.");
    }
}
