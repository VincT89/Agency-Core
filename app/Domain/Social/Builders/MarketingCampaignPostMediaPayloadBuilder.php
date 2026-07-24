<?php

namespace App\Domain\Social\Builders;

use App\Models\MarketingCampaignPost;
use App\Enums\Social\MarketingCampaignPostType;

class MarketingCampaignPostMediaPayloadBuilder
{
    public function __construct(
        private readonly \App\Domain\Social\Services\MarketingCampaignPostVersionMediaResolver $resolver,
        private readonly \App\Domain\Social\Services\MarketingCampaignPostMediaUrlResolver $urlResolver
    ) {}

    public function build(MarketingCampaignPost $post): array
    {
        $resolution = $this->resolver->resolveForPost($post);
        $orderedMediaItems = $resolution->mediaItems;
        
        $mediaItemsPayload = $orderedMediaItems->values()->map(function ($item, $index) {
            $url = $this->urlResolver->deliveryUrl($item);
            if ($url === null) {
                throw \App\Domain\Social\Exceptions\MarketingCampaignPostMediaDeliveryException::forMedia($item->id);
            }

            return [
                'id' => $item->id,
                'source' => $item->source,
                'media_type' => $item->media_type,
                'url' => $url,
                'mime_type' => $item->mime_type,
                'original_name' => $item->original_name,
                'nextcloud_path' => $item->nextcloud_path,
                'nextcloud_share_url' => $item->nextcloud_share_url,
                'nextcloud_file_id' => $item->nextcloud_file_id,
                'sort_order' => $index,
            ];
        })->toArray();
        
        $mediaCount = count($mediaItemsPayload);

        $primaryMediaUrl = null;
        $primaryMediaType = null;
        $firstMediaAlias = null;

        if (!empty($mediaItemsPayload)) {
            $firstMediaAlias = $mediaItemsPayload[0];
            $primaryMediaUrl = $firstMediaAlias['url'];
            $primaryMediaType = $firstMediaAlias['media_type'];
        }

        return [
            'media_count' => $mediaCount,
            'primary_media_url' => $primaryMediaUrl,
            'primary_media_type' => $primaryMediaType,
            'media_items' => $mediaItemsPayload,
            'media' => $firstMediaAlias,
        ];
    }
}
