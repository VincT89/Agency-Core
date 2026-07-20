<?php

namespace App\Domain\Social\Publishing;

use App\Models\MarketingCampaignPost;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPostPublication;

interface SocialPublisherInterface
{
    /**
     * Pubblica il post sull'account specificato usando lo snapshot della pubblicazione.
     */
    public function publish(MarketingCampaignPostPublication $publication, ClientSocialAccount $account, ?string $correlationId = null): PublishResult;
    
    /**
     * Verifica se i requisiti per la pubblicazione (token, permessi) sono soddisfatti.
     */
    public function verifyAccountCapabilities(ClientSocialAccount $account): bool;
}
