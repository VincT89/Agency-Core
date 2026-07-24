<?php

namespace App\Domain\Social\Exceptions;

use Exception;

class MarketingCampaignPostMediaDeliveryException extends Exception
{
    public static function forMedia(int $mediaId): self
    {
        return new self("Cannot determine delivery URL for media ID {$mediaId}.");
    }
}
