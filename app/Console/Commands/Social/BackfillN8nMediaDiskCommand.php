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
        $ignored = 0;

        MarketingCampaignPostMedia::query()
            ->where('source', 'n8n')
            ->whereNull('disk')
            ->chunkById(500, function ($mediaCollection) use (&$analyzed, &$updated, &$ignored, $dryRun) {
                foreach ($mediaCollection as $media) {
                    $analyzed++;

                    if ($media->path && Storage::disk('public')->exists($media->path)) {
                        if (!$dryRun) {
                            $media->update(['disk' => 'public']);
                        }
                        $updated++;
                    } else {
                        // File not found locally or path is empty
                        $ignored++;
                    }
                }
            });

        $this->info("Completed.");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Analyzed', $analyzed],
                ['Updated', $updated],
                ['Ignored (not found/ambiguous)', $ignored],
            ]
        );

        return 0;
    }
}
