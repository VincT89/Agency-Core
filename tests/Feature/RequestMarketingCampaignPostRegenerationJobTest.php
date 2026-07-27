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

        RequestMarketingCampaignPostRegenerationJob::dispatch($post, $payload, MarketingCampaignPostStatus::Draft->value, 0);

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
        
        $version = \App\Models\MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'version_number' => 1
        ]);
        
        $comment = $post->comments()->create([
            'marketing_campaign_post_version_id' => $version->id,
            'user_id' => \App\Models\User::factory()->create()->id,
            'body' => 'Change it',
            'visibility' => 'internal',
            'type' => 'change_request'
        ]);

        $payload = ['request_id' => 'req_123', 'dummy' => 'data'];
        $previousStatus = MarketingCampaignPostStatus::Generated->value;
        $previousState = [
            'status' => $previousStatus,
            'n8n_previous_status' => MarketingCampaignPostStatus::Draft->value,
            'n8n_request_id' => 'old_req_000',
            'approved_payload_snapshot' => ['old' => 'snapshot'],
            'n8n_payload_hash' => 'old_hash',
            'n8n_internal_context' => ['old' => 'context'],
            'submitted_to_n8n_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'n8n_completed_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
        ];

        $job = new RequestMarketingCampaignPostRegenerationJob($post, $payload, $previousStatus, $comment->id, $previousState);
        
        $exception = new Exception('N8n is down');
        $job->failed($exception);
        
        $post->refresh();
        $this->assertEquals($previousStatus, $post->status->value);
        $this->assertEquals(MarketingCampaignPostStatus::Draft, $post->n8n_previous_status);
        $this->assertEquals('old_req_000', $post->n8n_request_id);
        $this->assertEquals(['old' => 'snapshot'], $post->approved_payload_snapshot);
        $this->assertEquals('old_hash', $post->n8n_payload_hash);
        $this->assertEquals(['old' => 'context'], $post->n8n_internal_context);
        $this->assertEquals($previousState['submitted_to_n8n_at'], $post->submitted_to_n8n_at->format('Y-m-d H:i:s'));
        $this->assertEquals($previousState['n8n_completed_at'], $post->n8n_completed_at->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('N8n is down', $post->n8n_error);
        
        // Assert comment is deleted
        $this->assertDatabaseMissing('marketing_campaign_post_comments', ['id' => $comment->id]);
    }

    public function test_job_ignores_failure_if_request_id_has_changed()
    {
        $post = MarketingCampaignPost::factory()->create([
            'status' => MarketingCampaignPostStatus::Regenerating->value,
            'n8n_request_id' => 'req_new', // request_id has changed
        ]);

        $payload = ['request_id' => 'req_old', 'dummy' => 'data'];
        $previousStatus = MarketingCampaignPostStatus::Generated->value;

        $job = new RequestMarketingCampaignPostRegenerationJob($post, $payload, $previousStatus, 0);
        
        $exception = new Exception('N8n is down');
        $job->failed($exception);
        
        $post->refresh();
        $this->assertEquals(MarketingCampaignPostStatus::Regenerating->value, $post->status->value);
        $this->assertNull($post->n8n_error);
    }
}
