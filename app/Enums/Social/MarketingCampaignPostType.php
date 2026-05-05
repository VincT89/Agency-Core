<?php

namespace App\Enums\Social;

enum MarketingCampaignPostType: string
{
    case Post = 'post';
    case Story = 'story';
    case Reel = 'reel';

    public function label(): string
    {
        return match($this) {
            self::Post => 'Post',
            self::Story => 'Story',
            self::Reel => 'Reel',
        };
    }
}
