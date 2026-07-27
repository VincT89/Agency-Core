<?php

namespace App\Services\Integrations\Nextcloud\DTO;

final readonly class NextcloudPublicShareResult
{
    public function __construct(
        public string $url,
        public string|int $shareId,
        public bool $created,
    ) {}
}
