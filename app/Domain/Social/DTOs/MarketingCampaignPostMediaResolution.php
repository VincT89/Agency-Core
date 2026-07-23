<?php

namespace App\Domain\Social\DTOs;

use App\Domain\Social\Enums\MarketingCampaignPostMediaResolutionSource;
use Illuminate\Support\Collection;

class MarketingCampaignPostMediaResolution
{
    public function __construct(
        public readonly Collection $mediaItems,
        public readonly MarketingCampaignPostMediaResolutionSource $source,
        public readonly ?int $versionId,
        public readonly bool $usesLegacyFallback,
    ) {
    }
}
