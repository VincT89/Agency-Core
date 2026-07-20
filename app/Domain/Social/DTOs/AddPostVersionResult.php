<?php

namespace App\Domain\Social\DTOs;

use App\Models\MarketingCampaignPostVersion;

final readonly class AddPostVersionResult
{
    public function __construct(
        public string $outcome, // 'created', 'duplicate', 'ignored', 'conflict'
        public ?MarketingCampaignPostVersion $version = null,
        public ?string $reason = null,
    ) {}

    public function wasCreated(): bool
    {
        return $this->outcome === 'created';
    }
}
