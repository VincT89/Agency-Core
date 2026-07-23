<?php

namespace App\Domain\Social\Exceptions;

use Exception;
use App\Models\MarketingCampaignPost;

class HistoricalPostProtectedException extends Exception
{
    public static function forPost(MarketingCampaignPost $post): self
    {
        return new self("The post ID {$post->id} is protected because it contains historical versions or publications and cannot be deleted.");
    }
}
