<?php

namespace App\Console\Commands\Social;

use App\Models\MarketingCampaignPostMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillN8nMediaDiskCommand extends Command
{
    protected $signature = 'social:backfill-n8n-media-disk {--dry-run}';
    protected $description = 'Backfills the disk attribute for n8n generated media';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info("Starting backfill for n8n media disk. Dry run: " . ($dryRun ? 'YES' : 'NO'));

        $analyzed = 0;
        $updated = 0;
        $wouldUpdate = 0;
        $missing = 0;
        $ambiguous = 0;

        MarketingCampaignPostMedia::query()
            ->where('source', 'n8n')
            ->whereNull('disk')
            ->chunkById(500, function ($mediaCollection) use (&$analyzed, &$updated, &$wouldUpdate, &$missing, &$ambiguous, $dryRun) {
                foreach ($mediaCollection as $media) {
                    $analyzed++;

                    if (empty($media->path)) {
                        $ambiguous++;
                        continue;
                    }

                    if (Storage::disk('public')->exists($media->path)) {
                        if (!$dryRun) {
                            $media->update(['disk' => 'public']);
                            $updated++;
                        } else {
                            $wouldUpdate++;
                        }
                    } else {
                        // File not found locally
                        $missing++;
                    }
                }
            });

        $this->info("Completed.");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Analyzed', $analyzed],
                [$dryRun ? 'Would update' : 'Updated', $dryRun ? $wouldUpdate : $updated],
                ['Missing (file not on disk)', $missing],
                ['Ambiguous (null/empty path)', $ambiguous],
            ]
        );

        return 0;
    }
}
