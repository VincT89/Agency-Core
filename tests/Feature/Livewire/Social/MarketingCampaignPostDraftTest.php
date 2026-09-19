<?php

namespace Tests\Feature\Livewire\Social;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\UserRole;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingCampaignPostDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => UserRole::Marketing]));
    }

    private function draft(): MarketingCampaignPost
    {
        return MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => MarketingCampaign::factory(),
            'status' => MarketingCampaignPostStatus::Draft,
            'title' => 'Titolo iniziale', 'content_type' => 'post',
            'current_version_id' => null, 'ai_analysis_enabled' => true,
        ]);
    }

    public function test_title_only_is_saved_without_media_or_a_new_version(): void
    {
        $post = $this->draft();
        $component = Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $post->campaign, 'post' => $post]);
        $component->assertSeeHtml('wire:submit="saveDraft"')
            ->set('form.title', 'Titolo aggiornato')->call('saveDraft')
            ->assertHasNoErrors()->assertDispatched('post-saved')->assertSee('Bozza aggiornata.');
        $this->assertSame('Titolo aggiornato', $post->fresh()->title);
        $this->assertSame(MarketingCampaignPostStatus::Draft, $post->fresh()->status);
        $this->assertNull($post->fresh()->current_version_id);
        $this->assertDatabaseCount('marketing_campaign_post_versions', 0);
        $component->set('form.title', 'Seconda modifica')->call('saveDraft')->assertHasNoErrors();
        $this->assertSame('Seconda modifica', $post->fresh()->title);
        Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $post->campaign, 'post' => $post->fresh()])
            ->assertSet('form.title', 'Seconda modifica');
    }

    public function test_incomplete_manual_reel_can_remain_a_draft_but_cannot_be_marked_ready(): void
    {
        $post = $this->draft();
        $component = Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $post->campaign, 'post' => $post])
            ->set('form.ai_analysis_enabled', false)->set('form.content_type', 'reel')
            ->set('form.title', 'Reel da completare')->set('form.status', 'published')
            ->call('saveDraft')->assertHasNoErrors();
        $this->assertSame(MarketingCampaignPostStatus::Draft, $post->fresh()->status);
        $component->call('saveAsManualVersion')->assertHasErrors('media');
        $this->assertNull($post->fresh()->current_version_id);
    }

    public function test_date_and_time_changes_persist_without_media_and_can_be_removed(): void
    {
        $post = $this->draft();
        $post->update(['scheduled_date' => '2026-09-20', 'scheduled_time' => '09:30']);
        $component = Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $post->campaign, 'post' => $post])
            ->set('form.scheduled_date', '2026-10-02')->set('form.scheduled_time', '15:45')
            ->call('saveDraft')->assertHasNoErrors();
        $this->assertSame('2026-10-02', $post->fresh()->scheduled_date->toDateString());
        $this->assertSame('15:45', substr($post->fresh()->scheduled_time, 0, 5));
        Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $post->campaign, 'post' => $post->fresh()])
            ->assertSet('form.scheduled_date', '2026-10-02')->assertSet('form.scheduled_time', '15:45');
        $component->set('form.scheduled_date', null)->set('form.scheduled_time', null)->call('saveDraft')->assertHasNoErrors();
        $this->assertNull($post->fresh()->scheduled_date);
        $this->assertNull($post->fresh()->scheduled_time);
        $this->assertDatabaseCount('marketing_campaign_post_versions', 0);
    }

    public function test_draft_media_order_and_removal_are_persisted_without_promotion(): void
    {
        $post = $this->draft();
        $first = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id, 'sort_order' => 0]);
        $second = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id, 'sort_order' => 1]);
        $component = Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $post->campaign, 'post' => $post])
            ->call('reorderSelectedMedia', 0, 1)->call('saveDraft')->assertHasNoErrors();
        $this->assertSame($second->id, $post->mediaItems()->orderBy('sort_order')->first()->id);
        $component->call('removeSelectedMediaItem', 'existing:'.$first->id)->call('saveDraft')->assertHasNoErrors();
        $this->assertDatabaseMissing('marketing_campaign_post_media', ['id' => $first->id]);
        $this->assertSame(MarketingCampaignPostStatus::Draft, $post->fresh()->status);
        $this->assertDatabaseCount('marketing_campaign_post_versions', 0);
    }

    public function test_draft_save_cannot_overwrite_a_version_created_in_another_session(): void
    {
        $post = $this->draft();
        $component = Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $post->campaign, 'post' => $post]);
        $version = $post->versions()->create(['version_number' => 1, 'title' => 'Altra sessione']);
        $post->update(['current_version_id' => $version->id, 'title' => 'Altra sessione', 'status' => MarketingCampaignPostStatus::Generated]);
        $component->set('form.title', 'Modifica obsoleta')->call('saveDraft')->assertHasErrors('post');
        $this->assertSame('Altra sessione', $post->fresh()->title);
        $this->assertSame($version->id, $post->fresh()->current_version_id);
    }
}
