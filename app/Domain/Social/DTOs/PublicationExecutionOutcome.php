<?php

namespace App\Domain\Social\DTOs;

use App\Enums\Social\PublicationFailureClassification;

class PublicationExecutionOutcome
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $errorMessage = null,
        public readonly ?PublicationFailureClassification $classification = null,
        public readonly ?array $providerResponse = null
    ) {}

    public static function success(?array $providerResponse = null): self
    {
        return new self(true, null, null, $providerResponse);
    }

    public static function failure(
        string $message,
        PublicationFailureClassification $classification = PublicationFailureClassification::Temporary,
        ?array $providerResponse = null
    ): self {
        return new self(false, $message, $classification, $providerResponse);
    }
}
