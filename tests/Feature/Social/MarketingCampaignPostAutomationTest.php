<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\EvaluateMarketingCampaignPostAutomationAction;
use App\Domain\Social\Actions\SendMarketingCampaignPostToClientAction;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignStatus;
use App\Enums\Social\PublicationMode;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialPlatform;
use App\Jobs\Social\ExecuteMarketingCampaignPostPublicationJob;
use App\Models\ClientReviewToken;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketingCampaignPostAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_campaign_never_auto_approves_generated_content(): void
    {
        [$campaign, $post] = $this->postForCampaign(
            PublicationMode::Manual,
            false,
            MarketingCampaignPostStatus::Generated
        );

        $result = app(EvaluateMarketingCampaignPostAutomationAction::class)
            ->execute($post);

        $this->assertSame('manual_noop', $result);
        $this->assertSame(
            MarketingCampaignPostStatus::Generated,
            $post->fresh()->status
        );
    }

    public function test_automatic_campaign_without_review_auto_approves_after_generation(): void
    {
        [$campaign, $post] = $this->postForCampaign(
            PublicationMode::Automatic,
            false,
            MarketingCampaignPostStatus::Generated
        );

        $result = app(EvaluateMarketingCampaignPostAutomationAction::class)
            ->execute($post);

        $this->assertSame('auto_approved', $result);
        $this->assertSame(
            MarketingCampaignPostStatus::Approved,
            $post->fresh()->status
        );
    }

    public function test_automatic_campaign_with_review_requests_client_review(): void
    {
        [$campaign, $post] = $this->postForCampaign(
            PublicationMode::Automatic,
            true,
            MarketingCampaignPostStatus::Generated
        );
        $token = new ClientReviewToken;
        $this->mock(SendMarketingCampaignPostToClientAction::class)
            ->shouldReceive('execute')
            ->once()
            ->withArgs(fn (MarketingCampaignPost $candidate): bool => $candidate->is($post))
            ->andReturn($token);

        $result = app(EvaluateMarketingCampaignPostAutomationAction::class)
            ->execute($post);

        $this->assertSame('review_requested', $result);
    }

    public function test_kill_switch_prevents_automatic_dispatch(): void
    {
        Queue::fake();
        config(['social.auto_publish_enabled' => false]);
        [, $post] = $this->duePost(false);

        $this->artisan('social:dispatch-due-publications')
            ->assertSuccessful();

        $this->assertDatabaseMissing('marketing_campaign_post_publications', [
            'marketing_campaign_post_id' => $post->id,
        ]);
        Queue::assertNotPushed(ExecuteMarketingCampaignPostPublicationJob::class);
    }

    public function test_due_automatic_post_is_snapshotted_and_dispatched_once(): void
    {
        Queue::fake();
        config(['social.auto_publish_enabled' => true]);
        [, $post] = $this->duePost(false);
        $this->facebookAccount($post);

        $this->artisan('social:dispatch-due-publications')
            ->assertSuccessful();
        $this->artisan('social:dispatch-due-publications')
            ->assertSuccessful();

        $publication = MarketingCampaignPostPublication::query()->sole();
        $this->assertSame(PublicationStatus::Pending, $publication->status);
        $this->assertSame($post->current_version_id, $publication->marketing_campaign_post_version_id);
        $this->assertNotNull($publication->snapshot_hash);
        $this->assertSame(1, $publication->attempt_count);
        Queue::assertPushed(
            ExecuteMarketingCampaignPostPublicationJob::class,
            1
        );
    }

    public function test_client_review_is_a_hard_gate_for_automatic_dispatch(): void
    {
        Queue::fake();
        config(['social.auto_publish_enabled' => true]);
        [, $post] = $this->duePost(true);
        $this->facebookAccount($post);

        $this->artisan('social:dispatch-due-publications')
            ->assertSuccessful();

        $this->assertSame(0, MarketingCampaignPostPublication::count());

        $post->update([
            'status' => MarketingCampaignPostStatus::ClientApproved,
        ]);
        $this->artisan('social:dispatch-due-publications')
            ->assertSuccessful();

        $this->assertSame(1, MarketingCampaignPostPublication::count());
    }

    public function test_review_gated_posts_do_not_starve_eligible_posts_at_the_batch_limit(): void
    {
        Queue::fake();
        config([
            'social.auto_publish_enabled' => true,
            'social.auto_dispatch_batch_size' => 1,
        ]);

        [, $gatedPost] = $this->duePost(true);
        $this->facebookAccount($gatedPost);
        [, $eligiblePost] = $this->duePost(false);
        $this->facebookAccount($eligiblePost);

        $this->artisan('social:dispatch-due-publications')
            ->assertSuccessful();

        $this->assertDatabaseMissing(
            'marketing_campaign_post_publications',
            ['marketing_campaign_post_id' => $gatedPost->id]
        );
        $this->assertDatabaseHas(
            'marketing_campaign_post_publications',
            ['marketing_campaign_post_id' => $eligiblePost->id]
        );
        Queue::assertPushed(
            ExecuteMarketingCampaignPostPublicationJob::class,
            1
        );
    }

    public function test_invalid_platform_configuration_fails_closed(): void
    {
        Queue::fake();
        config(['social.auto_publish_enabled' => true]);
        [, $post] = $this->duePost(false);
        $post->update([
            'publishing_platforms' => [
                SocialPlatform::Facebook->value,
                'unsupported',
            ],
        ]);
        $this->facebookAccount($post);

        $this->artisan('social:dispatch-due-publications')
            ->assertFailed();

        $this->assertDatabaseMissing(
            'marketing_campaign_post_publications',
            ['marketing_campaign_post_id' => $post->id]
        );
        Queue::assertNotPushed(
            ExecuteMarketingCampaignPostPublicationJob::class
        );
    }

    public function test_multiplatform_jobs_are_queued_only_after_every_snapshot_exists(): void
    {
        Queue::fake();
        config(['social.auto_publish_enabled' => true]);
        [, $post] = $this->duePost(false);
        $post->update([
            'publishing_platforms' => [
                SocialPlatform::Facebook->value,
                SocialPlatform::Instagram->value,
            ],
        ]);
        $this->facebookAccount($post);
        $instagram = ClientSocialAccount::factory()->create([
            'client_id' => $post->campaign->client_id,
            'platform' => SocialPlatform::Instagram,
            'api_status' => SocialApiStatus::Connected,
            'provider_account_id' => null,
            'instagram_business_account_id' => null,
            'publishing_capabilities' => [
                'instagram' => ['enabled' => true],
            ],
        ]);

        $this->artisan('social:dispatch-due-publications')
            ->assertFailed();

        $this->assertSame(1, MarketingCampaignPostPublication::count());
        Queue::assertNotPushed(
            ExecuteMarketingCampaignPostPublicationJob::class
        );

        $instagram->update([
            'provider_account_id' => 'instagram-profile',
            'instagram_business_account_id' => 'instagram-profile',
        ]);

        $this->artisan('social:dispatch-due-publications')
            ->assertSuccessful();

        $this->assertSame(2, MarketingCampaignPostPublication::count());
        Queue::assertPushed(
            ExecuteMarketingCampaignPostPublicationJob::class,
            2
        );
    }

    /**
     * @return array{MarketingCampaign, MarketingCampaignPost}
     */
    private function postForCampaign(
        PublicationMode $mode,
        bool $reviewRequired,
        MarketingCampaignPostStatus $status
    ): array {
        $campaign = MarketingCampaign::factory()->create([
            'status' => MarketingCampaignStatus::Active,
            'publication_mode' => $mode,
            'client_review_required' => $reviewRequired,
        ]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => $status,
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
        ]);
        $post->update(['current_version_id' => $version->id]);

        return [$campaign, $post->fresh()];
    }

    /**
     * @return array{MarketingCampaign, MarketingCampaignPost}
     */
    private function duePost(bool $reviewRequired): array
    {
        [$campaign, $post] = $this->postForCampaign(
            PublicationMode::Automatic,
            $reviewRequired,
            MarketingCampaignPostStatus::Approved
        );
        $post->update([
            'scheduled_date' => now()->subDay()->toDateString(),
            'scheduled_time' => '09:00:00',
            'publishing_platforms' => [SocialPlatform::Facebook->value],
        ]);

        return [$campaign, $post->fresh()];
    }

    private function facebookAccount(
        MarketingCampaignPost $post
    ): ClientSocialAccount {
        return ClientSocialAccount::factory()->create([
            'client_id' => $post->campaign->client_id,
            'platform' => SocialPlatform::Facebook,
            'api_status' => SocialApiStatus::Connected,
            'provider_account_id' => 'page-123',
            'facebook_page_id' => 'page-123',
            'publishing_capabilities' => [
                'facebook' => ['enabled' => true],
            ],
        ]);
    }
}
