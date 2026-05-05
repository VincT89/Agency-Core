<?php

namespace App\Enums\Social;

enum MarketingCampaignExtraStatus: string
{
    case Pending = 'pending';
    case Invoiced = 'invoiced';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'In Sospeso',
            self::Invoiced => 'Fatturato',
            self::Cancelled => 'Annullato',
        };
    }
}
