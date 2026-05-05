<?php

namespace App\Enums\Social;

enum MarketingCampaignPostVersionSource: string
{
    case N8n = 'n8n';
    case Manual = 'manual';

    public function label(): string
    {
        return match($this) {
            self::N8n => 'Generato da AI (N8n)',
            self::Manual => 'Manuale',
        };
    }
}
