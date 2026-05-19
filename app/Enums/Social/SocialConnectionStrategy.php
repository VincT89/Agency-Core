<?php

namespace App\Enums\Social;

enum SocialConnectionStrategy: string
{
    case AgencyOauth = 'agency_oauth';
    case ManualTokenConfig = 'manual_token_config';

    public function label(): string
    {
        return match($this) {
            self::AgencyOauth => 'Agenzia (OAuth)',
            self::ManualTokenConfig => 'Configurazione Manuale / Token',
        };
    }
}
