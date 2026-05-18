<?php

namespace App\Enums\Social;

enum MarketingCampaignPostRegenerationType: string
{
    case Full = 'full';
    case Caption = 'caption';
    case Image = 'image';
    case Manual = 'manual';

    public function label(): string
    {
        return match($this) {
            self::Full => 'Testo e Immagine',
            self::Caption => 'Solo Testo (Caption)',
            self::Image => 'Solo Immagine',
            self::Manual => 'Manuale',
        };
    }
}
