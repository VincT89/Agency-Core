<?php

namespace App\Enums\Social;

enum MarketingCampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Closed = 'closed';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Bozza',
            self::Active => 'Attiva',
            self::Paused => 'In Pausa',
            self::Closed => 'Chiusa',
        };
    }
}
