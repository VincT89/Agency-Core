<?php

namespace App\Domain\Social\Enums;

enum MarketingCampaignPostMediaResolutionSource: string
{
    case VERSION_PIVOT = 'version_pivot';
    case VERSION_LEGACY = 'version_legacy';
    case CURRENT_POST_LEGACY = 'current_post_legacy';
    case DRAFT_POST = 'draft_post';
}
