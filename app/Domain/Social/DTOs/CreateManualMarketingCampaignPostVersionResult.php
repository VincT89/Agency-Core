<?php

namespace App\Domain\Social\DTOs;

use App\Models\MarketingCampaignPostVersion;

class CreateManualMarketingCampaignPostVersionResult
{
    public const CREATED = 'created';
    public const UNCHANGED = 'unchanged';

    private function __construct(
        public readonly MarketingCampaignPostVersion $version,
        public readonly string $outcome
    ) {
    }

    public static function created(MarketingCampaignPostVersion $version): self
    {
        return new self($version, self::CREATED);
    }

    public static function unchanged(MarketingCampaignPostVersion $version): self
    {
        return new self($version, self::UNCHANGED);
    }

    public function isCreated(): bool
    {
        return $this->outcome === self::CREATED;
    }

    public function isUnchanged(): bool
    {
        return $this->outcome === self::UNCHANGED;
    }
}
