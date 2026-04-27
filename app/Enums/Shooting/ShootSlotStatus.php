<?php

namespace App\Enums\Shooting;

enum ShootSlotStatus: string
{
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    
    public function label(): string
    {
        return match($this) {
            self::Proposed => 'Proposto',
            self::Accepted => 'Accettato',
            self::Rejected => 'Rifiutato',
        };
    }
}
