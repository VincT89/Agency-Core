<?php

namespace App\Enums\Social;

enum PublishingStatus: string
{
    case Ready = 'ready';
    case NotReady = 'not_ready';
    case MissingPermissions = 'missing_permissions';
    case InvalidToken = 'invalid_token';

    public function label(): string
    {
        return match($this) {
            self::Ready => 'Pronto',
            self::NotReady => 'Non Pronto',
            self::MissingPermissions => 'Permessi Mancanti',
            self::InvalidToken => 'Token Invalido',
        };
    }
}
