<?php

namespace Tests\Feature\Social;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\SocialPost;
use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;

class SocialPostWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock storage to prevent real file operations
        Storage::fake('public');
        
        // Mock n8n config token
        config(['services.n8n.token' => 'test-token']);
    }

    public function test_api_receives_post_from_n8n_and_creates_models()
    {
        Notification::fake();

        // Create admin user for notifications
        $admin = User::factory()->create(['role' => \App\Enums\UserRole::Admin]);
        $marketing = User::factory()->create(['role' => \App\Enums\UserRole::Marketing]);
        
        // Mock image download
        Http::fake([
            'example.com/image.jpg' => Http::response('fake-image-content', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);

        $payload = [
            'external_id' => 'n8n-123',
            'project_id' => $project->id,
            'title' => 'Test Post',
            'caption' => 'Test Caption',
            'image_url' => 'https://example.com/image.jpg'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token'
        ])->postJson('/api/v1/integrations/n8n/social/posts', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure(['ok', 'data' => ['social_post_id', 'version_id', 'status']]);

        $this->assertDatabaseHas('social_posts', [
            'title' => 'Test Post',
            'project_id' => $project->id,
            'status' => 'internal_review'
        ]);

        $this->assertDatabaseHas('social_post_versions', [
            'version_number' => 1,
            'caption' => 'Test Caption'
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'social_post.received'
        ]);
        
        Notification::assertSentTo(
            User::whereIn('role', [\App\Enums\UserRole::Admin, \App\Enums\UserRole::Marketing])->get(),
            \App\Notifications\SocialPostWorkflowNotification::class
        );
    }
}
