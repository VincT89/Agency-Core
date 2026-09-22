<?php

namespace Tests\Feature\Livewire\Social;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\UserRole;
use App\Jobs\SendMarketingCampaignPostToN8nJob;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostCreate;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarketingCampaignPostCreationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Queue::fake();
        Storage::fake('social_media');
        $this->actingAs(User::factory()->create(['role' => UserRole::Photographer]));
    }

    public static function sodyChoices(): array
    {
        return ['manual' => [false], 'with Sody' => [true]];
    }

    #[DataProvider('sodyChoices')]
    public function test_an_incomplete_reel_can_be_created_as_a_draft_regardless_of_sody(bool $sody): void
    {
        $campaign = MarketingCampaign::factory()->create();
        Livewire::test(MarketingCampaignPostCreate::class, ['campaign' => $campaign])
            ->set('form.ai_analysis_enabled', $sody)
            ->set('form.content_type', 'reel')
            ->set('form.title', null)
            ->set('form.description', null)
            ->set('form.scheduled_date', null)
            ->set('form.scheduled_time', null)
            ->set('form.status', 'published')
            ->assertSee('Salva Bozza')
            ->assertDontSeeHtml('wire:model="form.status"')
            ->call('save')->assertHasNoErrors()->assertRedirect();

        $post = $campaign->posts()->sole();
        $this->assertSame(MarketingCampaignPostStatus::Draft, $post->status);
        $this->assertSame($sody, $post->ai_analysis_enabled);
        $this->assertNull($post->current_version_id);
        $this->assertNull($post->scheduled_date);
        $this->assertDatabaseCount('marketing_campaign_post_versions', 0);
        $this->assertDatabaseCount('marketing_campaign_post_publications', 0);
        Queue::assertNotPushed(SendMarketingCampaignPostToN8nJob::class);

        Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->assertSee('Salva Bozza')
            ->set('form.title', 'Bozza completata in seguito')
            ->call('saveDraft')->assertHasNoErrors();
        $this->assertSame(MarketingCampaignPostStatus::Draft, $post->fresh()->status);
    }

    public function test_manual_reel_draft_preserves_all_seven_files_without_creating_a_ready_version(): void
    {
        $campaign = MarketingCampaign::factory()->create();
        // A small MP4 container is enough to exercise upload MIME detection and storage.
        $content = pack('N', 24).'ftypmp42'.pack('N', 0).'mp42isom'.pack('N', 16).'mdat'.str_repeat("\0", 8);
        $files = array_map(fn ($index) => UploadedFile::fake()->createWithContent("video-{$index}.mp4", $content)->mimeType('video/mp4'), range(1, 7));

        Livewire::test(MarketingCampaignPostCreate::class, ['campaign' => $campaign])
            ->set('form.content_type', 'reel')
            ->set('form.publishing_platforms', ['instagram'])
            ->set('form.ai_analysis_enabled', false)
            ->set('media', $files)
            ->assertSee('Salva Bozza')
            ->assertSee('Salva come pronto')
            ->call('save')->assertHasNoErrors()->assertRedirect();

        $post = $campaign->posts()->sole();
        $this->assertSame(MarketingCampaignPostStatus::Draft, $post->status);
        $this->assertNull($post->current_version_id);
        $media = $post->mediaItems()->orderBy('sort_order')->get();
        $this->assertCount(7, $media);
        foreach ($media as $index => $item) {
            $this->assertSame('video-'.($index + 1).'.mp4', $item->original_name);
            $this->assertSame('video', $item->media_type);
            $this->assertSame(hash('sha256', $content), $item->sha256);
            $this->assertSame($content, Storage::disk('social_media')->get($item->path));
        }
        $this->assertDatabaseCount('marketing_campaign_post_versions', 0);
        Queue::assertNotPushed(SendMarketingCampaignPostToN8nJob::class);
    }

    public function test_an_upload_in_progress_blocks_draft_creation_and_a_failed_upload_can_be_removed(): void
    {
        $campaign = MarketingCampaign::factory()->create();
        $component = Livewire::test(MarketingCampaignPostCreate::class, ['campaign' => $campaign])
            ->set('form.ai_analysis_enabled', false)
            ->call('registerPendingLocalMedia', [['uid' => 'local_pending:video', 'name' => 'video.mov', 'type' => 'video']])
            ->call('save')->assertHasErrors('media');
        $this->assertDatabaseCount('marketing_campaign_posts', 0);

        $component->call('failedLocalMediaUpload', ['local_pending:video'])
            ->assertSet('selected_media_items', [])
            ->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame(MarketingCampaignPostStatus::Draft, MarketingCampaignPost::sole()->status);
        Queue::assertNotPushed(SendMarketingCampaignPostToN8nJob::class);
    }

    public function test_an_incomplete_reel_still_cannot_be_marked_ready(): void
    {
        $campaign = MarketingCampaign::factory()->create();
        Livewire::test(MarketingCampaignPostCreate::class, ['campaign' => $campaign])
            ->set('form.content_type', 'reel')
            ->set('form.ai_analysis_enabled', false)
            ->set('media', [UploadedFile::fake()->image('foto.jpg')])
            ->call('saveAsManualVersion')->assertHasErrors('media');
        $this->assertDatabaseCount('marketing_campaign_posts', 0);
        $this->assertSame([], Storage::disk('social_media')->allFiles());
    }
}
