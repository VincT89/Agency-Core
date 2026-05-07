<?php

namespace App\Observers;

use App\Models\MarketingCampaign;
use App\Jobs\Chatbot\SyncChatbotClientDataJob;

class MarketingCampaignObserver
{
    public function saved(MarketingCampaign $campaign): void
    {
        if ($campaign->wasChanged(['name', 'status', 'starts_at', 'ends_at', 'client_id'])) {
            SyncChatbotClientDataJob::dispatch($campaign->client_id)
                ->delay(now()->addSeconds(10))
                ->onQueue('chatbot');
        }
    }
    
    public function deleted(MarketingCampaign $campaign): void
    {
        SyncChatbotClientDataJob::dispatch($campaign->client_id)
            ->delay(now()->addSeconds(10))
            ->onQueue('chatbot');
    }
}
