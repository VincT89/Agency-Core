<?php

namespace App\Enums\Social;

enum SocialApiStatus: string
{
    case NotConfigured = 'not_configured';
    case Connected = 'connected';
    case TokenExpired = 'token_expired';
    case Revoked = 'revoked';
    case Error = 'error';

    public function label(): string
    {
        return match($this) {
            self::NotConfigured => 'Non configurato',
            self::Connected => 'Connesso',
            self::TokenExpired => 'Token scaduto',
            self::Revoked => 'Accesso revocato',
            self::Error => 'Errore',
        };
    }
}
