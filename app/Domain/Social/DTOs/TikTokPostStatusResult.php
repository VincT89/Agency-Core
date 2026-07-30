<?php

namespace App\Domain\Social\DTOs;

class TikTokPostStatusResult
{
    public function __construct(
        public readonly string $status,
        public readonly array $responseData,
        public readonly bool $isPermanentError = false,
        public readonly ?string $errorMessage = null,
        public readonly ?int $httpStatus = null,
        public readonly ?string $requestId = null,
        public readonly bool $isTemporaryError = false,
        public readonly bool $isAuthError = false,
        public readonly ?string $failReason = null,
    ) {}
}
