<?php

namespace App\Domain\Social\Services;

use App\Domain\Social\DTOs\PreflightResult;
use App\Enums\Social\SocialPlatform;

class MetaSnapshotPreflightRules
{
    public function validate(array $snapshotPayload): PreflightResult
    {
        $errors = [];
        $media = $snapshotPayload['media'] ?? [];
        $platform = SocialPlatform::tryFrom($snapshotPayload['platform'] ?? '');

        if (empty($media) && empty($snapshotPayload['caption'])) {
             $errors[] = "Meta requires either a caption or at least one media file.";
        }
        
        if ($platform === SocialPlatform::Facebook) {
            if (count($media) > 1) {
                $errors[] = "Facebook publishing currently blocks multiple images (Carousel not supported in this phase).";
            }
        } elseif ($platform === SocialPlatform::Instagram) {
            if (count($media) > 10) {
                $errors[] = "Instagram supports a maximum of 10 media items per carousel.";
            }
        }
        
        return new PreflightResult(
            isPass: empty($errors),
            errors: $errors
        );
    }
}
