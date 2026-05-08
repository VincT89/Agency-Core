<?php

namespace App\Domain\Chatbot\Actions;

use App\Models\Client;
use App\Models\Chatbot\ChatbotClient;
use App\Models\Chatbot\ChatbotMarketingCampaign;
use App\Domain\Chatbot\Support\ChatbotLabelMapper;

class SyncChatbotMarketingCampaignsAction
{
    public function execute(Client $client, ChatbotClient $chatbotClient): void
    {
        // Definizione: campagne attive + campagne chiuse negli ultimi 90 giorni
        $ninetyDaysAgo = now()->subDays(90);
        
        $campaigns = $client->marketingCampaigns()
            ->where(function ($query) use ($ninetyDaysAgo) {
                $query->where('status', 'active')
                      ->orWhere(function ($q) use ($ninetyDaysAgo) {
                          $q->whereIn('status', ['closed', 'cancelled'])
                            ->where('updated_at', '>=', $ninetyDaysAgo);
                      });
            })
            ->get();

        foreach ($campaigns as $campaign) {
            ChatbotMarketingCampaign::updateOrCreate(
                [
                    'marketing_campaign_id' => $campaign->id,
                ],
                [
                    'chatbot_client_id' => $chatbotClient->id,
                    'client_id' => $client->id,
                    'name' => $campaign->name,
                    'description' => $campaign->description,
                    'status' => ChatbotLabelMapper::status($campaign->status),
                    'starts_at' => $campaign->starts_at,
                    'ends_at' => $campaign->ends_at,
                    'source_created_at' => $campaign->created_at,
                    'source_updated_at' => $campaign->updated_at,
                    'synced_at' => now(),
                ]
            );
        }

        // Retention: eliminiamo vecchie campagne non più rientranti nella condizione
        $validCampaignIds = $campaigns->pluck('id');

        ChatbotMarketingCampaign::where('chatbot_client_id', $chatbotClient->id)
            ->whereNotIn('marketing_campaign_id', $validCampaignIds)
            ->delete();
    }
}
