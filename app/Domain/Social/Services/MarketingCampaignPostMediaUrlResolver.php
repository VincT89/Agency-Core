<?php

namespace App\Domain\Social\Services;

use App\Domain\Social\Exceptions\MarketingCampaignPostMediaDeliveryException;
use App\Models\MarketingCampaignPostMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class MarketingCampaignPostMediaUrlResolver
{
    public function deliveryUrl(MarketingCampaignPostMedia $media): ?string
    {
        if ($media->source === 'nextcloud') {
            return $media->nextcloud_share_url ? rtrim($media->nextcloud_share_url, '/').'/download' : null;
        }

        $effectiveDisk = $media->disk
            ?: (in_array($media->source, ['local', 'n8n'], true)
                ? 'public'
                : null);

        if ($media->path
            && in_array($effectiveDisk, ['public', 'social_media'], true)) {
            return URL::temporarySignedRoute(
                'social.media.delivery',
                now()->addMinutes(
                    (int) config('services.tiktok.media_url_ttl', 1440)
                ),
                ['media' => $media->id]
            );
        }

        return null;
    }

    public function previewUrl(MarketingCampaignPostMedia $media): ?string
    {
        if ($media->source === 'nextcloud') {
            if ($media->isVideo() && filled($media->nextcloud_path)) {
                return URL::route('nextcloud.download', [
                    'path' => $media->nextcloud_path,
                ]);
            }

            return $media->nextcloud_share_url ? rtrim($media->nextcloud_share_url, '/').'/preview' : null;
        }

        return $this->deliveryUrl($media);
    }

    public function orderedDeliveryUrls(Collection $mediaItems): array
    {
        return $mediaItems
            ->values()
            ->map(function (MarketingCampaignPostMedia $media): string {
                return $this->deliveryUrl($media)
                    ?? throw MarketingCampaignPostMediaDeliveryException::forMedia($media->id);
            })
            ->all();
    }

    public function primaryPreviewUrlOrNull(Collection $mediaItems): ?string
    {
        $firstMedia = $mediaItems->first();
        if (! $firstMedia) {
            return null;
        }

        $url = $this->previewUrl($firstMedia);
        if ($url === null) {
            Log::warning('social.version_media.primary_preview_resolution_failed', [
                'marketing_campaign_post_media_id' => $firstMedia->id,
            ]);

            return null;
        }

        return $url;
    }
}
