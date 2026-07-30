<?php

namespace App\Domain\Social\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaIntegrityMetadataReader
{
    /**
     * @return array{source_size_bytes: int, sha256: string, mime_type: string}
     */
    public function readLocal(string $disk, string $path): array
    {
        $filesystem = Storage::disk($disk);

        if (! $filesystem->exists($path)) {
            throw new RuntimeException("Media file does not exist on disk {$disk}: {$path}");
        }

        $absolutePath = $filesystem->path($path);
        $size = $filesystem->size($path);
        $sha256 = hash_file('sha256', $absolutePath);
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath);

        if ($size <= 0 || $sha256 === false || ! is_string($mimeType) || $mimeType === '') {
            throw new RuntimeException("Unable to calculate complete integrity metadata for {$path}");
        }

        return [
            'source_size_bytes' => $size,
            'sha256' => $sha256,
            'mime_type' => $mimeType,
        ];
    }
}
