<?php

namespace App\Domain\Social\DTOs;

final readonly class PreflightResult
{
    public function __construct(
        public bool $isPass,
        public array $errors = []
    ) {}
}
