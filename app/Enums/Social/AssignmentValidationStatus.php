<?php

namespace App\Enums\Social;

enum AssignmentValidationStatus: string
{
    case Allowed = 'allowed';
    case Warning = 'warning';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match($this) {
            self::Allowed => 'Consentito',
            self::Warning => 'Avviso',
            self::Blocked => 'Bloccato',
        };
    }
}
