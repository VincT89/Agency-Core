<?php

namespace App\Domain\Social\DTO;

class SyncMetaAssetsResult
{
    public function __construct(
        public readonly int $totalFound = 0,
        public readonly int $newCreated = 0,
        public readonly int $updated = 0,
        public readonly int $revoked = 0,
        public readonly int $missingPermissions = 0,
        public readonly int $errors = 0,
        public readonly ?string $errorMessage = null
    ) {}

    public function isSuccessful(): bool
    {
        return $this->errors === 0 && $this->errorMessage === null;
    }
}
