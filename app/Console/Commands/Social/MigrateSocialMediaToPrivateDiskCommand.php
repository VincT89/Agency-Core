<?php

namespace App\Console\Commands\Social;

use App\Domain\Social\Services\MediaIntegrityMetadataReader;
use App\Models\MarketingCampaignPostMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateSocialMediaToPrivateDiskCommand extends Command
{
    protected $signature = 'social:migrate-media-to-private
        {--execute : Copy, verify, update records, and remove public originals}';

    protected $description = 'Migrate local social media from the public disk to private storage';

    public function handle(MediaIntegrityMetadataReader $metadataReader): int
    {
        $query = MarketingCampaignPostMedia::query()
            ->where('disk', 'public')
            ->whereIn('source', ['local', 'n8n'])
            ->whereNotNull('path')
            ->where('path', '!=', '');

        $recordCount = (clone $query)->count();
        $pathCount = (clone $query)->distinct()->count('path');

        if (! $this->option('execute')) {
            $this->info(
                "Dry run: {$recordCount} media records across {$pathCount} files "
                .'would be migrated. Re-run with --execute to apply.'
            );

            return self::SUCCESS;
        }

        $migratedRecords = 0;
        $migratedFiles = 0;
        $failures = 0;

        foreach (
            (clone $query)
                ->select('path')
                ->distinct()
                ->orderBy('path')
                ->cursor() as $candidate
        ) {
            $path = $candidate->path;

            try {
                $source = Storage::disk('public');
                $target = Storage::disk('social_media');

                if (! $target->exists($path)) {
                    if (! $source->exists($path)) {
                        throw new \RuntimeException(
                            "Source file is missing: {$path}"
                        );
                    }

                    $stream = $source->readStream($path);
                    if (! is_resource($stream)) {
                        throw new \RuntimeException(
                            "Unable to read source file: {$path}"
                        );
                    }

                    try {
                        if (! $target->writeStream($path, $stream)) {
                            throw new \RuntimeException(
                                "Unable to write private file: {$path}"
                            );
                        }
                    } finally {
                        fclose($stream);
                    }
                }

                $targetMetadata = $metadataReader->readLocal(
                    'social_media',
                    $path
                );
                $reference = MarketingCampaignPostMedia::query()
                    ->where('disk', 'public')
                    ->whereIn('source', ['local', 'n8n'])
                    ->where('path', $path)
                    ->firstOrFail();

                if ($source->exists($path)) {
                    $sourceMetadata = $metadataReader->readLocal('public', $path);

                    if (! hash_equals(
                        $sourceMetadata['sha256'],
                        $targetMetadata['sha256']
                    ) || $sourceMetadata['source_size_bytes']
                        !== $targetMetadata['source_size_bytes']) {
                        throw new \RuntimeException(
                            "Private copy verification failed: {$path}"
                        );
                    }
                } elseif (filled($reference->sha256)
                    && ! hash_equals(
                        strtolower((string) $reference->sha256),
                        $targetMetadata['sha256']
                    )) {
                    throw new \RuntimeException(
                        "Existing private copy does not match metadata: {$path}"
                    );
                }

                if ($source->exists($path) && ! $source->delete($path)) {
                    throw new \RuntimeException(
                        "Public source could not be removed: {$path}"
                    );
                }

                $updated = DB::transaction(
                    fn (): int => MarketingCampaignPostMedia::query()
                        ->where('disk', 'public')
                        ->whereIn('source', ['local', 'n8n'])
                        ->where('path', $path)
                        ->update([
                            'disk' => 'social_media',
                            'source_size_bytes' => $targetMetadata['source_size_bytes'],
                            'sha256' => $targetMetadata['sha256'],
                            'mime_type' => $targetMetadata['mime_type'],
                            'updated_at' => now(),
                        ])
                );

                $migratedRecords += $updated;
                $migratedFiles++;
            } catch (Throwable $exception) {
                $failures++;
                $this->error($exception->getMessage());
            }
        }

        $this->info(
            "Migrated {$migratedRecords} media records and {$migratedFiles} files. "
            ."Failures: {$failures}."
        );

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
