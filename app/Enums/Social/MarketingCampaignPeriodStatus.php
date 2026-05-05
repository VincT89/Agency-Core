<?php

namespace App\Enums\Social;

enum MarketingCampaignPeriodStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Invoiced = 'invoiced';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Planned => 'Pianificato',
            self::Active => 'Attivo',
            self::Invoiced => 'Fatturato',
            self::Cancelled => 'Annullato',
        };
    }
}
