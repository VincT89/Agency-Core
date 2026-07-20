<?php

namespace App\Console\Commands\Social;

use Illuminate\Console\Command;
use App\Models\MarketingCampaignPostVersion;

class BackfillN8nRequestIdsCommand extends Command
{
    protected $signature = 'social:backfill-n8n-request-ids {--dry-run : Esegui senza salvare}';
    protected $description = 'Backfill n8n_request_id da raw_payload->request_id';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info("Modalita DRY-RUN: Nessun dato verra modificato.");
        }

        $stats = [
            'processed' => 0,
            'updated' => 0,
            'skipped_already_filled' => 0,
            'skipped_no_request_id_in_json' => 0,
            'skipped_invalid_request_id' => 0,
        ];

        MarketingCampaignPostVersion::query()
            ->whereNull('n8n_request_id')
            ->whereNotNull('raw_payload')
            ->orderBy('id')
            ->chunk(500, function ($versions) use (&$stats, $dryRun) {
                foreach ($versions as $version) {
                    $stats['processed']++;

                    if ($version->n8n_request_id !== null) {
                        $stats['skipped_already_filled']++;
                        continue;
                    }

                    $jsonRequestId = data_get($version->raw_payload, 'request_id');

                    if ($jsonRequestId === null) {
                        $stats['skipped_no_request_id_in_json']++;
                        continue;
                    }

                    if (!is_string($jsonRequestId) || trim($jsonRequestId) === '') {
                        $stats['skipped_invalid_request_id']++;
                        continue;
                    }

                    $stats['updated']++;

                    if (!$dryRun) {
                        $version->n8n_request_id = $jsonRequestId;
                        // Avoid triggering generic saved events if not needed, but saving normally is fine
                        // to avoid touching timestamps unnecessarily, we can use update() or saveQuietly()
                        $version->timestamps = false;
                        $version->saveQuietly();
                    }
                }
            });

        $this->info("=== Risultati Backfill ===");
        $this->table(
            ['Metrica', 'Valore'],
            [
                ['Record processati', $stats['processed']],
                ['Record aggiornati', $stats['updated']],
                ['Saltati (gia valorizzati)', $stats['skipped_already_filled']],
                ['Saltati (nessun request_id nel JSON)', $stats['skipped_no_request_id_in_json']],
                ['Saltati (request_id non valido)', $stats['skipped_invalid_request_id']],
            ]
        );

        return 0;
    }
}
