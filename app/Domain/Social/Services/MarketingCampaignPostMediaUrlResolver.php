<?php

namespace App\Domain\Social\Services;

use App\Models\MarketingCampaignPostMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class MarketingCampaignPostMediaUrlResolver
{
    public function deliveryUrl(MarketingCampaignPostMedia $media): ?string
    {
        if ($media->source === 'nextcloud') {
            return $media->nextcloud_share_url ? rtrim($media->nextcloud_share_url, '/') . '/download' : null;
        }

        if ($media->disk === 'public' && $media->path) {
            try {
                return Storage::disk('public')->url($media->path);
            } catch (\Exception $e) {
                // Ignore missing disk
            }
        }
        
        if ($media->path) {
            return route('media.marketing-campaign-posts', ['path' => $media->path]);
        }

        return null;
    }

    public function previewUrl(MarketingCampaignPostMedia $media): ?string
    {
        if ($media->source === 'nextcloud') {
            return $media->nextcloud_share_url ? rtrim($media->nextcloud_share_url, '/') . '/preview' : null;
        }

        return $this->deliveryUrl($media);
    }

    public function orderedDeliveryUrls(Collection $mediaItems): array
    {
        return $mediaItems
            ->values()
            ->map(function (MarketingCampaignPostMedia $media): string {
                return $this->deliveryUrl($media)
                    ?? throw \App\Domain\Social\Exceptions\MarketingCampaignPostMediaDeliveryException::forMedia($media->id);
            })
            ->all();
    }

    public function primaryPreviewUrlOrNull(Collection $mediaItems): ?string
    {
        $firstMedia = $mediaItems->first();
        if (!$firstMedia) {
            return null;
        }

        $url = $this->previewUrl($firstMedia);
        if ($url === null) {
            \Illuminate\Support\Facades\Log::warning('social.version_media.primary_preview_resolution_failed', [
                'marketing_campaign_post_media_id' => $firstMedia->id,
            ]);
            return null;
        }

        return $url;
    }
}
