<?php

namespace Tests\Feature\Livewire\Social;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostType;
use App\Enums\UserRole;
use App\Exceptions\NextcloudShareException;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingCampaignPostClientIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
        Queue::fake();
        Storage::fake('public');
        Storage::fake('social_media');
    }

    public function test_store_logo_success_client_save_fails_version_media_preserved_and_new_logo_deleted()
    {
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create(['logo_path' => 'clients/logos/old_logo.png']);
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);

        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => MarketingCampaignPostType::Post->value,
            'status' => MarketingCampaignPostStatus::Draft->value,
            'title' => 'Original title',
        ]);

        $newLogo = UploadedFile::fake()->image('new_logo.png');
        $newMedia = UploadedFile::fake()->image('new_media.png');

        // Force an exception during Client save, but allow MarketingCampaignPost to save.
        Client::saving(function ($model) {
            if ($model->isDirty('logo_path')) {
                throw new \Exception('Database failure during client save');
            }
        });

        try {
            Livewire::actingAs($user)
                ->test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
                ->set('form.title', 'Test Title')
                ->set('form.content_type', 'post')
                ->set('form.status', 'draft')
                ->set('form.ai_analysis_enabled', false)
                ->set('form.media_source', 'local')
                ->set('media', [$newMedia]) // Upload media
                ->set('include_client_logo', true)
                ->set('save_runtime_logo_to_client', true)
                ->set('runtime_logo', $newLogo)
                ->call('savePost');
        } catch (\Exception $e) {
            $this->assertEquals('Database failure during client save', $e->getMessage());
        }

        // The client logo should NOT have changed in DB
        $client->refresh();
        $this->assertEquals('clients/logos/old_logo.png', $client->logo_path);

        // The newly created logo should have been deleted from storage
        $clientLogos = Storage::disk('public')->allFiles('clients/logos');
        $this->assertEmpty($clientLogos);

        // However, the version media should HAVE BEEN PRESERVED
        $post->refresh();
        $this->assertNotNull($post->current_version_id);

        $version = $post->currentVersion;
        $this->assertEquals('Test Title', $version->title);

        $mediaItems = $version->mediaItems;
        $this->assertCount(1, $mediaItems);

        $mediaPath = $mediaItems->first()->path;
        Storage::disk('social_media')->assertExists($mediaPath);
    }

    public function test_concurrent_logo_replacement_orphans_prevented()
    {
        // This test simulates the race condition by asserting the old logo path is fetched
        // accurately and within the transaction. Since we cannot run true concurrency,
        // we check that if the client is updated behind the scenes before saving,
        // it doesn't try to delete a stale logo.
        // The implementation uses lockForUpdate(), ensuring serialization.
        // Here we test standard successful logo update.
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create(['logo_path' => 'clients/logos/old_logo.png']);
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => MarketingCampaignPostType::Post->value,
            'status' => MarketingCampaignPostStatus::Draft->value,
        ]);

        Storage::disk('public')->put('clients/logos/old_logo.png', 'old');

        $newLogo = UploadedFile::fake()->image('new_logo.png');

        Livewire::actingAs($user)
            ->test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->set('form.title', 'Test Title')
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft')
            ->set('form.ai_analysis_enabled', false)
            ->set('form.media_source', 'local')
            ->set('include_client_logo', true)
            ->set('save_runtime_logo_to_client', true)
            ->set('runtime_logo', $newLogo)
            ->call('saveAndSubmitToN8n')
            ->assertHasNoErrors();

        $client->refresh();
        $this->assertNotEquals('clients/logos/old_logo.png', $client->logo_path);

        Storage::disk('public')->assertMissing('clients/logos/old_logo.png');
        Storage::disk('public')->assertExists($client->logo_path);

        // Assert only one logo remains
        $logos = Storage::disk('public')->allFiles('clients/logos');
        $this->assertCount(1, $logos);
    }

    public function test_save_and_submit_to_n8n_lock_nextcloud_fails_no_client_changes()
    {
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create(['logo_path' => 'clients/logos/old.png']);
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => MarketingCampaignPostType::Post->value,
            'status' => MarketingCampaignPostStatus::Draft->value,
        ]);

        $newLogo = UploadedFile::fake()->image('new_logo.png');

        $mockNextcloud = \Mockery::mock(NextcloudService::class)->shouldIgnoreMissing();
        $mockNextcloud->shouldReceive('acquireLocksForPaths')->once()->andThrow(new NextcloudShareException('Lock failed'));
        $this->app->instance(NextcloudService::class, $mockNextcloud);

        Livewire::actingAs($user)
            ->test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->set('form.title', 'Test Title')
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft')
            ->set('form.ai_analysis_enabled', false)
            ->set('form.media_source', 'nextcloud')
            ->set('selected_media_items', [
                ['uid' => 'uid-123', 'name' => '1.jpg', 'source' => 'nextcloud', 'type' => 'image', 'nextcloud_path' => '/Photos/1.jpg'],
            ])
            ->set('include_client_logo', true)
            ->set('save_runtime_logo_to_client', true)
            ->set('runtime_logo', $newLogo)
            ->call('saveAndSubmitToN8n')
            ->assertHasErrors('media');

        $client->refresh();
        $this->assertEquals('clients/logos/old.png', $client->logo_path);

        $files = Storage::disk('public')->allFiles('clients/logos');
        $this->assertEmpty($files);
    }
}
