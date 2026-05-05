<?php

namespace App\Enums\Social;

enum MarketingCampaignPostCommentVisibility: string
{
    case Internal = 'internal';
    case Client = 'client';
    case Public = 'public';

    public function label(): string
    {
        return match($this) {
            self::Internal => 'Team Interno',
            self::Client => 'Cliente (Feedback)',
            self::Public => 'Pubblico',
        };
    }
}
