<?php

namespace Tests\Feature;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Jobs\SendMarketingCampaignPostToN8nJob;
use App\Models\MarketingCampaignPost;
use App\Services\Integrations\N8n\N8nClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Exception;

class SendMarketingCampaignPostToN8nJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_dispatched()
    {
        Queue::fake();

        $post = new MarketingCampaignPost();
        $post->id = 1;
        $post->status = MarketingCampaignPostStatus::PendingN8n;

        $payload = ['request_id' => 'req_123', 'dummy' => 'data'];

        SendMarketingCampaignPostToN8nJob::dispatch($post, $payload, null, false, []);

        Queue::assertPushed(SendMarketingCampaignPostToN8nJob::class, function ($job) use ($post, $payload) {
            return $job->post->id === $post->id &&
                   $job->payload === $payload;
        });
    }

    public function test_job_restores_full_previous_state_on_failure()
    {
        $post = MarketingCampaignPost::factory()->create([
            'status' => MarketingCampaignPostStatus::PendingN8n->value,
            'n8n_request_id' => 'req_123',
        ]);

        $payload = ['request_id' => 'req_123', 'dummy' => 'data'];
        $previousState = [
            'status' => MarketingCampaignPostStatus::Generated->value,
            'n8n_previous_status' => MarketingCampaignPostStatus::Draft->value,
            'n8n_request_id' => 'old_req_000',
            'approved_payload_snapshot' => ['old' => 'snapshot'],
            'n8n_payload_hash' => 'old_hash',
            'n8n_internal_context' => ['old' => 'context'],
            'submitted_to_n8n_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'n8n_completed_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
        ];

        $job = new SendMarketingCampaignPostToN8nJob($post, $payload, null, false, $previousState);
        
        $exception = new Exception('N8n is down');
        $job->failed($exception);
        
        $post->refresh();
        $this->assertEquals($previousState['status'], $post->status->value);
        $this->assertEquals(MarketingCampaignPostStatus::Draft, $post->n8n_previous_status);
        $this->assertEquals('old_req_000', $post->n8n_request_id);
        $this->assertEquals(['old' => 'snapshot'], $post->approved_payload_snapshot);
        $this->assertEquals('old_hash', $post->n8n_payload_hash);
        $this->assertEquals(['old' => 'context'], $post->n8n_internal_context);
        $this->assertEquals($previousState['submitted_to_n8n_at'], $post->submitted_to_n8n_at->format('Y-m-d H:i:s'));
        $this->assertEquals($previousState['n8n_completed_at'], $post->n8n_completed_at->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('N8n is down', $post->n8n_error);
    }
}
