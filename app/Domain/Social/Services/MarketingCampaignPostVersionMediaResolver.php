<?php

namespace App\Domain\Social\Services;

use App\Models\MarketingCampaignPostVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MarketingCampaignPostVersionMediaResolver
{
    /**
     * Resolves the media items for a specific version.
     * If the version has its own media items attached via pivot, it returns those.
     * Otherwise, it falls back to the legacy post ordered media items, logging the fallback.
     *
     * @param MarketingCampaignPostVersion $version
     * @return Collection
     */
    public function resolveMediaItems(MarketingCampaignPostVersion $version): Collection
    {
        $versionMedia = $version->mediaItems;

        if ($versionMedia->isNotEmpty()) {
            return $versionMedia;
        }

        Log::info('MarketingCampaignPostVersionMediaResolver: Fallback to legacy post media', [
            'marketing_campaign_post_version_id' => $version->id,
            'marketing_campaign_post_id' => $version->marketing_campaign_post_id,
        ]);

        return $version->post->orderedMediaItems;
    }
}
