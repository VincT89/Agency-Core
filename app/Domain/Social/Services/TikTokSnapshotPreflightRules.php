<?php

namespace App\Domain\Social\Services;

use App\Domain\Social\DTOs\PreflightResult;

class TikTokSnapshotPreflightRules
{
    public function validate(array $snapshotPayload): PreflightResult
    {
        $errors = [];
        $media = $snapshotPayload['media'] ?? [];
        
        if (count($media) !== 1) {
             $errors[] = "TikTok requires exactly one video.";
        } else {
             // The snapshot media items have 'mime_type' and sometimes 'media_type', but not 'type'
             $mimeType = $media[0]['mime_type'] ?? '';
             if (strpos($mimeType, 'video/') !== 0) {
                 $errors[] = "TikTok requires the media file to be a video.";
             }
        }
        
        return new PreflightResult(
            isPass: empty($errors),
            errors: $errors
        );
    }
}
