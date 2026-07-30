<?php

namespace App\Domain\Social\DTOs;

final readonly class PublicationIntegrityResult
{
    public function __construct(
        public bool $passed,
        public array $errors = [],
        public \App\Enums\Social\IntegritySeverity $severity = \App\Enums\Social\IntegritySeverity::Error,
        public bool $retryable = false,
    ) {}
}
