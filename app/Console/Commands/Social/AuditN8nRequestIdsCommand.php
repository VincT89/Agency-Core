<?php

namespace App\Console\Commands\Social;

use Illuminate\Console\Command;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Support\Facades\DB;

class AuditN8nRequestIdsCommand extends Command
{
    protected $signature = 'social:audit-n8n-request-ids {--json : Output in JSON format}';
    protected $description = 'Audit raw_payload.request_id and n8n_request_id for consistency';

    public function handle()
    {
        $stats = [
            'total_versions' => 0,
            'versions_with_request_id_in_json' => 0,
            'versions_with_column_filled' => 0,
            'recoverable_but_not_migrated' => 0,
            'versions_without_request_id' => 0,
            'empty_or_non_string_request_ids' => 0,
            'duplicate_request_ids' => 0,
            'request_ids_associated_with_different_posts' => 0,
            'duplicate_external_generation_ids' => 0,
            'external_generation_ids_associated_with_different_posts' => 0,
        ];

        $requestIdsToPosts = [];
        $requestIdsCount = [];
        $externalIdsToPosts = [];
        $externalIdsCount = [];

        MarketingCampaignPostVersion::query()->chunk(500, function ($versions) use (
            &$stats, &$requestIdsToPosts, &$requestIdsCount, &$externalIdsToPosts, &$externalIdsCount
        ) {
            foreach ($versions as $version) {
                $stats['total_versions']++;
                $jsonRequestId = data_get($version->raw_payload, 'request_id');
                
                try {
                    $colRequestId = $version->n8n_request_id;
                } catch (\Throwable $e) {
                    $colRequestId = null;
                }
                
                try {
                    $extId = $version->external_generation_id;
                } catch (\Throwable $e) {
                    $extId = null;
                }
                
                $postId = $version->marketing_campaign_post_id;

                if ($jsonRequestId !== null) {
                    $stats['versions_with_request_id_in_json']++;
                    if (!is_string($jsonRequestId) || trim($jsonRequestId) === '') {
                        $stats['empty_or_non_string_request_ids']++;
                    } else {
                        $requestIdsCount[$jsonRequestId] = ($requestIdsCount[$jsonRequestId] ?? 0) + 1;
                        if (!isset($requestIdsToPosts[$jsonRequestId])) $requestIdsToPosts[$jsonRequestId] = [];
                        if (!in_array($postId, $requestIdsToPosts[$jsonRequestId])) $requestIdsToPosts[$jsonRequestId][] = $postId;
                    }
                } else {
                    $stats['versions_without_request_id']++;
                }

                if ($colRequestId !== null) {
                    $stats['versions_with_column_filled']++;
                }

                if ($jsonRequestId !== null && is_string($jsonRequestId) && trim($jsonRequestId) !== '' && $colRequestId === null) {
                    $stats['recoverable_but_not_migrated']++;
                }

                if ($extId !== null && is_string($extId) && trim($extId) !== '') {
                    $externalIdsCount[$extId] = ($externalIdsCount[$extId] ?? 0) + 1;
                    if (!isset($externalIdsToPosts[$extId])) $externalIdsToPosts[$extId] = [];
                    if (!in_array($postId, $externalIdsToPosts[$extId])) $externalIdsToPosts[$extId][] = $postId;
                }
            }
        });

        foreach ($requestIdsCount as $reqId => $count) {
            if ($count > 1) $stats['duplicate_request_ids'] += ($count - 1);
        }
        foreach ($requestIdsToPosts as $reqId => $posts) {
            if (count($posts) > 1) $stats['request_ids_associated_with_different_posts']++;
        }

        foreach ($externalIdsCount as $extId => $count) {
            if ($count > 1) $stats['duplicate_external_generation_ids'] += ($count - 1);
        }
        foreach ($externalIdsToPosts as $extId => $posts) {
            if (count($posts) > 1) $stats['external_generation_ids_associated_with_different_posts']++;
        }

        $hasConflicts = $stats['duplicate_request_ids'] > 0 || $stats['request_ids_associated_with_different_posts'] > 0 || $stats['duplicate_external_generation_ids'] > 0 || $stats['external_generation_ids_associated_with_different_posts'] > 0;

        if ($this->option('json')) {
            $this->output->writeln(json_encode($stats, JSON_PRETTY_PRINT));
            return $hasConflicts ? 1 : 0;
        }

        $this->info("=== Audit N8N Request IDs ===");
        $this->table(
            ['Metrica', 'Valore'],
            [
                ['Totale Versioni', $stats['total_versions']],
                ['Versioni con request_id nel JSON', $stats['versions_with_request_id_in_json']],
                ['Versioni con colonna n8n_request_id valorizzata', $stats['versions_with_column_filled']],
                ['Versioni recuperabili ma non migrate', $stats['recoverable_but_not_migrated']],
                ['Versioni senza request_id', $stats['versions_without_request_id']],
                ['Valori vuoti o non stringa in request_id', $stats['empty_or_non_string_request_ids']],
                ['Request ID duplicati', $stats['duplicate_request_ids']],
                ['Request ID associati a post differenti', $stats['request_ids_associated_with_different_posts']],
                ['External Generation ID duplicati', $stats['duplicate_external_generation_ids']],
                ['External Generation ID associati a post differenti', $stats['external_generation_ids_associated_with_different_posts']],
            ]
        );

        if ($hasConflicts) {
            $this->error("ATTENZIONE: Trovati duplicati o conflitti. Non e' sicuro applicare l'indice UNIQUE.");
            return 1;
        } else {
            $this->info("OK: Nessun conflitto rilevato sui request_id.");
            return 0;
        }
    }
}
