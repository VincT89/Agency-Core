<?php

namespace App\Domain\Social\Publishing;

class PublishResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalPostId = null,
        public readonly ?string $externalContainerId = null,
        public readonly ?string $externalPermalink = null,
        public readonly ?string $errorMessage = null,
        public readonly ?array $responseSnapshot = null,
        public readonly bool $isProcessing = false
    ) {}

    public static function success(string $postId, ?string $permalink = null, ?array $response = null): self
    {
        return new self(true, $postId, null, $permalink, null, $response, false);
    }

    public static function processing(string $containerId, ?array $response = null): self
    {
        return new self(true, null, $containerId, null, null, $response, true);
    }

    public static function failure(string $errorMessage, ?array $response = null): self
    {
        return new self(false, null, null, null, $errorMessage, $response, false);
    }

    public function isProcessing(): bool
    {
        return $this->isProcessing;
    }

    public function isSuccess(): bool
    {
        return $this->success && !$this->isProcessing;
    }
}
