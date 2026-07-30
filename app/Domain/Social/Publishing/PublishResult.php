<?php

namespace App\Domain\Social\Publishing;

use App\Enums\Social\PublicationFailureClassification;

class PublishResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalPostId = null,
        public readonly ?string $externalContainerId = null,
        public readonly ?string $externalPermalink = null,
        public readonly ?string $errorMessage = null,
        public readonly ?array $responseSnapshot = null,
        public readonly bool $isProcessing = false,
        public readonly ?string $externalTaskId = null,
        public readonly ?array $providerStatePayload = null,
        public readonly ?PublicationFailureClassification $failureClassification = null
    ) {}

    public static function success(string $postId, ?string $permalink = null, ?array $response = null): self
    {
        return new self(true, $postId, null, $permalink, null, $response, false);
    }

    public static function processing(
        ?string $externalContainerId = null,
        ?array $response = null,
        ?string $externalTaskId = null,
        ?array $providerStatePayload = null
    ): self {
        return new self(true, null, $externalContainerId, null, null, $response, true, $externalTaskId, $providerStatePayload);
    }

    public static function processingTask(string $taskId, ?array $response = null, ?array $providerStatePayload = null): self
    {
        return new self(true, null, null, null, null, $response, true, $taskId, $providerStatePayload);
    }

    public static function failure(string $errorMessage, PublicationFailureClassification $classification, ?array $response = null): self
    {
        return new self(false, null, null, null, $errorMessage, $response, false, null, null, $classification);
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
