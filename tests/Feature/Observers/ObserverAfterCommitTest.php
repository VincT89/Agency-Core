<?php

namespace Tests\Feature\Observers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ObserverAfterCommitTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Queue::fake();
    }

    public function test_client_observer_pushes_job_after_commit(): void
    {
        $client = Client::factory()->create(['name' => 'Old Name']);
        
        $client->update(['name' => 'New Name']);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\Chatbot\SyncChatbotClientDataJob::class, function ($job) {
            return $job->afterCommit === true;
        });
    }

    public function test_project_observer_pushes_job_after_commit(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id, 'name' => 'Proj Name']);
        
        $project->update(['name' => 'New Name']);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\Chatbot\SyncChatbotClientDataJob::class, function ($job) {
            return $job->afterCommit === true;
        });
    }

    public function test_ticket_observer_pushes_job_after_commit(): void
    {
        $client = Client::factory()->create();
        $ticket = Ticket::factory()->create(['client_id' => $client->id, 'title' => 'Old Title']);
        
        $ticket->updateQuietly(['code' => 'TCK-2024-0001']);
        $ticket->update(['title' => 'New Title']);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\Chatbot\SyncChatbotClientDataJob::class, function ($job) {
            return $job->afterCommit === true;
        });
    }

    public function test_marketing_campaign_observer_pushes_job_after_commit(): void
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id, 'name' => 'Old Name']);
        
        $campaign->update(['name' => 'New Name']);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\Chatbot\SyncChatbotClientDataJob::class, function ($job) {
            return $job->afterCommit === true;
        });
    }

    public function test_marketing_campaign_post_observer_pushes_job_after_commit(): void
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id, 'title' => 'Old Title']);
        
        $post->update(['title' => 'New Title']);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\Chatbot\SyncChatbotClientDataJob::class, function ($job) {
            return $job->afterCommit === true;
        });
    }
}
