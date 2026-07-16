<?php

namespace Tests\Feature\Livewire\Social;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction;
use Mockery\MockInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use App\Jobs\SendMarketingCampaignPostToN8nJob;
use Illuminate\Support\Str;

class MarketingCampaignPostShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Event::fake();
    }

    public function test_save_and_submit_to_n8n_calls_action_without_assigning_status_in_livewire()
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Old Title',
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);

        $this->actingAs($user);

        // We'll mock the Action to ensure it's called and it's the one responsible for the status
        $this->mock(SubmitMarketingCampaignPostToN8nAction::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                 ->once()
                 ->andReturnUsing(function ($post, $runtimeClientData) {
                     $this->assertNotEquals(MarketingCampaignPostStatus::PendingN8n->value, $post->status->value, 'Livewire should not set status to pending_n8n directly before Action');
                     $post->status = MarketingCampaignPostStatus::PendingN8n->value;
                     $post->save();
                 });
        });

        Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->set('form.title', 'New Title')
            ->set('form.description', 'New Description')
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft') // Intentionally setting draft
            ->set('form.ai_analysis_enabled', false)
            ->set('form.media_source', 'local')
            ->call('saveAndSubmitToN8n')
            ->assertDispatched('post-submitted-n8n');

        $post->refresh();
        $this->assertEquals(MarketingCampaignPostStatus::PendingN8n, $post->status);
        $this->assertEquals('New Title', $post->title);
    }

    public function test_save_and_submit_to_n8n_dispatches_job_with_new_request_id()
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'n8n_request_id' => 'old_req_123',
            'title' => 'Old Title',
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->set('form.title', 'Test Title')
            ->set('form.description', 'Test Description')
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft')
            ->set('form.ai_analysis_enabled', false)
            ->set('form.media_source', 'local')
            ->call('saveAndSubmitToN8n');

        Queue::assertPushed(SendMarketingCampaignPostToN8nJob::class, function ($job) {
            return !empty($job->post->n8n_request_id) && $job->post->n8n_request_id !== 'old_req_123';
        });
    }
}
