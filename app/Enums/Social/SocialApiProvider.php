<?php

namespace App\Enums\Social;

enum SocialApiProvider: string
{
    case MetaGraph = 'meta_graph';
    case InstagramGraph = 'instagram_graph';
    case TiktokContentApi = 'tiktok_content_api';

    public function label(): string
    {
        return match($this) {
            self::MetaGraph => 'Meta Graph API',
            self::InstagramGraph => 'Instagram Graph API',
            self::TiktokContentApi => 'TikTok Content API',
        };
    }
}
