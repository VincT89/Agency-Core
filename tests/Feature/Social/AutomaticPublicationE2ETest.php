<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignStatus;
use App\Enums\Social\PublicationMode;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialPlatform;
use App\Jobs\Social\ExecuteMarketingCampaignPostPublicationJob;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomaticPublicationE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_due_multiplatform_post_is_snapshotted_queued_and_reconciled_as_partial_success(): void
    {
        Queue::fake();
        config(['social.auto_publish_enabled' => true]);

        $campaign = MarketingCampaign::factory()->create([
            'status' => MarketingCampaignStatus::Active,
            'publication_mode' => PublicationMode::Automatic,
            'client_review_required' => false,
        ]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Approved,
            'scheduled_date' => now()->subDay()->toDateString(),
            'scheduled_time' => '09:00:00',
            'publishing_platforms' => [
                SocialPlatform::Facebook->value,
                SocialPlatform::Instagram->value,
            ],
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
        ]);
        $post->update(['current_version_id' => $version->id]);

        $this->readyAccount(
            $campaign->client_id,
            SocialPlatform::Facebook,
            'facebook-page'
        );
        $this->readyAccount(
            $campaign->client_id,
            SocialPlatform::Instagram,
            'instagram-profile'
        );

        $this->artisan('social:dispatch-due-publications')
            ->assertSuccessful();

        $publications = MarketingCampaignPostPublication::query()
            ->where('marketing_campaign_post_id', $post->id)
            ->orderBy('platform')
            ->get();

        $this->assertCount(2, $publications);
        $this->assertSame(
            [
                SocialPlatform::Facebook->value,
                SocialPlatform::Instagram->value,
            ],
            $publications
                ->pluck('platform')
                ->map(fn (SocialPlatform $platform): string => $platform->value)
                ->sort()
                ->values()
                ->all()
        );
        $this->assertSame(
            [$version->id],
            $publications
                ->pluck('marketing_campaign_post_version_id')
                ->unique()
                ->values()
                ->all()
        );
        Queue::assertPushed(
            ExecuteMarketingCampaignPostPublicationJob::class,
            2
        );

        $publications
            ->firstWhere('platform', SocialPlatform::Facebook)
            ->update(['status' => PublicationStatus::Published]);
        $publications
            ->firstWhere('platform', SocialPlatform::Instagram)
            ->update(['status' => PublicationStatus::Failed]);

        app(SyncMarketingCampaignPostPublicationStatusAction::class)
            ->execute($post->fresh());

        $this->assertSame(
            MarketingCampaignPostStatus::PartialSuccess,
            $post->fresh()->status
        );
    }

    public function test_future_multiplatform_post_is_not_dispatched_early(): void
    {
        Queue::fake();
        config(['social.auto_publish_enabled' => true]);

        $campaign = MarketingCampaign::factory()->create([
            'status' => MarketingCampaignStatus::Active,
            'publication_mode' => PublicationMode::Automatic,
            'client_review_required' => false,
        ]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Approved,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '09:00:00',
            'publishing_platforms' => [
                SocialPlatform::Facebook->value,
            ],
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
        ]);
        $post->update(['current_version_id' => $version->id]);
        $this->readyAccount(
            $campaign->client_id,
            SocialPlatform::Facebook,
            'facebook-page'
        );

        $this->artisan('social:dispatch-due-publications')
            ->assertSuccessful();

        $this->assertDatabaseMissing(
            'marketing_campaign_post_publications',
            ['marketing_campaign_post_id' => $post->id]
        );
        Queue::assertNotPushed(
            ExecuteMarketingCampaignPostPublicationJob::class
        );
    }

    private function readyAccount(
        int $clientId,
        SocialPlatform $platform,
        string $externalId
    ): ClientSocialAccount {
        return ClientSocialAccount::factory()->create([
            'client_id' => $clientId,
            'platform' => $platform,
            'api_status' => SocialApiStatus::Connected,
            'provider_account_id' => $externalId,
            'facebook_page_id' => $platform === SocialPlatform::Facebook
                ? $externalId
                : null,
            'instagram_business_account_id' => $platform === SocialPlatform::Instagram
                    ? $externalId
                    : null,
            'publishing_capabilities' => [
                $platform->value => ['enabled' => true],
            ],
        ]);
    }
}
