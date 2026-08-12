<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\ArchiveMarketingCampaignPostAction;
use App\Domain\Social\Actions\CreateMarketingCampaignPostPublicationAction;
use App\Domain\Social\Actions\RestoreMarketingCampaignPostAction;
use App\Domain\Social\Actions\RetryMarketingCampaignPostPublicationAction;
use App\Domain\Social\Exceptions\MarketingCampaignPostArchiveException;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use App\Enums\UserRole;
use App\Livewire\Admin\Social\SocialOperationsDashboard;
use App\Livewire\Social\MarketingCampaignCalendar;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignShow;
use App\Models\Client;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use App\Models\MarketingCampaignPostVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingCampaignPostArchiveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MarketingCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $this->campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
    }

    public function test_failed_post_is_archived_without_deleting_technical_history_and_can_be_restored(): void
    {
        $post = $this->createPost(MarketingCampaignPostStatus::Failed, 'Post da archiviare');
        $publication = $this->createPublication($post, PublicationStatus::Failed, [
            'external_task_id' => 'remote-task-kept',
            'error_message' => 'Errore conservato',
        ]);

        $archived = app(ArchiveMarketingCampaignPostAction::class)->execute($post, $this->admin);

        $this->assertTrue($archived->isArchived());
        $this->assertSame($this->admin->id, $archived->archived_by);
        $this->assertDatabaseHas('marketing_campaign_post_publications', [
            'id' => $publication->id,
            'external_task_id' => 'remote-task-kept',
            'error_message' => 'Errore conservato',
        ]);

        $restored = app(RestoreMarketingCampaignPostAction::class)->execute($archived);

        $this->assertFalse($restored->isArchived());
        $this->assertNull($restored->archived_by);
    }

    public function test_manual_review_post_can_be_archived_but_published_or_active_publications_block_it(): void
    {
        $reviewPost = $this->createPost(MarketingCampaignPostStatus::NeedsManualReview, 'Da verificare');
        $this->createPublication($reviewPost, PublicationStatus::NeedsManualReview);

        $this->assertTrue(
            app(ArchiveMarketingCampaignPostAction::class)
                ->execute($reviewPost, $this->admin)
                ->isArchived()
        );

        foreach ([PublicationStatus::Published, PublicationStatus::Pending, PublicationStatus::Publishing] as $status) {
            $post = $this->createPost(MarketingCampaignPostStatus::Failed, 'Bloccato '.$status->value);
            $this->createPublication($post, $status);

            try {
                app(ArchiveMarketingCampaignPostAction::class)->execute($post, $this->admin);
                $this->fail("Lo stato {$status->value} avrebbe dovuto bloccare l'archiviazione.");
            } catch (MarketingCampaignPostArchiveException) {
                $this->assertNull($post->fresh()->archived_at);
            }
        }
    }

    public function test_archived_post_cannot_create_or_retry_a_publication(): void
    {
        $post = $this->createPost(MarketingCampaignPostStatus::Failed, 'Post bloccato');
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
        ]);
        $post->update(['current_version_id' => $version->id]);
        $publication = $this->createPublication($post, PublicationStatus::Failed, [
            'marketing_campaign_post_version_id' => $version->id,
            'snapshot_schema_version' => 1,
        ]);
        $account = ClientSocialAccount::factory()->create([
            'client_id' => $this->campaign->client_id,
            'platform' => SocialPlatform::Facebook,
        ]);

        $post = app(ArchiveMarketingCampaignPostAction::class)->execute($post, $this->admin);

        try {
            app(CreateMarketingCampaignPostPublicationAction::class)->execute(
                $post,
                $version,
                SocialPlatform::Facebook,
                $account
            );
            $this->fail('Un post archiviato non deve creare nuove pubblicazioni.');
        } catch (MarketingCampaignPostArchiveException) {
            $this->assertDatabaseCount('marketing_campaign_post_publications', 1);
        }

        $this->expectException(MarketingCampaignPostArchiveException::class);
        app(RetryMarketingCampaignPostPublicationAction::class)->execute($publication);
    }

    public function test_archived_post_is_hidden_from_project_and_social_queue_until_restored(): void
    {
        $post = $this->createPost(MarketingCampaignPostStatus::Failed, 'Post fallito visibile');
        $publication = $this->createPublication($post, PublicationStatus::Failed);

        Livewire::actingAs($this->admin)
            ->test(MarketingCampaignShow::class, ['campaign' => $this->campaign])
            ->assertSee('Post fallito visibile')
            ->call('archivePost', $post->id)
            ->assertDontSee('Post fallito visibile')
            ->set('postFilter', 'archived')
            ->assertSee('Post fallito visibile')
            ->assertSee('Ripristina');

        Livewire::actingAs($this->admin)
            ->test(SocialOperationsDashboard::class)
            ->assertDontSee("#{$publication->id}", false)
            ->set('filter', 'archived')
            ->assertSee("#{$publication->id}", false)
            ->call('restorePost', $publication->id)
            ->assertDontSee("#{$publication->id}", false);

        $this->assertNull($post->fresh()->archived_at);
    }

    public function test_default_social_queue_hides_superseded_attempts(): void
    {
        $post = $this->createPost(MarketingCampaignPostStatus::Failed, 'Catena retry');
        $failed = $this->createPublication($post, PublicationStatus::Failed);
        $superseded = $this->createPublication($post, PublicationStatus::Superseded);

        Livewire::actingAs($this->admin)
            ->test(SocialOperationsDashboard::class)
            ->assertSee("#{$failed->id}", false)
            ->assertDontSee("#{$superseded->id}", false)
            ->set('filter', 'attempt_history')
            ->assertDontSee("#{$failed->id}", false)
            ->assertSee("#{$superseded->id}", false);
    }

    public function test_archived_post_is_excluded_from_global_and_campaign_calendars(): void
    {
        $post = $this->createPost(MarketingCampaignPostStatus::Failed, 'Evento da nascondere', [
            'scheduled_date' => '2026-08-12',
            'scheduled_time' => '12:00:00',
        ]);
        app(ArchiveMarketingCampaignPostAction::class)->execute($post, $this->admin);

        Livewire::actingAs($this->admin)
            ->test(MarketingCampaignCalendar::class)
            ->call('fetchEvents')
            ->assertReturned(fn (array $events): bool => ! collect($events)->contains('id', 'post_'.$post->id));

        Livewire::actingAs($this->admin)
            ->test(MarketingCampaignShow::class, ['campaign' => $this->campaign])
            ->call('fetchEvents')
            ->assertReturned(fn (array $events): bool => ! collect($events)->contains('id', $post->id));
    }

    private function createPost(
        MarketingCampaignPostStatus $status,
        string $title,
        array $attributes = []
    ): MarketingCampaignPost {
        return MarketingCampaignPost::factory()->create(array_merge([
            'marketing_campaign_id' => $this->campaign->id,
            'status' => $status,
            'title' => $title,
        ], $attributes));
    }

    private function createPublication(
        MarketingCampaignPost $post,
        PublicationStatus $status,
        array $attributes = []
    ): MarketingCampaignPostPublication {
        return MarketingCampaignPostPublication::factory()->create(array_merge([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Facebook,
            'status' => $status,
        ], $attributes));
    }
}
