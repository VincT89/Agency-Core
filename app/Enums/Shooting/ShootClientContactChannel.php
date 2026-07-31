<?php

namespace App\Enums\Shooting;

enum ShootClientContactChannel: string
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';
    case Phone = 'phone';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Whatsapp => 'WhatsApp',
            self::Phone => 'Telefono',
            self::Other => 'Altro canale',
        };
    }
}
