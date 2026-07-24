<?php

namespace Tests\Feature\Integrations\N8n;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Jobs\SendMarketingCampaignPostToN8nJob;
use App\Services\Integrations\N8n\N8nClient;
use Illuminate\Support\Facades\Http;
use App\Domain\Social\Actions\AddMarketingCampaignPostVersionFromN8nAction;
use App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction;
use Exception;

class MarketingCampaignPostRaceConditionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.n8n.submit_marketing_campaign_post_webhook_url' => 'https://n8n.local/webhook/submit']);
    }

    public function test_old_job_with_different_request_id_is_ignored()
    {
        Http::fake();

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::PendingN8n->value,
            'n8n_request_id' => 'new-request-id',
        ]);

        $payload = ['request_id' => 'old-request-id'];

        $job = new SendMarketingCampaignPostToN8nJob($post, $payload);
        $job->handle(app(N8nClient::class));

        Http::assertNothingSent();

        $post->refresh();
        $this->assertEquals(MarketingCampaignPostStatus::PendingN8n, $post->status);
    }

    public function test_fast_callback_followed_by_job_conclusion()
    {
        Http::fake([
            'https://n8n.local/webhook/submit' => Http::response(['success' => true], 200),
        ]);

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::PendingN8n->value,
            'n8n_request_id' => 'req-123',
        ]);

        $payload = ['request_id' => 'req-123'];
        $job = new SendMarketingCampaignPostToN8nJob($post, $payload);

        // Simulate callback modifying status while job is running
        $post->update(['status' => MarketingCampaignPostStatus::Generated->value]);

        $job->handle(app(N8nClient::class)); // concludes but should not update status or send

        Http::assertNothingSent();

        $post->refresh();
        $this->assertEquals(MarketingCampaignPostStatus::Generated, $post->status);
        $this->assertNull($post->submitted_to_n8n_at); // Not updated because where('status', pending) fails
    }

    public function test_job_failed_executed_after_successful_callback_is_ignored()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Generated->value,
            'n8n_request_id' => 'req-123',
        ]);

        $payload = ['request_id' => 'req-123'];
        $job = new SendMarketingCampaignPostToN8nJob($post, $payload);

        $job->failed(new Exception('Network Error'));

        $post->refresh();
        $this->assertEquals(MarketingCampaignPostStatus::Generated, $post->status);
        $this->assertNull($post->n8n_error);
    }

    public function test_effective_reconstruction_of_snapshot_with_modified_data()
    {
        Http::fake([
            'https://n8n.local/webhook/submit' => Http::response(['success' => true], 200),
        ]);

        $client = Client::factory()->create(['name' => 'Old Name']);
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Old Title',
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);

        $action = app(SubmitMarketingCampaignPostToN8nAction::class);
        $action->execute($post);

        $post->refresh();
        $firstHash = $post->n8n_payload_hash;
        $firstRequestId = $post->n8n_request_id;

        // Change data and re-submit
        $post->title = 'New Title';
        $post->status = MarketingCampaignPostStatus::Draft->value;
        $post->save();

        $action->execute($post);

        $post->refresh();
        $this->assertNotEquals($firstHash, $post->n8n_payload_hash);
        $this->assertNotEquals($firstRequestId, $post->n8n_request_id);
        $this->assertStringContainsString('New Title', json_encode($post->approved_payload_snapshot));
    }

    public function test_request_id_check_inside_successful_callback_lock()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::PendingN8n->value,
            'n8n_request_id' => 'req-new',
        ]);

        $action = app(AddMarketingCampaignPostVersionFromN8nAction::class);

        $payload = [
            'request_id' => 'req-old', // This callback is from an old request
            'regeneration_type' => 'full',
            'title' => 'Title',
            'raw_payload' => [],
        ];

        $data = \App\Domain\Social\DTOs\AddMarketingCampaignPostVersionData::fromArray($post->id, $payload);
        $result = $action->execute($data);

        $this->assertEquals('conflict', $result->outcome);
    }

    public function test_deleted_record_in_job_handle_returns_gracefully()
    {
        Http::fake();

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::PendingN8n->value,
            'n8n_request_id' => 'req-123',
        ]);

        $payload = ['request_id' => 'req-123'];
        $job = new SendMarketingCampaignPostToN8nJob($post, $payload);

        // Simulate deletion
        $post->delete();

        $job->handle(app(N8nClient::class)); // Should return without errors

        Http::assertNothingSent();
    }

    public function test_regeneration_job_ignores_old_request_id()
    {
        Http::fake();

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Regenerating->value,
            'n8n_request_id' => 'req-new',
        ]);

        $payload = ['request_id' => 'req-old'];
        $job = new \App\Jobs\RequestMarketingCampaignPostRegenerationJob($post, $payload, MarketingCampaignPostStatus::Draft->value);

        $job->handle(app(N8nClient::class)); // concludes without sending

        Http::assertNothingSent();

        $post->refresh();
        $this->assertEquals(MarketingCampaignPostStatus::Regenerating, $post->status);
    }

    public function test_dispatch_submit_failure_with_state_and_logo_rollback()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);

        // Mock del Dispatcher per simulare un fallimento nel job dispatch (es. Redis giù)
        $dispatcher = \Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andThrow(new Exception('Queue connection refused'));
        $this->app->instance(\Illuminate\Contracts\Bus\Dispatcher::class, $dispatcher);

        $action = app(SubmitMarketingCampaignPostToN8nAction::class);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Queue connection refused');

        $action->execute($post);
    }

    public function test_dispatch_submit_failure_with_state_and_logo_rollback_asserts()
    {
        // Questo test verifica lo stato dopo il fallimento del precedente (è difficile farlo in un blocco try/catch con expectException se si usa expectException, usiamo try/catch)
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);

        $dispatcher = \Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andThrow(new Exception('Queue connection refused'));
        $this->app->instance(\Illuminate\Contracts\Bus\Dispatcher::class, $dispatcher);

        $action = app(SubmitMarketingCampaignPostToN8nAction::class);
        
        // Prepariamo un mock logo
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->image('logo.png');

        try {
            $action->execute($post, ['include_client_logo' => true, 'runtime_logo' => $file]);
        } catch (Exception $e) {
            $this->assertEquals('Queue connection refused', $e->getMessage());
        }

        $post->refresh();
        $this->assertEquals(MarketingCampaignPostStatus::Draft, $post->status);
        $this->assertNull($post->n8n_request_id);
        $this->assertNull($post->n8n_payload_hash);
        
        // Verifica che il logo temporaneo sia stato cancellato
        $this->assertEmpty(\Illuminate\Support\Facades\Storage::disk('public')->allFiles('clients/logos/temp'));
    }

    public function test_dispatch_regeneration_failure_with_state_and_comment_rollback()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Generated->value,
        ]);
        $user = \App\Models\User::factory()->create();

        $dispatcher = \Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andThrow(new Exception('Queue connection refused'));
        $this->app->instance(\Illuminate\Contracts\Bus\Dispatcher::class, $dispatcher);

        $action = app(\App\Domain\Social\Actions\RequestMarketingCampaignPostRegenerationAction::class);

        try {
            $action->execute($post, $user, 'full', 'Change everything');
        } catch (Exception $e) {
            $this->assertEquals('Queue connection refused', $e->getMessage());
        }

        $post->refresh();
        // Lo stato deve essere tornato a Generated
        $this->assertEquals(MarketingCampaignPostStatus::Generated, $post->status);
        $this->assertNull($post->n8n_request_id);
        
        // Il commento (ChangeRequest) deve essere stato cancellato
        $this->assertDatabaseMissing('marketing_campaign_post_comments', [
            'marketing_campaign_post_id' => $post->id,
            'type' => \App\Enums\Social\MarketingCampaignPostCommentType::ChangeRequest->value,
            'body' => 'Change everything',
        ]);
    }
}
