<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use App\Domain\Social\DTOs\PublicationIntegrityResult;
use App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier;
use App\Enums\Social\IntegritySeverity;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use App\Livewire\Admin\Social\SocialOperationsDashboard;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public static function providerTabularStatuses(): array
    {
        return [
            'All Published' => [
                [PublicationStatus::Published, PublicationStatus::Published],
                MarketingCampaignPostStatus::Published,
            ],
            'Published and Failed' => [
                [PublicationStatus::Published, PublicationStatus::Failed],
                MarketingCampaignPostStatus::PartialSuccess,
            ],
            'Published and NeedsManualReview' => [
                [
                    PublicationStatus::Published,
                    PublicationStatus::NeedsManualReview,
                ],
                MarketingCampaignPostStatus::PartialSuccess,
            ],
            'Failed and NeedsManualReview' => [
                [PublicationStatus::Failed, PublicationStatus::NeedsManualReview],
                MarketingCampaignPostStatus::NeedsManualReview,
            ],
            'Publishing and Published' => [
                [PublicationStatus::Publishing, PublicationStatus::Published],
                MarketingCampaignPostStatus::Publishing,
            ],
        ];
    }

    #[DataProvider('providerTabularStatuses')]
    public function test_sync_action_calculates_correct_status(array $publicationStatuses, MarketingCampaignPostStatus $expectedPostStatus)
    {
        $post = MarketingCampaignPost::factory()->create();

        $platforms = [SocialPlatform::Instagram->value, SocialPlatform::Facebook->value, SocialPlatform::Tiktok->value];
        $index = 0;
        foreach ($publicationStatuses as $status) {
            MarketingCampaignPostPublication::factory()->create([
                'marketing_campaign_post_id' => $post->id,
                'status' => $status->value,
                'platform' => $platforms[$index % count($platforms)],
            ]);
            $index++;
        }

        $action = new SyncMarketingCampaignPostPublicationStatusAction;
        $action->execute($post);

        $post->refresh();
        $this->assertEquals($expectedPostStatus, $post->status);
    }

    public function test_sync_ignores_superseded_and_abandoned()
    {
        $post = MarketingCampaignPost::factory()->create();

        MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'status' => PublicationStatus::Published->value,
            'platform' => SocialPlatform::Facebook->value,
        ]);

        MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'status' => PublicationStatus::Superseded->value, // should be ignored
            'platform' => SocialPlatform::Instagram->value,
        ]);

        MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'status' => PublicationStatus::Abandoned->value, // should be ignored
            'platform' => SocialPlatform::Tiktok->value,
        ]);

        $action = new SyncMarketingCampaignPostPublicationStatusAction;
        $action->execute($post);

        $post->refresh();
        // Since only 'Published' is active, the post should be Published.
        $this->assertEquals(MarketingCampaignPostStatus::Published, $post->status);
    }

    public function test_sync_returns_approved_if_no_active_publications()
    {
        $post = MarketingCampaignPost::factory()->create();

        MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'status' => PublicationStatus::Superseded->value, // ignored
        ]);

        $action = new SyncMarketingCampaignPostPublicationStatusAction;
        $action->execute($post);

        $post->refresh();
        // Fallback or "no active" state for a post ready to be published is typically Approved
        $this->assertEquals(MarketingCampaignPostStatus::Approved, $post->status);
    }

    public function test_sync_reloads_a_previously_loaded_publication_relation(): void
    {
        $post = MarketingCampaignPost::factory()->create();
        $post->load('publications');
        MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'status' => PublicationStatus::Published,
            'platform' => SocialPlatform::Facebook,
        ]);

        (new SyncMarketingCampaignPostPublicationStatusAction)->execute($post);

        $this->assertSame(
            MarketingCampaignPostStatus::Published,
            $post->fresh()->status
        );
    }

    public function test_dashboard_retry_marks_old_publication_superseded()
    {
        $post = MarketingCampaignPost::factory()->create();
        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Instagram,
        ]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'client_social_account_id' => $account->id,
            'platform' => SocialPlatform::Instagram->value,
            'status' => PublicationStatus::NeedsManualReview->value,
            'snapshot_schema_version' => '1.0',
            'snapshot_hash' => 'dummy',
            'attempt_count' => 1,
            'payload_snapshot' => [],
        ]);

        $verifierMock = \Mockery::mock(MarketingCampaignPostPublicationIntegrityVerifier::class);
        $verifierMock->shouldReceive('verify')->andReturn(new PublicationIntegrityResult(
            passed: true,
            severity: IntegritySeverity::Error,
            errors: []
        ));
        $this->app->instance(MarketingCampaignPostPublicationIntegrityVerifier::class, $verifierMock);

        $admin = User::factory()->create(['role' => 'admin'] ?? []);
        $this->actingAs($admin);

        $component = Livewire::test(SocialOperationsDashboard::class)
            ->call('retryPublication', $publication->id);

        $publication->refresh();

        $this->assertEquals(PublicationStatus::Superseded, $publication->status);
        $this->assertEquals('Dismesso (sostituito da nuovo tentativo)', $publication->error_message);
    }
}
