<?php

namespace App\Enums\Social;

enum EditorialSlotStatus: string
{
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Scheduled => 'Pianificato',
            self::Published => 'Pubblicato',
            self::Cancelled => 'Annullato',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Scheduled => 'var(--accent)',
            self::Published => 'var(--green)',
            self::Cancelled => 'var(--red)',
        };
    }
}
