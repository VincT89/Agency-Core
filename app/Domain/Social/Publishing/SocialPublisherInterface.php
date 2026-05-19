<?php

namespace App\Domain\Social\Publishing;

use App\Models\MarketingCampaignPost;
use App\Models\ClientSocialAccount;

interface SocialPublisherInterface
{
    /**
     * Pubblica il post sull'account specificato.
     */
    public function publish(MarketingCampaignPost $post, ClientSocialAccount $account, ?string $correlationId = null): PublishResult;
    
    /**
     * Verifica se i requisiti per la pubblicazione (token, permessi) sono soddisfatti.
     */
    public function verifyConfiguration(ClientSocialAccount $account): bool;
}
