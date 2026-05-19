<?php

namespace App\Enums\Social;

enum SocialConnectionMode: string
{
    case Manual = 'manual';
    case Oauth = 'oauth';
    
    public function label(): string
    {
        return match($this) {
            self::Manual => 'Manuale',
            self::Oauth => 'API / OAuth',
        };
    }
}
