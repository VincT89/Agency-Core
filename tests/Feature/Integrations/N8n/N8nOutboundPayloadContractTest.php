<?php

namespace Tests\Feature\Integrations\N8n;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Services\Integrations\N8n\N8nClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class N8nOutboundPayloadContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        config(['services.n8n.submit_marketing_campaign_post_webhook_url' => 'https://n8n.local/webhook/submit']);
        config(['services.n8n.regenerate_social_post_webhook_url' => 'https://n8n.local/webhook/regenerate']);
    }

    public function test_submit_marketing_campaign_post_sends_stable_payload(): void
    {
        Http::fake([
            'https://n8n.local/webhook/submit' => Http::response(['success' => true], 200),
        ]);

        $client = Client::factory()->create([
            'name' => 'Acme Test Srl',
        ]);
        
        $campaign = MarketingCampaign::factory()->create([
            'client_id' => $client->id,
            'name' => 'Campagna Estiva',
        ]);
        
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'title' => 'Post di test',
            'description' => 'Descrizione post',
            'content_type' => 'post',
            'publishing_platforms' => ['instagram', 'facebook'],
        ]);

        // Assicurati che non ci siano media mockati qui, ma solo i default vuoti o testati.
        
        $action = app(\App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction::class);
        $action->execute($post);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($post, $campaign, $client) {
            $data = $request->data();

            return $data['type'] === 'marketing_campaign_post' &&
                   isset($data['request_id']) &&
                   $data['campaign']['id'] === $campaign->id &&
                   $data['campaign']['name'] === 'Campagna Estiva' &&
                   $data['client']['id'] === $client->id &&
                   $data['client']['name'] === 'Acme Test Srl' &&
                   $data['post']['id'] === $post->id &&
                   $data['post']['title'] === 'Post di test' &&
                   $data['post']['description'] === 'Descrizione post' &&
                   $data['post']['content_type'] === 'post' &&
                   $data['post']['publishing_platforms'] === ['instagram', 'facebook'] &&
                   isset($data['post']['media_count']) &&
                   isset($data['post']['media_items']) &&
                   isset($data['callback_url']);
        });
    }

    public function test_submit_payload_includes_nullable_optional_client_fields(): void
    {
        Http::fake([
            'https://n8n.local/webhook/submit' => Http::response(['success' => true], 200),
        ]);

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id]);

        $action = app(\App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction::class);
        $action->execute($post);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            
            return array_key_exists('logo_url', $data['client']) &&
                   array_key_exists('activity_description', $data['client']);
        });
    }

    public function test_submit_payload_prefers_media_items_for_multiple_media(): void
    {
        Http::fake([
            'https://n8n.local/webhook/submit' => Http::response(['success' => true], 200),
        ]);

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id]);
        
        \App\Models\MarketingCampaignPostMedia::create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'img1.jpg',
            'sort_order' => 1,
            'media_type' => 'image',
            'mime_type' => 'image/jpeg',
        ]);
        
        \App\Models\MarketingCampaignPostMedia::create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'img2.jpg',
            'sort_order' => 2,
            'media_type' => 'image',
            'mime_type' => 'image/jpeg',
        ]);

        $action = app(\App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction::class);
        $action->execute($post);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            
            return is_array($data['post']['media_items']) &&
                   count($data['post']['media_items']) === 2 &&
                   isset($data['post']['media']) && // Fallback
                   is_array($data['post']['media']); // E' un array associativo
        });
    }

    public function test_regeneration_sends_stable_payload(): void
    {
        Http::fake([
            'https://n8n.local/webhook/regenerate' => Http::response(['success' => true], 200),
        ]);

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Generated->value,
        ]);

        // Simula che il post ha una versione
        $version = \App\Models\MarketingCampaignPostVersion::create([
            'marketing_campaign_post_id' => $post->id,
            'version_number' => 1,
            'caption' => 'Test caption',
            'image_url' => 'http://test.local/img.jpg',
        ]);
        
        $post->update(['current_version_id' => $version->id]);

        $payload = [
            'regeneration_type' => 'full',
            'prompt' => 'Please rewrite this',
        ];

        $user = \App\Models\User::factory()->create();

        $action = app(\App\Domain\Social\Actions\RequestMarketingCampaignPostRegenerationAction::class);
        $action->execute($post, $user, 'full', 'Please rewrite this');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($post, $payload, $version) {
            $data = $request->data();
            
            return $data['type'] === 'marketing_campaign_post_regeneration' &&
                   $data['post_id'] === $post->id &&
                   isset($data['request_id']) &&
                   $data['regeneration_type'] === 'full' &&
                   $data['prompt'] === 'Please rewrite this' &&
                   isset($data['campaign']) &&
                   isset($data['client']) &&
                   isset($data['post']) &&
                   isset($data['current_version']) &&
                   $data['current_version']['id'] === $version->id &&
                   isset($data['callback_url']);
        });
    }
}
