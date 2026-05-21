<?php

namespace App\Enums\Social;

enum SocialConnectionStrategy: string
{
    case AgencyOauth = 'agency_oauth';
    case PlatformOauth = 'platform_oauth';
    case ManualTokenConfig = 'manual_token_config';

    public function label(): string
    {
        return match($this) {
            self::AgencyOauth => 'Agenzia (OAuth)',
            self::PlatformOauth => 'Piattaforma Diretta (OAuth)',
            self::ManualTokenConfig => 'Configurazione Manuale / Token',
        };
    }
}
