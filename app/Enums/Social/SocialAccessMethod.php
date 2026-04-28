<?php

namespace App\Enums\Social;

enum SocialAccessMethod: string
{
    case MetaBusiness = 'meta_business';
    case TiktokBusinessCenter = 'tiktok_business_center';
    case Credentials = 'credentials';
    case DirectAccess = 'direct_access';
    case Unknown = 'unknown';
    case None = 'none';

    public function label(): string
    {
        return match($this) {
            self::MetaBusiness => 'Meta Business',
            self::TiktokBusinessCenter => 'TikTok Business Center',
            self::Credentials => 'Credenziali Condivise',
            self::DirectAccess => 'Accesso Diretto',
            self::Unknown => 'Sconosciuto',
            self::None => 'Nessun Accesso',
        };
    }
}
