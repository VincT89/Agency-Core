<?php

namespace Tests\Feature\Integrations\N8n;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class N8nCallbackValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_n8n_callback_fails_with_invalid_request_id()
    {
        $user = User::factory()->create();
        $client = \App\Models\Client::create(['name' => 'Test', 'activity_description' => 'Test', 'slug' => 'test-slug']);
        $campaign = MarketingCampaign::create(['client_id' => $client->id, 'name' => 'Test', 'status' => 'draft']);
        $post = MarketingCampaignPost::create([
            'marketing_campaign_id' => $campaign->id,
            'title' => 'test',
            'status' => 'draft',
            'content_type' => 'post',
            'n8n_request_id' => 'correct-uuid-1234',
        ]);

        $payload = [
            'post_id' => $post->id,
            'request_id' => 'wrong-uuid-5678',
            'regeneration_type' => 'full',
            'version_number' => 1,
            'title' => 'Test Title',
            'caption' => 'Test Caption',
        ];

        $token = 'test-token';
        config(['services.n8n.token' => $token]);

        $response = $this->actingAs($user)->postJson(
            route('api.v1.integrations.n8n.marketing-campaign-posts.result'),
            $payload,
            ['Authorization' => 'Bearer ' . $token]
        );

        $response->assertStatus(409);
    }
}
