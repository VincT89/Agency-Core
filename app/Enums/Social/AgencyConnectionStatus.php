<?php

namespace App\Enums\Social;

enum AgencyConnectionStatus: string
{
    case Connected = 'connected';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case PermissionMissing = 'permission_missing';
    case SyncFailed = 'sync_failed';

    public function label(): string
    {
        return match($this) {
            self::Connected => 'Connesso',
            self::Expired => 'Scaduto',
            self::Revoked => 'Revocato',
            self::PermissionMissing => 'Permessi Mancanti',
            self::SyncFailed => 'Sincronizzazione Fallita',
        };
    }
}
