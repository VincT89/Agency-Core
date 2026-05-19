<?php

namespace App\Enums\Social;

enum SocialAssetType: string
{
    case FacebookPage = 'facebook_page';
    case InstagramBusinessAccount = 'instagram_business_account';

    public function label(): string
    {
        return match($this) {
            self::FacebookPage => 'Pagina Facebook',
            self::InstagramBusinessAccount => 'Account Instagram Business',
        };
    }
}
