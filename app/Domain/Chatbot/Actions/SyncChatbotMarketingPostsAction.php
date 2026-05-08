<?php

namespace App\Domain\Chatbot\Actions;

use App\Models\Client;
use App\Models\Chatbot\ChatbotClient;
use App\Models\Chatbot\ChatbotMarketingPost;
use App\Models\MarketingCampaignPost;
use App\Domain\Chatbot\Support\ChatbotLabelMapper;

class SyncChatbotMarketingPostsAction
{
    private const MAX_POSTS_PER_CLIENT = 50;

    public function execute(Client $client, ChatbotClient $chatbotClient): void
    {
        // Ultimi post del cliente (ritenzione controllata da costante)
        $latestPosts = MarketingCampaignPost::query()
            ->with('campaign')
            ->whereHas('campaign', function ($q) use ($client) {
                $q->where('client_id', $client->id);
            })
            ->orderBy('created_at', 'desc')
            ->limit(self::MAX_POSTS_PER_CLIENT)
            ->get();

        foreach ($latestPosts as $post) {
            ChatbotMarketingPost::updateOrCreate(
                [
                    'marketing_campaign_post_id' => $post->id,
                ],
                [
                    'chatbot_client_id' => $chatbotClient->id,
                    'client_id' => $client->id,
                    'marketing_campaign_id' => $post->marketing_campaign_id,
                    'campaign_name' => $post->campaign?->name,
                    'title' => $post->title,
                    'description' => $post->description,
                    'content_type' => ChatbotLabelMapper::contentType($post->content_type),
                    'status' => ChatbotLabelMapper::status($post->status),
                    'media_path' => $post->media_path,
                    'media_source' => $post->media_source,
                    'publishing_platforms' => ChatbotLabelMapper::platforms($post->publishing_platforms ?? []),
                    'scheduled_date' => $post->scheduled_date,
                    'scheduled_time' => $post->scheduled_time,
                    'published_at' => $post->published_at,
                    'source_created_at' => $post->created_at,
                    'source_updated_at' => $post->updated_at,
                    'nextcloud_file_id' => $post->nextcloud_file_id,
                    'nextcloud_path' => $post->nextcloud_path,
                    'nextcloud_share_url' => $post->nextcloud_share_url,
                    'synced_at' => now(),
                ]
            );
        }

        // Retention
        
        // Logica di retention: manteniamo sempre e solo gli ultimi N post.
        // I record più vecchi (anche se modificati in precedenza e finiti nel read model) 
        // vengono eliminati dalla projection per non appesantire n8n.
        $validPostIds = $latestPosts->pluck('id');

        ChatbotMarketingPost::where('chatbot_client_id', $chatbotClient->id)
            ->whereNotIn('marketing_campaign_post_id', $validPostIds)
            ->delete();
    }
}
