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

    public function test_regenerate_post_saves_metadata_before_submitting()
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

        // We mock the Action to ensure it's called
        $this->mock(\App\Domain\Social\Actions\RequestMarketingCampaignPostRegenerationAction::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                 ->once()
                 ->andReturnUsing(function ($post, $user, $type) {
                     $post->status = MarketingCampaignPostStatus::Regenerating->value;
                     $post->save();
                 });
        });

        Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->set('form.title', 'Regenerated Title')
            ->set('form.description', 'Regenerated Description')
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft') // Intentionally setting draft
            ->set('form.ai_analysis_enabled', false)
            ->set('form.media_source', 'local')
            ->call('regeneratePost', 'full')
            ->assertDispatched('post-regenerating');

        $post->refresh();
        $this->assertEquals(MarketingCampaignPostStatus::Regenerating, $post->status);
        $this->assertEquals('Regenerated Title', $post->title);
    }

    public function test_sody_loader_resets_after_validation_error(): void
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->assertSee('Sody sta preparando il contenuto')
            ->assertSee('Chiudi questo pannello')
            ->assertDontSee('Interrompi')
            ->set('form.content_type', 'unsupported')
            ->call('saveAndSubmitToN8n')
            ->assertHasErrors(['form.content_type'])
            ->assertDispatched('sody-processing-started')
            ->assertDispatched('sody-processing-failed');
    }

    public function test_sody_loader_reports_long_processing_without_cancelling_the_request(): void
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::PendingN8n->value,
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);

        $this->actingAs($user);

        $component = Livewire::test(MarketingCampaignPostShow::class, [
            'campaign' => $campaign,
            'post' => $post,
        ]);

        for ($check = 0; $check < 10; $check++) {
            $component->call('checkRegenerationStatus');
        }

        $component->assertDispatched('sody-processing-delayed');

        $this->assertEquals(MarketingCampaignPostStatus::PendingN8n, $post->fresh()->status);
        $this->assertNull($post->fresh()->n8n_error);
    }

    public function test_remove_media_item_only_affects_livewire_state()
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);
        
        $media = \App\Models\MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'fake_path.jpg',
            'sort_order' => 0
        ]);

        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->assertSee($media->id)
            ->call('removeSelectedMediaItem', 'existing:' . $media->id);

        $this->assertDatabaseHas('marketing_campaign_post_media', [
            'id' => $media->id,
            'path' => 'fake_path.jpg',
        ]);
    }

    public function test_reorder_media_only_affects_livewire_state_until_save()
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);
        
        $m1 = \App\Models\MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'sort_order' => 0
        ]);
        
        $m2 = \App\Models\MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'sort_order' => 1
        ]);

        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->call('reorderSelectedMedia', 0, 1);

        $this->assertDatabaseHas('marketing_campaign_post_media', [
            'id' => $m1->id,
            'sort_order' => 0, // DB Not updated yet
        ]);
    }

    public function test_save_manual_version_noop_shows_message()
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);

        $v1 = $post->versions()->create([
            'version_number' => 1,
            'title' => 'Title',
            'caption' => null,
            'hashtags' => null,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        
        $media = \App\Models\MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'fake_path.jpg',
            'sort_order' => 0
        ]);
        $v1->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $post->update(['current_version_id' => $v1->id, 'generated_at' => now()->subDay()]);
        
        $originalGeneratedAt = $post->fresh()->generated_at;
        $originalStatus = $post->fresh()->status;

        $this->actingAs($user);

        $component = Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->set('form.title', 'Title')
            ->set('form.description', null)
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft')
            ->set('form.ai_analysis_enabled', false)
            ->set('form.media_source', 'local')
            ->call('savePost');

        $component->assertHasNoErrors();
        $component->assertDispatched('post-saved');
        $this->assertDatabaseCount('marketing_campaign_post_versions', 1);
        
        $post->refresh();
        $this->assertEquals($originalStatus, $post->status);
        $this->assertEquals($originalGeneratedAt, $post->generated_at);
    }

    public function test_two_tabs_stale_data_fails_silently()
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);

        $media = \App\Models\MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'fake_path.jpg',
            'sort_order' => 0
        ]);

        $v1 = $post->versions()->create([
            'version_number' => 1,
            'title' => 'Title',
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $v1->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $post->update(['current_version_id' => $v1->id]);

        $this->actingAs($user);

        $component = Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post]);

        // Simulate Tab B saving and creating V2
        $v2 = $post->versions()->create([
            'version_number' => 2,
            'title' => 'Title 2',
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $v2->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $post->update(['current_version_id' => $v2->id]);

        // Tab A tries to save
        $component->set('form.title', 'Title 3')
            ->set('form.description', null)
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft')
            ->set('form.ai_analysis_enabled', false)
            ->set('form.media_source', 'local')
            ->call('savePost');

        $this->assertDatabaseCount('marketing_campaign_post_versions', 2); // Still 2, no V3 created
    }

    public function test_save_post_drops_status_update_before_action()
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);

        $media = \App\Models\MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'fake_path.jpg',
            'sort_order' => 0
        ]);

        $this->actingAs($user);

        $component = Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->set('form.title', 'New Title')
            ->set('form.description', 'New Desc')
            ->set('form.content_type', 'post')
            ->set('form.status', MarketingCampaignPostStatus::Published->value) // Try to bypass status
            ->set('form.ai_analysis_enabled', false)
            ->set('form.media_source', 'local')
            ->call('savePost');

        // Since it's a real edit, action will set status to Generated. It should not be Published.
        $post->refresh();
        $this->assertEquals(MarketingCampaignPostStatus::Generated, $post->status);
    }

    public function test_stale_save_non_modifica_il_cliente()
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        $client = Client::factory()->create(['activity_description' => 'Original']);
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);

        $v1 = $post->versions()->create([
            'version_number' => 1,
            'title' => 'Title',
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $media = \App\Models\MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'fake_path.jpg',
            'sort_order' => 0
        ]);
        $v1->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $post->update(['current_version_id' => $v1->id]);

        $this->actingAs($user);

        \Illuminate\Support\Facades\Storage::fake('public');
        $fakeLogo = \Illuminate\Http\UploadedFile::fake()->image('logo.jpg');

        $component = Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post]);

        // Simulate Tab B saving and creating V2
        $v2 = $post->versions()->create([
            'version_number' => 2,
            'title' => 'Title 2',
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $v2->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $post->update(['current_version_id' => $v2->id]);

        // Tab A tries to save
        $component->set('form.title', 'Title 3')
            ->set('form.description', 'Desc')
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft')
            ->set('form.ai_analysis_enabled', false)
            ->set('form.media_source', 'local')
            ->set('runtime_activity_description', 'Modificata da stale')
            ->set('save_runtime_activity_to_client', true)
            ->set('include_client_header', true)
            ->set('runtime_logo', $fakeLogo)
            ->call('savePost');

        $component->assertNotDispatched('post-saved');

        $this->assertEquals('Original', $client->fresh()->activity_description);
        $this->assertNull($client->fresh()->logo_path);
    }

    public function test_due_salvataggi_consecutivi_nello_stesso_componente()
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);

        $media = \App\Models\MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'fake_path.jpg',
            'sort_order' => 0
        ]);

        $this->actingAs($user);

        $component = Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->set('form.title', 'V1')
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft')
            ->set('form.ai_analysis_enabled', false)
            ->set('form.media_source', 'local')
            ->call('savePost')
            ->assertHasNoErrors();
        
        $this->assertDatabaseCount('marketing_campaign_post_versions', 1);

        $component->set('form.title', 'V2')
            ->call('savePost')
            ->assertHasNoErrors();
        
        $this->assertDatabaseCount('marketing_campaign_post_versions', 2);
    }

    public function test_stale_save_seguito_da_refresh_e_nuovo_salvataggio_valido()
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);

        $media = \App\Models\MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'fake_path.jpg',
            'sort_order' => 0
        ]);

        $this->actingAs($user);

        $component = Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post]);

        // Simulate Tab B saving and creating V1
        $v1 = $post->versions()->create([
            'version_number' => 1,
            'title' => 'V1',
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $v1->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $post->update(['current_version_id' => $v1->id]);

        // Tab A tries to save => Stale
        $component->set('form.title', 'Title 3')
            ->set('form.description', 'Desc')
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft')
            ->set('form.ai_analysis_enabled', false)
            ->set('form.media_source', 'local')
            ->call('savePost');

        $component->assertNotDispatched('post-saved');
        $this->assertDatabaseCount('marketing_campaign_post_versions', 1);

        // Tab A refreshes post
        $component->call('refreshPost');

        // Tab A tries to save again
        $component->set('form.title', 'V2')
            ->call('savePost')
            ->assertHasNoErrors();
        
        $this->assertDatabaseCount('marketing_campaign_post_versions', 2);
    }
}
