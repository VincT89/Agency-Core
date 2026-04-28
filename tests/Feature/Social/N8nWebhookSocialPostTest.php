<?php

namespace Tests\Feature\Social;

use App\Models\Client;
use App\Models\MarketingProject;
use App\Models\Project;
use App\Models\SocialPost;
use App\Models\SocialPostVersion;
use App\Models\EditorialPlanSlot;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class N8nWebhookSocialPostTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected MarketingProject $marketingProject;

    protected function setUp(): void
    {
        parent::setUp();

        // Evitiamo che le notifiche e i task logghino audit / query inattese durante i test del webhook
        Notification::fake();
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response('fake-image-content', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->adminUser = User::factory()->create(['role' => UserRole::Admin]);

        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);

        $this->marketingProject = MarketingProject::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Test Marketing Project',
            'type' => 'one_shot',
            'status' => 'draft',
        ]);
    }

    public function test_creates_social_post_deriving_project_id_from_marketing_project()
    {
        $payload = [
            'n8n_execution_id' => 'exec-12345',
            'marketing_project_id' => $this->marketingProject->id,
            'title' => 'Test Post One-Shot',
            'caption' => 'This is a test caption',
            'image_url' => 'https://example.com/image.jpg',
            'format' => '1080x1350',
        ];

        config(['services.n8n.token' => 'test-token']);

        $response = $this->withToken('test-token')->postJson('/api/v1/integrations/n8n/social/posts', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure(['data' => ['social_post_id', 'version_id', 'status']]);

        $this->assertDatabaseHas('social_posts', [
            'client_id' => $this->marketingProject->client_id,
            'project_id' => $this->marketingProject->project_id,
            'marketing_project_id' => $this->marketingProject->id,
            'title' => 'Test Post One-Shot',
        ]);

        $this->assertDatabaseHas('social_post_versions', [
            'external_id' => 'exec-12345',
            'version_number' => 1,
        ]);
    }

    public function test_same_n8n_execution_id_is_idempotent()
    {
        $payload = [
            'n8n_execution_id' => 'exec-idempotent-999',
            'marketing_project_id' => $this->marketingProject->id,
            'title' => 'Test Idempotency',
            'caption' => 'Should only create once',
            'image_url' => 'https://example.com/image.jpg',
        ];

        config(['services.n8n.token' => 'test-token']);

        // First request
        $response1 = $this->withToken('test-token')->postJson('/api/v1/integrations/n8n/social/posts', $payload);
        $response1->assertStatus(201);
        
        $postId = $response1->json('data.social_post_id');
        $versionId = $response1->json('data.version_id');

        $this->assertEquals(1, SocialPost::count());
        $this->assertEquals(1, SocialPostVersion::count());

        // Second request with SAME execution id
        $response2 = $this->withToken('test-token')->postJson('/api/v1/integrations/n8n/social/posts', $payload);
        
        $response2->assertStatus(200)
                  ->assertJsonFragment([
                      'idempotent' => true,
                      'social_post_id' => $postId,
                      'version_id' => $versionId,
                  ]);

        // Asserts NO DUPLICATES were created
        $this->assertEquals(1, SocialPost::count());
        $this->assertEquals(1, SocialPostVersion::count());
    }

    public function test_same_slot_with_new_execution_id_creates_new_version_not_new_post()
    {
        $plan = \App\Models\EditorialPlan::create([
            'project_id' => $this->marketingProject->project_id,
            'client_id' => $this->marketingProject->client_id,
            'marketing_project_id' => $this->marketingProject->id,
            'title' => 'Test Plan',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'status' => 'draft',
        ]);

        $slot = EditorialPlanSlot::create([
            'editorial_plan_id' => $plan->id, 
            'project_id' => $this->marketingProject->project_id,
            'client_id' => $this->marketingProject->client_id,
            'planned_date' => now()->toDateString(),
            'planned_time' => '10:00:00',
            'status' => 'empty',
        ]);

        // Request 1: creates the post for the slot
        $payload1 = [
            'n8n_execution_id' => 'exec-slot-1',
            'marketing_project_id' => $this->marketingProject->id,
            'editorial_plan_slot_id' => $slot->id,
            'title' => 'First Generation',
            'caption' => 'Version 1',
            'image_url' => 'https://example.com/v1.jpg',
        ];

        config(['services.n8n.token' => 'test-token']);

        $response1 = $this->withToken('test-token')->postJson('/api/v1/integrations/n8n/social/posts', $payload1);
        $response1->assertStatus(201);

        $postId = $response1->json('data.social_post_id');

        $this->assertEquals(1, SocialPost::count());
        $this->assertEquals(1, SocialPostVersion::count());

        // Request 2: NEW execution ID, SAME slot ID
        $payload2 = [
            'n8n_execution_id' => 'exec-slot-2',
            'marketing_project_id' => $this->marketingProject->id,
            'editorial_plan_slot_id' => $slot->id,
            'title' => 'Second Generation',
            'caption' => 'Version 2 with revisions',
            'image_url' => 'https://example.com/v2.jpg',
        ];

        $response2 = $this->withToken('test-token')->postJson('/api/v1/integrations/n8n/social/posts', $payload2);
        
        // AddSocialPostVersionFromN8nAction should have been called
        $response2->assertStatus(201);

        // Asserts exactly ONE post exists, but TWO versions exist
        $this->assertEquals(1, SocialPost::count());
        $this->assertEquals(2, SocialPostVersion::count());

        // Verify the second response maps to the SAME post but NEW version
        $this->assertEquals($postId, $response2->json('data.social_post_id'));
        $this->assertNotEquals($response1->json('data.version_id'), $response2->json('data.version_id'));

        $this->assertDatabaseHas('social_post_versions', [
            'external_id' => 'exec-slot-2',
            'version_number' => 2,
        ]);
    }

    public function test_race_condition_on_external_id_returns_idempotent_response()
    {
        $payload = [
            'n8n_execution_id' => 'exec-race-123',
            'marketing_project_id' => $this->marketingProject->id,
            'title' => 'Test Race Condition',
            'caption' => 'Should handle DB unique constraint',
            'image_url' => 'https://example.com/image.jpg',
        ];

        config(['services.n8n.token' => 'test-token']);

        // First we simulate the race by directly creating the version in the DB
        // just before the Action tries to create it, but after the first idempotency check.
        // The easiest way is to mock the Action or DB, but here we can just create the post 
        // and let the test try to insert the same execution ID if we were to mock it.
        // Actually, we can use an eloquent event to insert a conflicting record right before creation!
        
        SocialPostVersion::creating(function ($model) use ($payload) {
            static $runOnce = false;
            if (!$runOnce && $model->external_id === 'exec-race-123') {
                $runOnce = true;
                // Insert directly into DB bypassing eloquent events
                \Illuminate\Support\Facades\DB::table('social_post_versions')->insert([
                    'social_post_id' => $model->social_post_id,
                    'external_id' => 'exec-race-123',
                    'version_number' => 1,
                    'caption' => 'Inserted by another thread',
                    'image_path' => null,
                    'source' => 'n8n',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $response = $this->withToken('test-token')->postJson('/api/v1/integrations/n8n/social/posts', $payload);

        // Even though it triggered a QueryException internally, it should catch it and return 200 Idempotent
        $response->assertStatus(200)
                 ->assertJsonFragment(['idempotent' => true]);
                 
        // Remove the listener to not affect other tests
        SocialPostVersion::flushEventListeners();
    }
}
