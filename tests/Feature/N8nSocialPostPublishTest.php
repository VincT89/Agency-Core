<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\SocialPost;
use App\Enums\Social\SocialPostStatus;

class N8nSocialPostPublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_a_social_post_from_n8n(): void
    {
        $post = SocialPost::factory()->create([
            'status' => SocialPostStatus::ReadyToPublish,
        ]);

        $payload = [
            'n8n_execution_id' => 'exec_123',
            'platform' => 'instagram',
            'external_post_id' => '17895695668',
            'external_post_url' => 'https://instagram.com/p/1234',
            'published_at' => now()->toDateTimeString(),
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . env('N8N_API_TOKEN', 'test-token'),
        ])->postJson(route('social.posts.publish.store', $post), $payload);

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'published')
                 ->assertJsonPath('data.publication_status', 'published')
                 ->assertJsonPath('data.external_post_id', '17895695668');

        $this->assertDatabaseHas('social_posts', [
            'id' => $post->id,
            'status' => 'published',
            'publication_status' => 'published',
            'published_platform' => 'instagram',
            'external_post_id' => '17895695668',
            'external_post_url' => 'https://instagram.com/p/1234',
        ]);
    }

    public function test_it_is_idempotent_on_publish(): void
    {
        $post = SocialPost::factory()->create([
            'status' => SocialPostStatus::Published,
            'publication_status' => 'published',
            'published_platform' => 'instagram',
            'external_post_id' => '17895695668',
        ]);

        $payload = [
            'n8n_execution_id' => 'exec_123',
            'platform' => 'instagram',
            'external_post_id' => '17895695668',
            'external_post_url' => 'https://instagram.com/p/1234',
            'published_at' => now()->toDateTimeString(),
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . env('N8N_API_TOKEN', 'test-token'),
        ])->postJson(route('social.posts.publish.store', $post), $payload);

        $response->assertStatus(200);
        
        $this->assertEquals(1, SocialPost::count());
    }
}
