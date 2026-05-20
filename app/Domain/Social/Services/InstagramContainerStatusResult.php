<?php

namespace App\Domain\Social\Services;

class InstagramContainerStatusResult
{
    public function __construct(
        public readonly string $status, // 'FINISHED', 'ERROR', 'EXPIRED', 'IN_PROGRESS', 'UNKNOWN'
        public readonly bool $isPermanentError,
        public readonly ?string $errorMessage,
        public readonly ?array $responseData,
        public readonly ?string $externalPostId = null,
        public readonly ?array $publishResponse = null,
    ) {}

    public function isFinished(): bool
    {
        return $this->status === 'FINISHED';
    }

    public function isError(): bool
    {
        return in_array($this->status, ['ERROR', 'EXPIRED']) || $this->isPermanentError;
    }

    public function isTemporary(): bool
    {
        return !$this->isError() && $this->status !== 'FINISHED';
    }
}
