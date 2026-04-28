<?php

namespace App\DTO\Social;

class PublicationResult
{
    public function __construct(
        public bool $success,
        public ?string $externalId = null,
        public ?string $permalink = null,
        public ?string $error = null,
        public array $raw = [],
    ) {}
}
