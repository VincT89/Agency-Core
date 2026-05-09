<?php

namespace App\Domain\Social\Builders;

use App\Models\MarketingCampaignPost;
use App\Enums\Social\MarketingCampaignPostType;

class MarketingCampaignPostMediaPayloadBuilder
{
    public static function build(MarketingCampaignPost $post): array
    {
        $post->loadMissing('orderedMediaItems');
        $orderedMediaItems = $post->orderedMediaItems;
        
        $mediaItemsPayload = $orderedMediaItems->map(function ($item) {
            $url = null;
            if ($item->source === 'nextcloud') {
                $url = $item->nextcloud_share_url ? rtrim($item->nextcloud_share_url, '/') . '/download' : null;
            } elseif ($item->path) {
                $url = route('media.marketing-campaign-posts', ['path' => $item->path]);
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
                'sort_order' => $item->sort_order,
            ];
        })->toArray();
        
        $mediaCount = count($mediaItemsPayload);
        if ($mediaCount === 0) {
            $mediaCount = ($post->media_source || $post->media_path) ? 1 : 0;
        }

        $primaryMedia = $orderedMediaItems->first();
        $primaryMediaUrl = $post->media_url;
        
        if ($primaryMedia) {
            $primaryMediaType = $primaryMedia->media_type;
        } else {
            // Fallback for legacy
            $primaryMediaType = null;
            if ($mediaCount > 0) {
                // Handle Enums correctly, the property might be an Enum or scalar depending on casting
                $contentType = $post->content_type instanceof MarketingCampaignPostType 
                    ? $post->content_type 
                    : MarketingCampaignPostType::tryFrom($post->content_type);
                    
                if (in_array($contentType, [MarketingCampaignPostType::Video, MarketingCampaignPostType::Reel])) {
                    $primaryMediaType = 'video';
                } else {
                    $primaryMediaType = 'image';
                }
            }
        }

        $firstMediaAlias = !empty($mediaItemsPayload) ? $mediaItemsPayload[0] : [
            'source' => $post->media_source,
            'url' => $post->media_url,
            'nextcloud_path' => $post->nextcloud_path,
            'nextcloud_share_url' => $post->nextcloud_share_url,
            'nextcloud_file_id' => $post->nextcloud_file_id,
        ];

        return [
            'media_count' => $mediaCount,
            'primary_media_url' => $primaryMediaUrl,
            'primary_media_type' => $primaryMediaType,
            'media_items' => $mediaItemsPayload,
            'media' => $firstMediaAlias,
        ];
    }
}
