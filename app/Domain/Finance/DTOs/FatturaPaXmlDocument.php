<?php

namespace App\Domain\Finance\DTOs;

final readonly class FatturaPaXmlDocument
{
    public function __construct(
        public string $filename,
        public string $progressive,
        public string $content,
        public string $hash,
    ) {}
}
