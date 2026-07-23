<?php

namespace App\Domain\Social\Exceptions;

use Exception;

class MarketingCampaignPostMediaResolutionException extends Exception
{
    public static function forMissingCurrentVersion(int $postId): self
    {
        return new self("Current version ID missing or invalid for post ID {$postId}.");
    }

    public static function forForeignMedia(int $mediaId, int $postId, int $expectedPostId): self
    {
        return new self("Media ID {$mediaId} belongs to post ID {$postId}, but expected post ID {$expectedPostId}.");
    }

    public static function forMissingLegacyReference(string $reference, int $versionId): self
    {
        return new self("Legacy reference '{$reference}' could not be resolved to a media for version ID {$versionId}.");
    }

    public static function forAmbiguousLegacyReference(string $reference, int $versionId): self
    {
        return new self("Legacy reference '{$reference}' is ambiguous and matches multiple media for version ID {$versionId}.");
    }

    public static function forUnresolvableVersion(int $versionId): self
    {
        return new self("Version ID {$versionId} has no pivot and no valid legacy references. Cannot resolve media.");
    }
}
