<?php

namespace App\Domain\Social\Exceptions;

use Exception;
use App\Models\MarketingCampaignPostMedia;

class HistoricalMediaProtectedException extends Exception
{
    public readonly int $mediaId;
    public readonly int $postId;
    public readonly int $associatedVersionsCount;
    public readonly string $protectionReason;

    public function __construct(
        int $mediaId,
        int $postId,
        int $associatedVersionsCount,
        string $protectionReason
    ) {
        $this->mediaId = $mediaId;
        $this->postId = $postId;
        $this->associatedVersionsCount = $associatedVersionsCount;
        $this->protectionReason = $protectionReason;

        parent::__construct(sprintf(
            'Media %d of post %d is protected and cannot be deleted. Reason: %s (Associated versions: %d)',
            $this->mediaId,
            $this->postId,
            $this->protectionReason,
            $this->associatedVersionsCount
        ));
    }

    public static function forVersionedMedia(MarketingCampaignPostMedia $media): self
    {
        return new self(
            $media->id,
            $media->marketing_campaign_post_id,
            $media->versions()->count(),
            'Media is currently bound to one or more post versions.'
        );
    }

    public static function forSnapshotMedia(MarketingCampaignPostMedia $media): self
    {
        return new self(
            $media->id,
            $media->marketing_campaign_post_id,
            0,
            'Media is referenced in a publication snapshot.'
        );
    }

    public static function forAmbiguousHistory(MarketingCampaignPostMedia $media): self
    {
        return new self(
            $media->id,
            $media->marketing_campaign_post_id,
            0,
            'Snapshot history could not be parsed confidently. Deletion is conservatively blocked.'
        );
    }
}
