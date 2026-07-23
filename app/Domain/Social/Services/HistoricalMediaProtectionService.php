<?php

namespace App\Domain\Social\Services;

use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostPublication;
use App\Domain\Social\Exceptions\HistoricalMediaProtectedException;
use Illuminate\Support\Facades\Log;

class HistoricalMediaProtectionService
{
    /**
     * Asserts that a media can be deleted safely.
     * Throws HistoricalMediaProtectedException if the media is protected by
     * being part of a version, a publication snapshot, or ambiguous history.
     */
    public function assertDeletable(MarketingCampaignPostMedia $media): void
    {
        // 1. Protection by Versions
        if ($media->versions()->exists()) {
            throw HistoricalMediaProtectedException::forVersionedMedia($media);
        }

        // 2. Protection by Publication Snapshot
        $postId = $media->marketing_campaign_post_id;
        
        $isProtectedBySnapshot = false;
        $isAmbiguous = false;

        MarketingCampaignPostPublication::where('marketing_campaign_post_id', $postId)
            ->chunkById(100, function ($publications) use ($media, &$isProtectedBySnapshot, &$isAmbiguous) {
                foreach ($publications as $publication) {
                    $payload = $publication->payload_snapshot;
                    
                    if (!is_array($payload)) {
                        $isAmbiguous = true;
                        return false;
                    }

                    if (!array_key_exists('media', $payload)) {
                        $isAmbiguous = true;
                        return false;
                    }
                    
                    $mediaItems = $payload['media'];
                    if (!is_array($mediaItems)) {
                        $isAmbiguous = true;
                        return false;
                    }

                    foreach ($mediaItems as $item) {
                        if (!is_array($item)) {
                            $isAmbiguous = true;
                            return false;
                        }

                        if (!array_key_exists('id', $item) && !array_key_exists('media_id', $item)) {
                            $isAmbiguous = true;
                            return false;
                        }
                        
                        $validIds = [];
                        
                        foreach (['id', 'media_id'] as $key) {
                            if (array_key_exists($key, $item)) {
                                $val = $item[$key];
                                if (!is_int($val) && !is_string($val)) {
                                    $isAmbiguous = true;
                                    return false;
                                }
                                $strVal = (string) $val;
                                if (!ctype_digit($strVal) || (int) $strVal <= 0) {
                                    $isAmbiguous = true;
                                    return false;
                                }
                                $validIds[] = (int) $strVal;
                            }
                        }
                        
                        if (count($validIds) === 2 && $validIds[0] !== $validIds[1]) {
                            $isAmbiguous = true;
                            return false;
                        }
                        
                        if (in_array($media->id, $validIds, true)) {
                            $isProtectedBySnapshot = true;
                            return false; // Break chunking
                        }
                    }
                }
            });

        if ($isProtectedBySnapshot) {
            throw HistoricalMediaProtectedException::forSnapshotMedia($media);
        }

        if ($isAmbiguous) {
            throw HistoricalMediaProtectedException::forAmbiguousHistory($media);
        }
    }
}
