<?php

namespace App\Enums\Social;

enum MetaApiStatus: string
{
    case NotConfigured = 'not_configured';
    case Connected = 'connected';
    case TokenExpired = 'token_expired';
    case Error = 'error';

    public function label(): string
    {
        return match($this) {
            self::NotConfigured => 'Non configurato',
            self::Connected => 'Connesso',
            self::TokenExpired => 'Token scaduto',
            self::Error => 'Errore',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NotConfigured => 'var(--text3)',
            self::Connected => 'var(--green)',
            self::TokenExpired => 'var(--orange)',
            self::Error => 'var(--red)',
        };
    }
}
