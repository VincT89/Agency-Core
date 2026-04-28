<?php

namespace App\Enums\Social;

enum ClientSocialAccessStatus: string
{
    case Missing = 'missing';
    case Requested = 'requested';
    case Granted = 'granted';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match($this) {
            self::Missing => 'Mancante',
            self::Requested => 'Richiesto',
            self::Granted => 'Concesso',
            self::Expired => 'Scaduto',
            self::Revoked => 'Revocato',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Missing => 'var(--text3)',
            self::Requested => 'var(--orange)',
            self::Granted => 'var(--green)',
            self::Expired, self::Revoked => 'var(--red)',
        };
    }
}
