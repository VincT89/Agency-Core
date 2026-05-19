<?php

namespace App\Enums\Social;

enum SocialApiStatus: string
{
    case NotConfigured = 'not_configured';
    case Connected = 'connected';
    case TokenExpired = 'token_expired';
    case Revoked = 'revoked';
    case Disconnected = 'disconnected';
    case Error = 'error';
    case TemporaryFailure = 'temporary_failure';

    public function label(): string
    {
        return match($this) {
            self::NotConfigured => 'Non configurato',
            self::Connected => 'Connesso',
            self::TokenExpired => 'Token scaduto',
            self::Revoked => 'Accesso revocato',
            self::Disconnected => 'Sconnesso',
            self::Error => 'Errore',
            self::TemporaryFailure => 'Errore temporaneo',
        };
    }
}
