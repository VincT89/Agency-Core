<?php

namespace Tests\Feature\Social;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\MarketingProject;
use App\Enums\UserRole;
use App\Enums\Social\MarketingProjectStatus;
use App\Domain\Social\Actions\SubmitMarketingProjectToN8nAction;
use App\Services\Integrations\N8n\N8nClient;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SendN8nRequestJob;
use Mockery;

class MarketingAsyncFailureTest extends TestCase
{
    use RefreshDatabase;

    protected User $marketing;
    protected Client $client;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->marketing = User::factory()->create(['role' => UserRole::Marketing, 'status' => 'active']);
        $this->client = Client::factory()->create(['status' => 'active']);
        $this->project = Project::factory()->create(['client_id' => $this->client->id]);
    }

    public function test_job_updates_status_to_failed_on_exception()
    {
        $marketingProject = MarketingProject::create([
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'title' => 'Test Failure',
            'type' => 'one_shot',
            'status' => MarketingProjectStatus::QueuedToN8n->value,
            'platforms' => [],
        ]);

        $mockClient = Mockery::mock(N8nClient::class);
        $mockClient->shouldReceive('requestSingleSocialPostGeneration')->andThrow(new \Exception('N8n down'));

        $job = new SendN8nRequestJob(['dummy' => 'payload'], $marketingProject->id, 'one_shot');
        
        // Execute the job manually with the mocked client
        $job->handle($mockClient);

        $this->assertEquals(MarketingProjectStatus::N8nFailed->value, $marketingProject->fresh()->status->value);
    }

    public function test_submit_action_allows_retry_and_regenerates_id()
    {
        Queue::fake();

        $marketingProject = MarketingProject::create([
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'title' => 'Test Retry',
            'type' => 'one_shot',
            'status' => MarketingProjectStatus::N8nFailed->value,
            'platforms' => [],
            'n8n_request_id' => 'OLD_ID',
        ]);

        $submitAction = app(SubmitMarketingProjectToN8nAction::class);
        $submitAction->execute($marketingProject);

        $marketingProject->refresh();

        // 1. Lo stato deve tornare a QueuedToN8n
        $this->assertEquals(MarketingProjectStatus::QueuedToN8n->value, $marketingProject->status->value);
        
        // 2. Deve aver generato un nuovo n8n_request_id
        $this->assertNotEquals('OLD_ID', $marketingProject->n8n_request_id);
        $this->assertNotNull($marketingProject->n8n_request_id);

        // 3. Deve aver lanciato il Job
        Queue::assertPushed(SendN8nRequestJob::class);
    }

    public function test_job_updates_editorial_plan_status_to_failed_on_exception()
    {
        $marketingProject = MarketingProject::create([
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'title' => 'Test Failure Plan',
            'type' => 'editorial_plan',
            'status' => MarketingProjectStatus::QueuedToN8n->value,
            'platforms' => [],
        ]);

        $plan = \App\Models\EditorialPlan::create([
            'marketing_project_id' => $marketingProject->id,
            'duration_days' => 30,
            'status' => \App\Enums\Social\EditorialPlanStatus::QueuedToN8n->value,
        ]);

        $slot = \App\Models\EditorialPlanSlot::create([
            'editorial_plan_id' => $plan->id,
            'scheduled_date' => now()->addDays(2),
            'scheduled_time' => '10:00',
            'platforms' => ['instagram'],
            'status' => \App\Enums\Social\EditorialPlanSlotStatus::QueuedToN8n->value,
        ]);

        $mockClient = Mockery::mock(N8nClient::class);
        $mockClient->shouldReceive('requestEditorialPlanGeneration')->andThrow(new \Exception('N8n down'));

        $job = new SendN8nRequestJob(['dummy' => 'payload'], $marketingProject->id, 'editorial_plan');
        $job->handle($mockClient);

        $this->assertEquals(MarketingProjectStatus::N8nFailed->value, $marketingProject->fresh()->status->value);
        $this->assertEquals(\App\Enums\Social\EditorialPlanStatus::N8nFailed->value, $plan->fresh()->status->value);
        $this->assertEquals(\App\Enums\Social\EditorialPlanSlotStatus::N8nFailed->value, $slot->fresh()->status->value);
    }
}
