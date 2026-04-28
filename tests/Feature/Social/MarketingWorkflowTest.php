<?php

namespace Tests\Feature\Social;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\ClientReviewToken;
use App\Enums\UserRole;
use App\Enums\Social\MarketingProjectStatus;
use App\Domain\Social\Actions\CreateMarketingProjectAction;
use App\Domain\Social\Actions\SubmitMarketingProjectToN8nAction;
use App\Domain\Social\Actions\ReceiveSocialPostFromN8nAction;
use App\Domain\Social\Actions\ClientRespondToSocialPostAction;
use App\Domain\Social\Actions\ClientRespondToEditorialPlanAction;
use App\Domain\Social\Actions\CreateEditorialPlanAction;
use App\Domain\Social\Actions\CreateEditorialPlanSlotsAction;
use App\Domain\Social\Actions\MarkEditorialSlotPublishedAction;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class MarketingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $marketing;
    protected Client $client;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        $this->marketing = User::factory()->create(['role' => UserRole::Marketing, 'status' => 'active']);
        
        $this->client = Client::factory()->create(['status' => 'active']);
        
        $this->client->socialAccounts()->create([
            'platform' => \App\Enums\Social\SocialPlatform::Facebook->value,
            'is_ready_to_publish' => true,
            'access_status' => \App\Enums\Social\SocialAccessStatus::ReadyToPublish->value,
            'access_method' => \App\Enums\Social\SocialAccessMethod::MetaBusiness->value,
            'business_manager_id' => '12345',
        ]);
        $this->client->socialAccounts()->create([
            'platform' => \App\Enums\Social\SocialPlatform::Instagram->value,
            'is_ready_to_publish' => true,
            'access_status' => \App\Enums\Social\SocialAccessStatus::ReadyToPublish->value,
            'access_method' => \App\Enums\Social\SocialAccessMethod::MetaBusiness->value,
            'business_manager_id' => '12345',
        ]);

        $this->project = Project::factory()->create(['client_id' => $this->client->id]);
        
        Event::fake([
            \App\Events\SocialPostApprovedByClient::class,
            \App\Events\EditorialPlanApprovedByClient::class,
            \App\Events\EditorialSlotPublished::class,
        ]);
        
        Notification::fake();
        \Illuminate\Support\Facades\Http::fake();
        config([
            'services.n8n.generate_social_post_webhook_url' => 'http://test.local/one-shot',
            'services.n8n.generate_editorial_plan_webhook_url' => 'http://test.local/plan',
        ]);
    }

    public function test_one_shot_workflow_end_to_end()
    {
        $this->actingAs($this->marketing);

        // 1. Create
        $createAction = app(CreateMarketingProjectAction::class);
        $project = $createAction->execute([
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'title' => 'Test One Shot',
            'type' => 'one_shot',
            'platforms' => ['instagram'],
            'brief' => 'test brief',
        ]);

        $this->assertEquals(MarketingProjectStatus::Draft->value, $project->status->value);

        // 2. Submit
        $submitAction = app(SubmitMarketingProjectToN8nAction::class);
        $submitAction->execute($project);
        $this->assertEquals(MarketingProjectStatus::SubmittedToN8n->value, $project->fresh()->status->value);

        // 3. Receive Webhook
        $receiveAction = app(ReceiveSocialPostFromN8nAction::class);
        $post = $receiveAction->execute([
            'marketing_project_id' => $project->id,
            'title' => 'Generato da n8n',
            'caption' => 'Test',
        ]);

        $this->assertEquals(\App\Enums\Social\SocialPostStatus::InternalReview, $post->fresh()->status);
        $this->assertEquals(MarketingProjectStatus::PostsReceived->value, $project->fresh()->status->value);

        // 4. Token & Client Approve
        $token = ClientReviewToken::create([
            'reviewable_id' => $post->id,
            'reviewable_type' => \App\Models\SocialPost::class,
            'token' => \Illuminate\Support\Str::random(32),
            'expires_at' => now()->addDays(7),
        ]);

        $clientRespondAction = app(ClientRespondToSocialPostAction::class);
        $clientRespondAction->execute($token, 'approve', 'Tutto ok');

        Event::assertDispatched(\App\Events\SocialPostApprovedByClient::class, function ($e) use ($post) {
            return $e->post->id === $post->id;
        });

        $this->assertEquals(\App\Enums\Social\SocialPostStatus::ClientApproved, $post->fresh()->status);
        $this->assertEquals(MarketingProjectStatus::ClientApproved->value, $project->fresh()->status->value);
    }

    public function test_editorial_plan_workflow_end_to_end()
    {
        $this->actingAs($this->marketing);

        $createProjectAction = app(CreateMarketingProjectAction::class);
        $project = $createProjectAction->execute([
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'title' => 'Test Plan',
            'type' => 'editorial_plan',
            'platforms' => ['facebook', 'instagram'],
        ]);

        $createPlanAction = app(CreateEditorialPlanAction::class);
        $plan = $createPlanAction->execute($project, [
            'duration_days' => 30,
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(31)->format('Y-m-d'),
            'post_count' => 2,
        ]);

        $createSlotsAction = app(CreateEditorialPlanSlotsAction::class);
        $createSlotsAction->execute($plan, [
            ['date' => now()->addDays(2)->format('Y-m-d'), 'time' => '10:00', 'platforms' => ['instagram'], 'topic' => 'T1'],
            ['date' => now()->addDays(5)->format('Y-m-d'), 'time' => '10:00', 'platforms' => ['facebook'], 'topic' => 'T2'],
        ]);

        $this->assertCount(2, $plan->slots);

        $receiveAction = app(ReceiveSocialPostFromN8nAction::class);
        $post1 = $receiveAction->execute([
            'marketing_project_id' => $project->id,
            'editorial_plan_id' => $plan->id,
            'editorial_plan_slot_id' => $plan->slots[0]->id,
            'title' => 'P1',
        ]);
        $post2 = $receiveAction->execute([
            'marketing_project_id' => $project->id,
            'editorial_plan_id' => $plan->id,
            'editorial_plan_slot_id' => $plan->slots[1]->id,
            'title' => 'P2',
        ]);

        $this->assertEquals(\App\Enums\Social\EditorialPlanStatus::PostsReceived->value, $plan->fresh()->status->value);

        // Client Approve
        $clientAction = app(ClientRespondToEditorialPlanAction::class);
        $clientAction->execute($plan, 'approve');

        Event::assertDispatched(\App\Events\EditorialPlanApprovedByClient::class);
        
        $this->assertEquals(\App\Enums\Social\EditorialPlanStatus::ClientApproved->value, $plan->fresh()->status->value);
        $this->assertEquals(\App\Enums\Social\EditorialPlanSlotStatus::ClientApproved->value, $plan->slots[0]->fresh()->status->value);
    }

    public function test_n8n_webhook_idempotency_for_slots()
    {
        $this->actingAs($this->marketing);

        $project = \App\Models\MarketingProject::create([
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'title' => 'Test',
            'type' => 'editorial_plan',
            'status' => MarketingProjectStatus::Draft->value,
            'platforms' => ['instagram'],
        ]);

        $plan = \App\Models\EditorialPlan::create([
            'marketing_project_id' => $project->id,
            'duration_days' => 30,
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(31)->format('Y-m-d'),
            'status' => \App\Enums\Social\EditorialPlanStatus::Draft->value,
        ]);

        $slot = \App\Models\EditorialPlanSlot::create([
            'editorial_plan_id' => $plan->id,
            'scheduled_date' => now()->addDays(2),
            'scheduled_time' => '10:00',
            'platforms' => ['instagram'],
            'status' => \App\Enums\Social\EditorialPlanSlotStatus::Empty->value,
        ]);

        $receiveAction = app(ReceiveSocialPostFromN8nAction::class);
        
        // Primo webhook
        $post = $receiveAction->execute([
            'marketing_project_id' => $project->id,
            'editorial_plan_id' => $plan->id,
            'editorial_plan_slot_id' => $slot->id,
            'title' => 'First try',
            'caption' => 'Caption 1',
        ]);

        $this->assertEquals(1, $post->versions()->count());
        $this->assertEquals(1, \App\Models\SocialPost::count());

        // Secondo webhook (stesso slot)
        $receiveAction->execute([
            'marketing_project_id' => $project->id,
            'editorial_plan_id' => $plan->id,
            'editorial_plan_slot_id' => $slot->id,
            'title' => 'Second try',
            'caption' => 'Caption 2',
        ]);

        // Nessun nuovo post creato, ma una versione aggiuntiva sul post esistente
        $this->assertEquals(1, \App\Models\SocialPost::count());
        $this->assertEquals(2, $post->fresh()->versions()->count());
        $this->assertEquals('Caption 2', $post->fresh()->currentVersion->caption);
    }

    public function test_client_token_expiration_and_invalidity()
    {
        $post = \App\Models\SocialPost::create([
            'project_id' => $this->project->id,
            'client_id' => $this->client->id,
            'title' => 'Test',
            'status' => \App\Enums\Social\SocialPostStatus::InternalReview,
            'created_by' => $this->marketing->id,
        ]);
        
        $token = ClientReviewToken::create([
            'reviewable_id' => $post->id,
            'reviewable_type' => \App\Models\SocialPost::class,
            'token' => 'VALID_TOKEN_123',
            'expires_at' => now()->subDay(), // EXPIRATO
        ]);

        // Route: GET /review/{token}
        $response = $this->get('/review/VALID_TOKEN_123');
        $response->assertStatus(403);
        $response->assertSee('scaduto');

        $response = $this->get('/review/INVALID_TOKEN');
        $response->assertStatus(404);
    }

    public function test_partial_editorial_plan_approval_does_not_promote_plan_status()
    {
        $this->actingAs($this->marketing);

        $project = \App\Models\MarketingProject::create([
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'title' => 'Test',
            'type' => 'editorial_plan',
            'status' => MarketingProjectStatus::Draft->value,
            'platforms' => ['instagram'],
        ]);
        $plan = \App\Models\EditorialPlan::create([
            'marketing_project_id' => $project->id,
            'duration_days' => 30,
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(31)->format('Y-m-d'),
            'status' => \App\Enums\Social\EditorialPlanStatus::PostsReceived->value,
        ]);
        
        $slot1 = \App\Models\EditorialPlanSlot::create([
            'editorial_plan_id' => $plan->id,
            'scheduled_date' => now()->addDays(2),
            'scheduled_time' => '10:00',
            'platforms' => ['instagram'],
            'status' => \App\Enums\Social\EditorialPlanSlotStatus::Empty->value,
        ]);
        $slot2 = \App\Models\EditorialPlanSlot::create([
            'editorial_plan_id' => $plan->id,
            'scheduled_date' => now()->addDays(3),
            'scheduled_time' => '10:00',
            'platforms' => ['instagram'],
            'status' => \App\Enums\Social\EditorialPlanSlotStatus::Empty->value,
        ]);

        $post1 = \App\Models\SocialPost::create([
            'project_id' => $this->project->id,
            'client_id' => $this->client->id,
            'editorial_plan_slot_id' => $slot1->id,
            'editorial_plan_id' => $plan->id,
            'marketing_project_id' => $project->id,
            'title' => 'Test 1',
            'status' => \App\Enums\Social\SocialPostStatus::InternalReview,
            'created_by' => $this->admin->id,
        ]);
        $post2 = \App\Models\SocialPost::create([
            'project_id' => $this->project->id,
            'client_id' => $this->client->id,
            'editorial_plan_slot_id' => $slot2->id,
            'editorial_plan_id' => $plan->id,
            'marketing_project_id' => $project->id,
            'title' => 'Test 2',
            'status' => \App\Enums\Social\SocialPostStatus::InternalReview,
            'created_by' => $this->admin->id,
        ]);

        $token1 = ClientReviewToken::create(['reviewable_id' => $post1->id, 'reviewable_type' => \App\Models\SocialPost::class, 'token' => 'T1']);
        $token2 = ClientReviewToken::create(['reviewable_id' => $post2->id, 'reviewable_type' => \App\Models\SocialPost::class, 'token' => 'T2']);

        $clientAction = app(ClientRespondToSocialPostAction::class);
        
        // Approva solo il primo
        $clientAction->execute($token1, 'approve');

        Event::assertDispatched(\App\Events\SocialPostApprovedByClient::class);
        Event::assertNotDispatched(\App\Events\EditorialPlanApprovedByClient::class);

        $this->assertNotEquals(\App\Enums\Social\EditorialPlanStatus::ClientApproved->value, $plan->fresh()->status->value);
        $this->assertNotEquals(\App\Enums\Social\MarketingProjectStatus::ClientApproved->value, $project->fresh()->status->value);

        // Richiede modifiche sul secondo
        $clientAction->execute($token2, 'request_changes');

        $this->assertEquals(\App\Enums\Social\EditorialPlanStatus::ClientChangesRequested->value, $plan->fresh()->status->value);
    }

    public function test_double_client_approval_does_not_create_duplicate_tasks()
    {
        $this->actingAs($this->marketing);

        $project = \App\Models\MarketingProject::create([
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'title' => 'Test',
            'type' => 'editorial_plan',
            'status' => MarketingProjectStatus::Draft->value,
            'platforms' => ['instagram'],
        ]);
        $plan = \App\Models\EditorialPlan::create([
            'marketing_project_id' => $project->id,
            'duration_days' => 30,
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(31)->format('Y-m-d'),
            'status' => \App\Enums\Social\EditorialPlanStatus::PostsReceived->value,
        ]);
        $slot = \App\Models\EditorialPlanSlot::create([
            'editorial_plan_id' => $plan->id,
            'scheduled_date' => now()->addDays(2),
            'scheduled_time' => '10:00',
            'platforms' => ['instagram'],
            'status' => \App\Enums\Social\EditorialPlanSlotStatus::Empty->value,
        ]);

        $clientAction = app(ClientRespondToEditorialPlanAction::class);

        // Prima approvazione
        $clientAction->execute($plan, 'approve');
        Event::assertDispatched(\App\Events\EditorialPlanApprovedByClient::class);

        // Reset eventi
        Event::fake([
            \App\Events\EditorialPlanApprovedByClient::class,
        ]);

        // Seconda approvazione simulata
        $clientAction->execute($plan, 'approve');
        
        // Verifica che NON sia stato dispatchato di nuovo
        Event::assertNotDispatched(\App\Events\EditorialPlanApprovedByClient::class);
    }
}
