<?php

namespace App\Enums\Social;

enum PublicationMode: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';

    public function label(): string
    {
        return match($this) {
            self::Manual => 'Manuale',
            self::Automatic => 'Automatica',
        };
    }
}
