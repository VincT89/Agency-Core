<?php

namespace Tests\Feature\Social;

use Tests\TestCase;
use App\Models\MarketingCampaignPostPublication;
use App\Models\MarketingCampaignPost;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;

class PublicationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public static function providerTabularStatuses(): array
    {
        return [
            'All Published' => [
                [PublicationStatus::Published, PublicationStatus::Published],
                MarketingCampaignPostStatus::Published
            ],
            'Published and Failed' => [
                [PublicationStatus::Published, PublicationStatus::Failed],
                MarketingCampaignPostStatus::PartialSuccess
            ],
            'Failed and NeedsManualReview' => [
                [PublicationStatus::Failed, PublicationStatus::NeedsManualReview],
                MarketingCampaignPostStatus::NeedsManualReview
            ],
            'Publishing and Published' => [
                [PublicationStatus::Publishing, PublicationStatus::Published],
                MarketingCampaignPostStatus::Publishing
            ],
        ];
    }

    #[DataProvider('providerTabularStatuses')]
    public function test_sync_action_calculates_correct_status(array $publicationStatuses, MarketingCampaignPostStatus $expectedPostStatus)
    {
        $post = MarketingCampaignPost::factory()->create();

        $platforms = [\App\Enums\Social\SocialPlatform::Instagram->value, \App\Enums\Social\SocialPlatform::Facebook->value, \App\Enums\Social\SocialPlatform::Tiktok->value];
        $index = 0;
        foreach ($publicationStatuses as $status) {
            MarketingCampaignPostPublication::factory()->create([
                'marketing_campaign_post_id' => $post->id,
                'status' => $status->value,
                'platform' => $platforms[$index % count($platforms)],
            ]);
            $index++;
        }

        $action = new SyncMarketingCampaignPostPublicationStatusAction();
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
            'platform' => \App\Enums\Social\SocialPlatform::Facebook->value,
        ]);

        MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'status' => PublicationStatus::Superseded->value, // should be ignored
            'platform' => \App\Enums\Social\SocialPlatform::Instagram->value,
        ]);

        MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'status' => PublicationStatus::Abandoned->value, // should be ignored
            'platform' => \App\Enums\Social\SocialPlatform::Tiktok->value,
        ]);

        $action = new SyncMarketingCampaignPostPublicationStatusAction();
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

        $action = new SyncMarketingCampaignPostPublicationStatusAction();
        $action->execute($post);

        $post->refresh();
        // Fallback or "no active" state for a post ready to be published is typically Approved
        $this->assertEquals(MarketingCampaignPostStatus::Approved, $post->status);
    }

    public function test_dashboard_retry_marks_old_publication_superseded()
    {
        $post = MarketingCampaignPost::factory()->create();
        $account = \App\Models\ClientSocialAccount::factory()->create([
            'platform' => \App\Enums\Social\SocialPlatform::Instagram,
        ]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'client_social_account_id' => $account->id,
            'platform' => \App\Enums\Social\SocialPlatform::Instagram->value,
            'status' => PublicationStatus::NeedsManualReview->value,
            'snapshot_schema_version' => '1.0',
            'snapshot_hash' => 'dummy',
            'attempt_count' => 1,
            'payload_snapshot' => [],
        ]);

        $verifierMock = \Mockery::mock(\App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier::class);
        $verifierMock->shouldReceive('verify')->andReturn(new \App\Domain\Social\DTOs\PublicationIntegrityResult(
            passed: true,
            severity: \App\Enums\Social\IntegritySeverity::Error,
            errors: []
        ));
        $this->app->instance(\App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier::class, $verifierMock);

        $admin = \App\Models\User::factory()->create(['role' => 'admin'] ?? []);
        $this->actingAs($admin);

        $component = \Livewire\Livewire::test(\App\Livewire\Admin\Social\SocialOperationsDashboard::class)
            ->call('retryPublication', $publication->id);

        $publication->refresh();
        
        $this->assertEquals(PublicationStatus::Superseded, $publication->status);
        $this->assertEquals('Dismesso (sostituito da nuovo tentativo)', $publication->error_message);
    }
}
