<?php

namespace Tests\Feature;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Jobs\RequestMarketingCampaignPostRegenerationJob;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use App\Services\Integrations\N8n\N8nClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Exception;

class RequestMarketingCampaignPostRegenerationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_dispatched()
    {
        Queue::fake();

        $post = new MarketingCampaignPost();
        $post->id = 1;
        $post->status = MarketingCampaignPostStatus::Regenerating;

        $payload = ['request_id' => 'req_123', 'dummy' => 'data'];

        RequestMarketingCampaignPostRegenerationJob::dispatch($post, $payload, MarketingCampaignPostStatus::Draft->value);

        Queue::assertPushed(RequestMarketingCampaignPostRegenerationJob::class, function ($job) use ($post, $payload) {
            return $job->post->id === $post->id &&
                   $job->payload === $payload &&
                   $job->previousStatus === MarketingCampaignPostStatus::Draft->value;
        });
    }

    public function test_job_restores_previous_status_on_failure()
    {
        $post = MarketingCampaignPost::factory()->create([
            'status' => MarketingCampaignPostStatus::Regenerating->value,
            'n8n_request_id' => 'req_123',
        ]);

        $payload = ['request_id' => 'req_123', 'dummy' => 'data'];
        $previousStatus = MarketingCampaignPostStatus::Generated->value;

        $job = new RequestMarketingCampaignPostRegenerationJob($post, $payload, $previousStatus);
        
        $exception = new Exception('N8n is down');
        $job->failed($exception);
        
        $post->refresh();
        $this->assertEquals($previousStatus, $post->status->value);
        $this->assertEquals('Rigenerazione fallita dopo 3 tentativi: N8n is down', $post->n8n_error);
    }
}
