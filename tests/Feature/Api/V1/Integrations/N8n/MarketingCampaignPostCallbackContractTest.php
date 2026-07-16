<?php

namespace Tests\Feature\Api\V1\Integrations\N8n;

use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingCampaignPostCallbackContractTest extends TestCase
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

    public function test_full_regeneration_requires_caption_and_image()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id, 'n8n_request_id' => 'test-req-id', 'status' => 'submitted_to_n8n']);

        $payload = [
            'request_id' => 'test-req-id',
            'regeneration_type' => 'full',
            'caption' => 'New text',
            'image_url' => 'https://example.com/img.jpg',
        ];

        $response = $this->postJson(
            route('api.v1.integrations.n8n.marketing-campaign-posts.versions.store', $post),
            $payload,
            $this->getHeaders()
        );

        $response->assertStatus(201);
    }

    public function test_caption_regeneration_requires_only_caption()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id, 'n8n_request_id' => 'test-req-id', 'status' => 'submitted_to_n8n']);
        
        // Simula la versione esistente per ereditare l'immagine
        $version = MarketingCampaignPostVersion::create([
            'marketing_campaign_post_id' => $post->id,
            'version_number' => 1,
            'caption' => 'Old',
            'image_url' => 'https://example.com/old.jpg',
        ]);
        
        $post->update(['current_version_id' => $version->id]);

        $payload = [
            'request_id' => 'test-req-id',
            'regeneration_type' => 'caption',
            'caption' => 'New text only',
        ];

        $response = $this->postJson(
            route('api.v1.integrations.n8n.marketing-campaign-posts.versions.store', $post),
            $payload,
            $this->getHeaders()
        );

        $response->assertStatus(201);
        $this->assertEquals('https://example.com/old.jpg', $post->refresh()->currentVersion->image_url);
    }

    public function test_image_regeneration_requires_only_image()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id, 'n8n_request_id' => 'test-req-id', 'status' => 'submitted_to_n8n']);

        $version = MarketingCampaignPostVersion::create([
            'marketing_campaign_post_id' => $post->id,
            'version_number' => 1,
            'caption' => 'Old',
            'image_url' => 'https://example.com/old.jpg',
        ]);
        
        $post->update(['current_version_id' => $version->id]);

        $payload = [
            'request_id' => 'test-req-id',
            'regeneration_type' => 'image',
            'image_url' => 'https://example.com/new.jpg',
        ];

        $response = $this->postJson(
            route('api.v1.integrations.n8n.marketing-campaign-posts.versions.store', $post),
            $payload,
            $this->getHeaders()
        );

        $response->assertStatus(201);
        $this->assertEquals('Old', $post->refresh()->currentVersion->caption);
    }

    public function test_alias_normalization_for_caption_and_image()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id, 'n8n_request_id' => 'test-req-id', 'status' => 'submitted_to_n8n']);

        $payload = [
            'request_id' => 'test-req-id',
            'regeneration_type' => 'full',
            'text' => 'Using alias for caption',
            'images' => 'https://example.com/alias.jpg', // alias per image_urls (string converted to array)
        ];

        $response = $this->postJson(
            route('api.v1.integrations.n8n.marketing-campaign-posts.versions.store', $post),
            $payload,
            $this->getHeaders()
        );

        $response->assertStatus(201);
        
        $version = $post->refresh()->currentVersion;
        $this->assertEquals('Using alias for caption', $version->caption);
        $this->assertIsArray($version->image_urls);
        $this->assertEquals('https://example.com/alias.jpg', $version->image_urls[0]);
    }

    public function test_nested_payload_is_lifted_to_root()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id, 'n8n_request_id' => 'test-req-id', 'status' => 'submitted_to_n8n']);

        $payload = [
            'data' => [
                'request_id' => 'test-req-id',
                'regeneration_type' => 'full',
                'caption' => 'Nested data',
                'image_url' => 'https://example.com/nested.jpg',
            ]
        ];

        $response = $this->postJson(
            route('api.v1.integrations.n8n.marketing-campaign-posts.versions.store', $post),
            $payload,
            $this->getHeaders()
        );

        $response->assertStatus(201);
    }

    public function test_wrong_request_id_returns_409()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id, 'n8n_request_id' => 'test-req-id', 'status' => 'submitted_to_n8n']);

        $payload = [
            'request_id' => 'wrong-id',
            'regeneration_type' => 'full',
            'caption' => 'New text',
            'image_url' => 'https://example.com/img.jpg',
        ];

        $response = $this->postJson(
            route('api.v1.integrations.n8n.marketing-campaign-posts.versions.store', $post),
            $payload,
            $this->getHeaders()
        );

        $response->assertStatus(409);
    }

    public function test_double_callback_with_same_external_generation_id_does_not_create_double_version()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id, 'n8n_request_id' => 'test-req-id', 'status' => 'submitted_to_n8n']);

        $payload = [
            'request_id' => 'test-req-id',
            'regeneration_type' => 'full',
            'caption' => 'First attempt',
            'image_url' => 'https://example.com/img.jpg',
            'external_generation_id' => 'ext-123',
        ];

        $this->postJson(
            route('api.v1.integrations.n8n.marketing-campaign-posts.versions.store', $post),
            $payload,
            $this->getHeaders()
        )->assertStatus(201);

        $versionsCount = $post->versions()->count();

        // Invio di nuovo lo stesso
        $this->postJson(
            route('api.v1.integrations.n8n.marketing-campaign-posts.versions.store', $post),
            $payload,
            $this->getHeaders()
        )->assertStatus(200); // Idempotente o almeno non errore (nel controller è 200)

        $this->assertEquals($versionsCount, $post->versions()->count());
    }

    public function test_failed_callback_with_correct_request_id_returns_200()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id, 'n8n_request_id' => 'test-req-id', 'status' => 'submitted_to_n8n']);

        $payload = [
            'request_id' => 'test-req-id',
            'error' => 'Generation failed due to timeout',
        ];

        $this->postJson(
            route('api.v1.integrations.n8n.marketing-campaign-posts.failed', $post),
            $payload,
            $this->getHeaders()
        )->assertStatus(200);

        $this->assertEquals('Generation failed due to timeout', $post->refresh()->n8n_error);
    }

    public function test_failed_callback_with_wrong_request_id_returns_400()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id, 'n8n_request_id' => 'test-req-id', 'status' => 'submitted_to_n8n']);

        $payload = [
            'request_id' => 'wrong-id',
            'error' => 'Generation failed due to timeout',
        ];

        $this->postJson(
            route('api.v1.integrations.n8n.marketing-campaign-posts.failed', $post),
            $payload,
            $this->getHeaders()
        )->assertStatus(400);
    }

    public function test_failed_callback_without_error_returns_422()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id, 'n8n_request_id' => 'test-req-id', 'status' => 'submitted_to_n8n']);

        $payload = [
            'request_id' => 'test-req-id',
            // missing error
        ];

        $this->postJson(
            route('api.v1.integrations.n8n.marketing-campaign-posts.failed', $post),
            $payload,
            $this->getHeaders()
        )->assertStatus(422);
    }
    public function test_failed_callback_when_already_approved_returns_ignored()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id, 'n8n_request_id' => 'test-req-id', 'status' => 'approved']);

        $payload = [
            'request_id' => 'test-req-id',
            'error' => 'Generation failed late',
        ];

        $this->postJson(
            route('api.v1.integrations.n8n.marketing-campaign-posts.failed', $post),
            $payload,
            $this->getHeaders()
        )->assertStatus(200)->assertJson(['status' => 'ignored']);
    }
}
