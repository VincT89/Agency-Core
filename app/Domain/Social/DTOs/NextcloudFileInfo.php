<?php

namespace App\Domain\Social\DTOs;

class NextcloudFileInfo
{
    public function __construct(
        public readonly string $path,
        public readonly string $fileId,
        public readonly string $etag,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
    ) {
        if (trim($this->path) === '') throw new \InvalidArgumentException('Path cannot be empty');
        if (trim($this->fileId) === '') throw new \InvalidArgumentException('File ID cannot be empty');
        if (trim($this->etag) === '') throw new \InvalidArgumentException('ETag cannot be empty');
        if (trim($this->mimeType) === '') throw new \InvalidArgumentException('MIME type cannot be empty');
        if ($this->sizeBytes <= 0) throw new \InvalidArgumentException('Size must be positive');
    }
}
