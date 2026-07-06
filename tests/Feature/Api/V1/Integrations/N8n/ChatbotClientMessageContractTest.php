<?php

namespace Tests\Feature\Api\V1\Integrations\N8n;

use App\Models\Chatbot\ChatbotClient;
use App\Models\Chatbot\ChatbotMarketingPost;
use App\Models\Chatbot\ChatbotTicket;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotClientMessageContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.n8n.token' => 'secret-testing-token']);
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer secret-testing-token',
        ];
    }

    public function test_valid_client_id_creates_message()
    {
        $client = Client::factory()->create();
        $campaign = \App\Models\MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = \App\Models\MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id]);

        $chatbotClient = ChatbotClient::firstOrCreate(['client_id' => $client->id], ['name' => $client->name]);

        $chatbotPost = ChatbotMarketingPost::firstOrCreate([
            'chatbot_client_id' => $chatbotClient->id,
            'client_id' => $client->id,
            'marketing_campaign_id' => $campaign->id,
            'marketing_campaign_post_id' => $post->id,
        ], [
            'campaign_name' => 'test',
            'title' => 'test',
            'status' => 'draft',
        ]);

        $payload = [
            'client_id' => $client->id,
            'session_type' => 'marketing',
            'session_id' => $post->id,
            'message' => 'Ok',
            'type' => 'approval',
        ];

        $this->postJson('/api/v1/integrations/n8n/chatbot/client-message', $payload, $this->getHeaders())
            ->assertStatus(200);
    }

    public function test_valid_phone_resolves_client_and_creates_message()
    {
        $client = Client::factory()->create(['phone' => '+393331234567']);
        $ticket = \App\Models\Ticket::factory()->create(['client_id' => $client->id, 'title' => 'Test Ticket']);

        $chatbotClient = ChatbotClient::firstOrCreate(['client_id' => $client->id], ['name' => $client->name]);

        $chatbotTicket = ChatbotTicket::firstOrCreate([
            'chatbot_client_id' => $chatbotClient->id,
            'client_id' => $client->id,
            'ticket_id' => $ticket->id,
        ], [
            'code' => 'T-1',
            'title' => 'test',
            'status' => 'open',
        ]);

        $payload = [
            'phone' => '+393331234567',
            'session_type' => 'ticket',
            'session_id' => $ticket->id,
            'message' => 'Need help',
            'type' => 'comment',
        ];

        $this->postJson('/api/v1/integrations/n8n/chatbot/client-message', $payload, $this->getHeaders())
            ->assertStatus(200);
    }

    public function test_client_id_and_phone_mismatch_returns_409()
    {
        $client1 = Client::factory()->create(['phone' => '+111']);
        $client2 = Client::factory()->create(['phone' => '+222']);

        $payload = [
            'client_id' => $client1->id,
            'phone' => '+222', // Mismatch!
            'session_type' => 'marketing',
            'session_id' => 1,
            'message' => 'Test',
            'type' => 'comment',
        ];

        $this->postJson('/api/v1/integrations/n8n/chatbot/client-message', $payload, $this->getHeaders())
            ->assertStatus(409);
    }

    public function test_phone_not_found_returns_404()
    {
        $payload = [
            'phone' => '+99999999',
            'session_type' => 'marketing',
            'session_id' => 1,
            'message' => 'Test',
            'type' => 'comment',
        ];

        $this->postJson('/api/v1/integrations/n8n/chatbot/client-message', $payload, $this->getHeaders())
            ->assertStatus(404);
    }

    public function test_marketing_session_not_found_returns_404()
    {
        $client = Client::factory()->create();
        
        $payload = [
            'client_id' => $client->id,
            'session_type' => 'marketing',
            'session_id' => 9999, // Does not exist
            'message' => 'Test',
            'type' => 'comment',
        ];

        $this->postJson('/api/v1/integrations/n8n/chatbot/client-message', $payload, $this->getHeaders())
            ->assertStatus(404);
    }

    public function test_ticket_session_not_found_returns_404()
    {
        $client = Client::factory()->create();
        
        $payload = [
            'client_id' => $client->id,
            'session_type' => 'ticket',
            'session_id' => 9999, // Does not exist
            'message' => 'Test',
            'type' => 'comment',
        ];

        $this->postJson('/api/v1/integrations/n8n/chatbot/client-message', $payload, $this->getHeaders())
            ->assertStatus(404);
    }

    public function test_approval_type_updates_post_status()
    {
        $client = Client::factory()->create();
        $chatbotClient = ChatbotClient::firstOrCreate(['client_id' => $client->id], ['name' => $client->name]);

        $campaign = \App\Models\MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = \App\Models\MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => \App\Enums\Social\MarketingCampaignPostStatus::SentToClient->value,
        ]);
        
        $chatbotPost = ChatbotMarketingPost::firstOrCreate([
            'chatbot_client_id' => $chatbotClient->id,
            'client_id' => $client->id,
            'marketing_campaign_id' => $campaign->id,
            'marketing_campaign_post_id' => $post->id,
        ], [
            'campaign_name' => 'test',
            'title' => 'test',
            'status' => 'sent_to_client',
        ]);

        $payload = [
            'client_id' => $client->id,
            'session_type' => 'marketing',
            'session_id' => $post->id,
            'message' => 'Approvato!',
            'type' => 'approval',
        ];

        $this->postJson('/api/v1/integrations/n8n/chatbot/client-message', $payload, $this->getHeaders())
            ->assertStatus(200);
            
        $this->assertEquals(\App\Enums\Social\MarketingCampaignPostStatus::ClientApproved->value, $post->refresh()->status->value);
    }

    public function test_change_request_type_updates_post_status()
    {
        $client = Client::factory()->create();
        $chatbotClient = ChatbotClient::firstOrCreate(['client_id' => $client->id], ['name' => $client->name]);

        $campaign = \App\Models\MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = \App\Models\MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => \App\Enums\Social\MarketingCampaignPostStatus::SentToClient->value,
        ]);
        
        $chatbotPost = ChatbotMarketingPost::firstOrCreate([
            'chatbot_client_id' => $chatbotClient->id,
            'client_id' => $client->id,
            'marketing_campaign_id' => $campaign->id,
            'marketing_campaign_post_id' => $post->id,
        ], [
            'campaign_name' => 'test',
            'title' => 'test',
            'status' => 'sent_to_client',
        ]);

        $payload = [
            'client_id' => $client->id,
            'session_type' => 'marketing',
            'session_id' => $post->id,
            'message' => 'Cambia il testo',
            'type' => 'change_request',
        ];

        $this->postJson('/api/v1/integrations/n8n/chatbot/client-message', $payload, $this->getHeaders())
            ->assertStatus(200);
            
        $this->assertEquals(\App\Enums\Social\MarketingCampaignPostStatus::ClientChangesRequested->value, $post->refresh()->status->value);
    }
}
