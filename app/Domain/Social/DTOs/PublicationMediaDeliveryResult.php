<?php

namespace App\Domain\Social\DTOs;

final readonly class PublicationMediaDeliveryResult
{
    public function __construct(
        public bool $passed,
        public ?string $url = null,
        public ?array $diagnosticPayload = null,
        public ?string $type = null,
        public array $errors = [],
        public array $mediaRequirements = []
    ) {}
}
