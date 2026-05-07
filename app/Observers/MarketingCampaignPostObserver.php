<?php

namespace App\Observers;

use App\Models\MarketingCampaignPost;
use App\Jobs\Chatbot\SyncChatbotClientDataJob;

class MarketingCampaignPostObserver
{
    public function saved(MarketingCampaignPost $post): void
    {
        if ($post->wasChanged(['title', 'description', 'status', 'media_path', 'media_source', 'scheduled_date', 'scheduled_time', 'marketing_campaign_id'])) {
            // we need client_id from campaign
            if ($post->campaign) {
                SyncChatbotClientDataJob::dispatch($post->campaign->client_id)
                    ->delay(now()->addSeconds(10))
                    ->onQueue('chatbot');
            }
        }
    }
    
    public function deleted(MarketingCampaignPost $post): void
    {
        if ($post->campaign) {
            SyncChatbotClientDataJob::dispatch($post->campaign->client_id)
                ->delay(now()->addSeconds(10))
                ->onQueue('chatbot');
        }
    }
}
