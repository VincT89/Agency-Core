<?php

namespace Tests\Feature\Social;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Exception;
use Mockery;

class N8nDispatchCompensationTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_action_compensates_transactionally_on_dispatch_failure()
    {
        Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->image('logo.png');

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Test Post',
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
            'n8n_request_id' => 'old_req_123',
        ]);

        $dispatcher = Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andThrow(new Exception('Queue connection refused'));
        $this->app->instance(\Illuminate\Contracts\Bus\Dispatcher::class, $dispatcher);

        $action = app(\App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction::class);

        try {
            $action->execute($post, ['include_client_logo' => true, 'runtime_logo' => $file]);
        } catch (Exception $e) {
            $this->assertEquals('Queue connection refused', $e->getMessage());
        }

        $post->refresh();
        
        $this->assertEquals(MarketingCampaignPostStatus::Draft, $post->status);
        $this->assertEquals('old_req_123', $post->n8n_request_id);
        $this->assertNull($post->n8n_payload_hash);
        $this->assertEmpty(Storage::disk('public')->allFiles('clients/logos/temp'));
    }

    public function test_regeneration_action_compensates_transactionally_on_dispatch_failure()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Generated->value,
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
            'n8n_request_id' => 'old_regen_123',
        ]);
        $user = User::factory()->create();

        $dispatcher = Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andThrow(new Exception('Queue connection refused'));
        $this->app->instance(\Illuminate\Contracts\Bus\Dispatcher::class, $dispatcher);

        $action = app(\App\Domain\Social\Actions\RequestMarketingCampaignPostRegenerationAction::class);

        try {
            $action->execute($post, $user, 'full', 'Change everything');
        } catch (Exception $e) {
            $this->assertEquals('Queue connection refused', $e->getMessage());
        }

        $post->refresh();

        $this->assertEquals(MarketingCampaignPostStatus::Generated, $post->status);
        $this->assertEquals('old_regen_123', $post->n8n_request_id);
        
        $this->assertDatabaseMissing('marketing_campaign_post_comments', [
            'marketing_campaign_post_id' => $post->id,
            'type' => \App\Enums\Social\MarketingCampaignPostCommentType::ChangeRequest->value,
            'body' => 'Change everything',
        ]);
    }

    public function test_submit_action_passes_correct_previous_state_to_job()
    {
        Storage::fake('public');

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Test Post',
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
            'n8n_request_id' => 'old_req_888',
        ]);

        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\SendMarketingCampaignPostToN8nJob::class]);

        $action = app(\App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction::class);
        $action->execute($post, []);

        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\SendMarketingCampaignPostToN8nJob::class, function ($job) {
            return $job->previousState['n8n_request_id'] === 'old_req_888'
                && $job->previousState['status'] === MarketingCampaignPostStatus::Draft->value;
        });
    }
}
