<?php

namespace Tests\Feature\Livewire\Social;

use App\Domain\Social\Actions\RequestMarketingCampaignPostRegenerationAction;
use App\Domain\Social\Actions\SubmitMarketingCampaignPostToN8nAction;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostType;
use App\Enums\Social\SocialAccessStatus;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialPlatform;
use App\Enums\UserRole;
use App\Jobs\SendMarketingCampaignPostToN8nJob;
use App\Jobs\Social\ExecuteMarketingCampaignPostPublicationJob;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow;
use App\Models\Client;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostPublication;
use App\Models\MarketingCampaignPostVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

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
        $user = User::factory()->create(['role' => UserRole::Admin->value]);

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Old Title',
            'content_type' => MarketingCampaignPostType::Post->value,
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
        $user = User::factory()->create(['role' => UserRole::Admin->value]);

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'n8n_request_id' => 'old_req_123',
            'title' => 'Old Title',
            'content_type' => MarketingCampaignPostType::Post->value,
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
            return ! empty($job->post->n8n_request_id) && $job->post->n8n_request_id !== 'old_req_123';
        });
    }

    public function test_regenerate_post_saves_metadata_before_submitting()
    {
        $user = User::factory()->create(['role' => UserRole::Admin->value]);

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Old Title',
            'content_type' => MarketingCampaignPostType::Post->value,
        ]);

        $this->actingAs($user);

        // We mock the Action to ensure it's called
        $this->mock(RequestMarketingCampaignPostRegenerationAction::class, function (MockInterface $mock) {
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
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'content_type' => MarketingCampaignPostType::Post->value,
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
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::PendingN8n->value,
            'content_type' => MarketingCampaignPostType::Post->value,
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
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => MarketingCampaignPostType::Post->value,
        ]);

        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'fake_path.jpg',
            'sort_order' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->assertSee($media->id)
            ->call('removeSelectedMediaItem', 'existing:'.$media->id);

        $this->assertDatabaseHas('marketing_campaign_post_media', [
            'id' => $media->id,
            'path' => 'fake_path.jpg',
        ]);
    }

    public function test_reorder_media_only_affects_livewire_state_until_save()
    {
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => MarketingCampaignPostType::Post->value,
        ]);

        $m1 = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'sort_order' => 0,
        ]);

        $m2 = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'sort_order' => 1,
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
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => MarketingCampaignPostType::Post->value,
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

        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'fake_path.jpg',
            'sort_order' => 0,
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
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => MarketingCampaignPostType::Post->value,
        ]);

        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'fake_path.jpg',
            'sort_order' => 0,
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
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => MarketingCampaignPostType::Post->value,
        ]);

        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'fake_path.jpg',
            'sort_order' => 0,
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
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create(['activity_description' => 'Original']);
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => MarketingCampaignPostType::Post->value,
        ]);

        $v1 = $post->versions()->create([
            'version_number' => 1,
            'title' => 'Title',
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'fake_path.jpg',
            'sort_order' => 0,
        ]);
        $v1->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $post->update(['current_version_id' => $v1->id]);

        $this->actingAs($user);

        Storage::fake('public');
        $fakeLogo = UploadedFile::fake()->image('logo.jpg');

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
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => MarketingCampaignPostType::Post->value,
        ]);

        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'fake_path.jpg',
            'sort_order' => 0,
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
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Title',
            'content_type' => MarketingCampaignPostType::Post->value,
        ]);

        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => 'fake_path.jpg',
            'sort_order' => 0,
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

    public function test_manual_ready_button_is_only_visible_when_sody_is_disabled(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'content_type' => MarketingCampaignPostType::Post->value,
            'ai_analysis_enabled' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostShow::class, [
            'campaign' => $campaign,
            'post' => $post,
        ])
            ->assertDontSee('Salva come pronto senza Sody')
            ->set('form.ai_analysis_enabled', false)
            ->assertSee('Salva come pronto senza Sody');
    }

    public function test_saved_video_keeps_its_type_and_mime_in_the_persisted_preview(): void
    {
        Storage::fake('social_media');
        Storage::disk('social_media')->put('marketing/campaign-posts/saved-video.mp4', 'video-content');

        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Generated->value,
            'content_type' => MarketingCampaignPostType::Post->value,
        ]);
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'disk' => 'social_media',
            'path' => 'marketing/campaign-posts/saved-video.mp4',
            'media_type' => 'video',
            'mime_type' => 'video/mp4',
            'original_name' => 'saved-video.mp4',
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'version_number' => 1,
        ]);
        $version->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $post->update(['current_version_id' => $version->id]);

        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostShow::class, [
            'campaign' => $campaign,
            'post' => $post->fresh(),
        ])
            ->assertSet('selected_media_items.0.type', 'video')
            ->assertSet('selected_media_items.0.mime_type', 'video/mp4')
            ->assertSeeHtml('data-persisted-video="'.$media->id.'"')
            ->assertSeeHtml('type="video/mp4"')
            ->assertSee('Anteprima video non disponibile.');
    }

    public function test_historical_post_explains_why_delete_is_unavailable(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Generated->value,
            'content_type' => MarketingCampaignPostType::Post->value,
        ]);
        MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'version_number' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostShow::class, [
            'campaign' => $campaign,
            'post' => $post,
        ])
            ->assertSeeHtml('data-post-delete-protected="true"')
            ->assertSee('Post storico: non eliminabile da questa schermata.')
            ->assertDontSee('Sei sicuro di voler eliminare questo post?');
    }

    public function test_post_without_history_keeps_the_delete_confirmation(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'content_type' => MarketingCampaignPostType::Post->value,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostShow::class, [
            'campaign' => $campaign,
            'post' => $post,
        ])
            ->assertSee('Sei sicuro di voler eliminare questo post?')
            ->assertDontSee('Post storico: non eliminabile da questa schermata.');
    }

    public function test_tiktok_direct_post_renders_current_creator_options_without_defaults(): void
    {
        [$user, $campaign, $post] = $this->directTikTokPostFixture();
        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostShow::class, [
            'campaign' => $campaign,
            'post' => $post,
        ])
            ->assertSeeHtml('data-tiktok-direct-post')
            ->assertSee('Pubblicazione diretta su TikTok')
            ->assertSee('Creator di prova')
            ->assertSee('Seleziona manualmente')
            ->assertSet('tiktokDirectOptions.privacy_level', '')
            ->assertSet('tiktokDirectOptions.allow_comment', false)
            ->assertSet('tiktokDirectOptions.allow_duet', false)
            ->assertSet('tiktokDirectOptions.allow_stitch', false)
            ->assertSet('tiktokDirectOptions.consent', false);
    }

    public function test_tiktok_direct_post_surfaces_safe_creator_info_error(): void
    {
        [$user, $campaign, $post] = $this->directTikTokPostFixture(
            creatorInfoResponse: [
                'data' => [],
                'error' => [
                    'code' => 'reached_active_user_cap',
                    'message' => 'Sandbox active user limit reached',
                    'log_id' => 'safe-log-reference-123',
                ],
            ]
        );
        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostShow::class, [
            'campaign' => $campaign,
            'post' => $post,
        ])
            ->call('refreshTikTokDirectOptions')
            ->assertSee('TikTok ha raggiunto il limite di utenti attivi consentiti per questa app.')
            ->assertSee('Codice TikTok: reached_active_user_cap.')
            ->assertSee('Riferimento TikTok: safe-log-reference-123.');
    }

    public function test_tiktok_direct_post_requires_explicit_privacy_and_consent(): void
    {
        [$user, $campaign, $post] = $this->directTikTokPostFixture();
        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostShow::class, [
            'campaign' => $campaign,
            'post' => $post,
        ])
            ->call('publishToSocial', SocialPlatform::Tiktok->value)
            ->assertHasErrors([
                'tiktokDirectOptions.privacy_level',
                'tiktokDirectOptions.consent',
            ]);

        $this->assertDatabaseCount('marketing_campaign_post_publications', 0);
        Queue::assertNotPushed(ExecuteMarketingCampaignPostPublicationJob::class);
    }

    public function test_tiktok_direct_post_freezes_user_choices_and_consent_in_snapshot(): void
    {
        [$user, $campaign, $post] = $this->directTikTokPostFixture([
            'duet_disabled' => true,
            'stitch_disabled' => false,
        ]);
        $this->actingAs($user);

        Livewire::test(MarketingCampaignPostShow::class, [
            'campaign' => $campaign,
            'post' => $post,
        ])
            ->set('tiktokDirectOptions.privacy_level', 'SELF_ONLY')
            ->set('tiktokDirectOptions.allow_comment', true)
            ->set('tiktokDirectOptions.allow_duet', true)
            ->set('tiktokDirectOptions.allow_stitch', true)
            ->set('tiktokDirectOptions.is_aigc', true)
            ->set('tiktokDirectOptions.consent', true)
            ->call('publishToSocial', SocialPlatform::Tiktok->value)
            ->assertHasNoErrors()
            ->assertSet('tiktokDirectOptions.consent', false);

        $publication = MarketingCampaignPostPublication::query()->sole();
        $snapshot = $publication->payload_snapshot;

        $this->assertSame('SELF_ONLY', data_get($snapshot, 'target.privacy_options.privacy_level'));
        $this->assertFalse(data_get($snapshot, 'target.privacy_options.disable_comment'));
        $this->assertTrue(data_get($snapshot, 'target.privacy_options.disable_duet'));
        $this->assertFalse(data_get($snapshot, 'target.privacy_options.disable_stitch'));
        $this->assertSame('direct', data_get($snapshot, 'platform_options.delivery_mode'));
        $this->assertTrue(data_get($snapshot, 'platform_options.creator_consent_confirmed'));
        $this->assertSame('music_usage', data_get($snapshot, 'platform_options.creator_consent_policy'));
        $this->assertTrue(data_get($snapshot, 'platform_options.is_aigc'));
        $this->assertFalse(data_get($snapshot, 'platform_options.brand_content_toggle'));
        $this->assertFalse(data_get($snapshot, 'platform_options.brand_organic_toggle'));

        Queue::assertPushed(
            ExecuteMarketingCampaignPostPublicationJob::class,
            fn (ExecuteMarketingCampaignPostPublicationJob $job): bool => $job->publicationId === $publication->id
        );
    }

    public function test_tiktok_direct_post_rejects_incomplete_or_private_branded_disclosure(): void
    {
        [$user, $campaign, $post] = $this->directTikTokPostFixture();
        $this->actingAs($user);

        $component = Livewire::test(MarketingCampaignPostShow::class, [
            'campaign' => $campaign,
            'post' => $post,
        ])
            ->set('tiktokDirectOptions.privacy_level', 'SELF_ONLY')
            ->set('tiktokDirectOptions.commercial_content', true)
            ->set('tiktokDirectOptions.consent', true)
            ->call('publishToSocial', SocialPlatform::Tiktok->value)
            ->assertHasErrors(['tiktokDirectOptions.commercial_content']);

        $component
            ->set('tiktokDirectOptions.brand_content_toggle', true)
            ->call('publishToSocial', SocialPlatform::Tiktok->value)
            ->assertHasErrors(['tiktokDirectOptions.brand_content_toggle']);

        $this->assertDatabaseCount('marketing_campaign_post_publications', 0);
        Queue::assertNotPushed(ExecuteMarketingCampaignPostPublicationJob::class);
    }

    /**
     * @return array{0: User, 1: MarketingCampaign, 2: MarketingCampaignPost, 3: ClientSocialAccount}
     */
    private function directTikTokPostFixture(
        array $creatorInfoOverrides = [],
        ?array $creatorInfoResponse = null
    ): array {
        config([
            'services.tiktok.delivery_mode' => 'direct',
            'services.tiktok.direct_publish_enabled' => true,
            'services.tiktok.mock_publishing' => false,
            'services.tiktok.enable_photo_mode' => false,
            'services.tiktok.upload_mode' => 'PullFromUrl',
        ]);

        $creatorInfo = array_merge([
            'creator_nickname' => 'Creator di prova',
            'creator_username' => 'creator.test',
            'creator_avatar_url' => '',
            'privacy_level_options' => ['SELF_ONLY', 'MUTUAL_FOLLOW_FRIENDS'],
            'comment_disabled' => false,
            'duet_disabled' => false,
            'stitch_disabled' => false,
            'max_video_post_duration_sec' => 600,
        ], $creatorInfoOverrides);

        $creatorInfoResponse ??= [
            'data' => $creatorInfo,
            'error' => ['code' => 'ok', 'message' => ''],
        ];

        Http::fake([
            '*open.tiktokapis.com/v2/post/publish/creator_info/query/*' => Http::response(
                $creatorInfoResponse,
                200
            ),
        ]);

        Storage::fake('social_media');
        $videoContents = base64_decode(
            'AAAAHGZ0eXBpc29tAAACAGlzb21pc28ybXA0MQAAAAhmcmVl',
            true
        );
        Storage::disk('social_media')->put('marketing/campaign-posts/direct-test.mp4', $videoContents);

        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Approved->value,
            'content_type' => MarketingCampaignPostType::Reel->value,
            'publishing_platforms' => [SocialPlatform::Tiktok->value],
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'title' => 'Titolo TikTok',
            'caption' => 'Caption TikTok',
        ]);
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'disk' => 'social_media',
            'path' => 'marketing/campaign-posts/direct-test.mp4',
            'media_type' => 'video',
            'mime_type' => 'video/mp4',
            'original_name' => 'direct-test.mp4',
        ]);
        $version->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $post->update(['current_version_id' => $version->id]);

        $account = ClientSocialAccount::factory()->create([
            'client_id' => $client->id,
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'access_status' => SocialAccessStatus::ReadyToPublish,
            'provider_account_id' => 'tiktok-creator-open-id',
            'access_token' => 'direct-access-token',
            'refresh_token' => 'direct-refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['user.info.basic', 'video.publish'],
            'publishing_capabilities' => [
                'tiktok' => [
                    'can_direct_publish_video' => true,
                    'can_publish_video' => true,
                    'privacy_levels_supported' => $creatorInfo['privacy_level_options'],
                ],
            ],
            'api_metadata' => ['content_posting_info' => $creatorInfo],
        ]);

        return [$user, $campaign->fresh(), $post->fresh(), $account];
    }
}
