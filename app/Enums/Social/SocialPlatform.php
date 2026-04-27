<?php

namespace App\Enums\Social;

enum SocialPlatform: string
{
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case Linkedin = 'linkedin';
    case Tiktok = 'tiktok';
    case Other = 'other';

    public function label(): string
    {
        return match($this) {
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::Linkedin => 'LinkedIn',
            self::Tiktok => 'TikTok',
            self::Other => 'Altro',
        };
    }
}
