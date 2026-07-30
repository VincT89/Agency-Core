<?php

namespace App\Console\Commands\Social;

use App\Domain\Social\Services\MediaIntegrityMetadataReader;
use App\Models\MarketingCampaignPostMedia;
use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackfillMediaIntegrityMetadata extends Command
{
    protected $signature = 'social:backfill-media-integrity
        {--apply : Salva esclusivamente metadati mancanti verificati}
        {--media-id=* : Limita il controllo a uno o più media}
        {--chunk=100 : Numero di media elaborati per blocco}';

    protected $description = 'Verifica e, con --apply, completa size, hash ed ETag dei media legacy';

    public function handle(
        MediaIntegrityMetadataReader $reader,
        NextcloudService $nextcloud
    ): int {
        $apply = (bool) $this->option('apply');
        $chunkSize = max(1, min(1000, (int) $this->option('chunk')));
        $counts = [
            'already_populated' => 0,
            'resolvable' => 0,
            'mismatch' => 0,
            'unresolvable' => 0,
            'applied' => 0,
        ];

        $this->info(
            $apply
                ? 'Backfill integrità media: modalità APPLY'
                : 'Backfill integrità media: modalità DRY-RUN. Nessuna modifica verrà salvata.'
        );

        $query = MarketingCampaignPostMedia::query()->orderBy('id');
        $ids = collect((array) $this->option('media-id'))
            ->filter(fn (mixed $value): bool => ctype_digit((string) $value) && (int) $value > 0)
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $query->chunkById($chunkSize, function ($mediaItems) use (
            $reader,
            $nextcloud,
            $apply,
            &$counts
        ): void {
            foreach ($mediaItems as $media) {
                try {
                    $candidate = $this->candidateMetadata($media, $reader, $nextcloud);
                    $mismatches = $this->mismatchedFields($media, $candidate);

                    if ($mismatches !== []) {
                        $counts['mismatch']++;
                        $this->warn(
                            "media={$media->id} mismatch=".implode(',', $mismatches)
                        );

                        continue;
                    }

                    $missing = $this->missingFields($media, array_keys($candidate));
                    if ($missing === []) {
                        $counts['already_populated']++;

                        continue;
                    }

                    $counts['resolvable']++;
                    $this->line(
                        "media={$media->id} resolvable fields=".implode(',', $missing)
                    );

                    if (! $apply) {
                        continue;
                    }

                    $updates = array_intersect_key($candidate, array_flip($missing));
                    $applied = DB::transaction(function () use ($media, $updates): bool {
                        $locked = MarketingCampaignPostMedia::query()
                            ->whereKey($media->id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        if (
                            $locked->source !== $media->source
                            || $locked->disk !== $media->disk
                            || $locked->path !== $media->path
                            || $locked->nextcloud_path !== $media->nextcloud_path
                        ) {
                            return false;
                        }

                        foreach ($updates as $field => $value) {
                            if ($locked->getAttribute($field) !== null) {
                                return false;
                            }
                        }

                        $locked->update($updates);

                        return true;
                    });

                    if ($applied) {
                        $counts['applied']++;
                    } else {
                        $counts['unresolvable']++;
                        $this->warn("media={$media->id} changed_during_backfill");
                    }
                } catch (Throwable $exception) {
                    $counts['unresolvable']++;
                    $this->warn(
                        "media={$media->id} unresolvable={$exception->getMessage()}"
                    );
                }
            }
        });

        $this->table(
            ['Esito', 'Media'],
            collect($counts)
                ->map(fn (int $count, string $outcome): array => [$outcome, $count])
                ->values()
                ->all()
        );

        if ($counts['mismatch'] > 0 || $counts['unresolvable'] > 0) {
            $this->error('Sono presenti media che richiedono verifica manuale.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, int|string|null>
     */
    private function candidateMetadata(
        MarketingCampaignPostMedia $media,
        MediaIntegrityMetadataReader $reader,
        NextcloudService $nextcloud
    ): array {
        if ($media->source === 'nextcloud') {
            if (! filled($media->nextcloud_path)) {
                throw new \RuntimeException('Missing Nextcloud path.');
            }

            $info = $nextcloud->getFileInfo($media->nextcloud_path);

            return [
                'source_size_bytes' => $info->sizeBytes,
                'mime_type' => $info->mimeType,
                'nextcloud_file_id' => $info->fileId,
                'nextcloud_etag' => $info->etag,
            ];
        }

        if (! filled($media->disk) || ! filled($media->path)) {
            throw new \RuntimeException('Missing local disk or path.');
        }

        return $reader->readLocal($media->disk, $media->path);
    }

    /**
     * @param  array<string, int|string|null>  $candidate
     * @return list<string>
     */
    private function mismatchedFields(
        MarketingCampaignPostMedia $media,
        array $candidate
    ): array {
        $mismatches = [];

        foreach ($candidate as $field => $value) {
            $existing = $media->getAttribute($field);
            if ($existing === null || $value === null) {
                continue;
            }

            if ((string) $existing !== (string) $value) {
                $mismatches[] = $field;
            }
        }

        return $mismatches;
    }

    /**
     * @param  list<string>  $fields
     * @return list<string>
     */
    private function missingFields(
        MarketingCampaignPostMedia $media,
        array $fields
    ): array {
        return array_values(array_filter(
            $fields,
            fn (string $field): bool => $media->getAttribute($field) === null
        ));
    }
}
