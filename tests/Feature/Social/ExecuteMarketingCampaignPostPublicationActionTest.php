<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\ExecuteMarketingCampaignPostPublicationAction;
use App\Domain\Social\DTOs\PreflightResult;
use App\Domain\Social\DTOs\PublicationIntegrityResult;
use App\Domain\Social\Publishing\MetaPublisher;
use App\Domain\Social\Publishing\PublishResult;
use App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier;
use App\Domain\Social\Services\PublicationSnapshotPreflightService;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecuteMarketingCampaignPostPublicationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_executes_successfully()
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create(['marketing_campaign_post_id' => $post->id]);

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Facebook,
            'provider_account_id' => 'provider-123',
        ]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Pending,
            'platform' => SocialPlatform::Facebook,
            'client_social_account_id' => $account->id,
            'snapshot_schema_version' => 1,
            'payload_snapshot' => [
                'target' => [
                    'social_account_id' => $account->id,
                    'external_id' => $account->provider_account_id,
                    'page_id' => $account->provider_account_id,
                    'profile_id' => null,
                ],
                'post_id' => 1,
                'version_id' => 1,
                'media' => [],
            ],
            'marketing_campaign_post_id' => $post->id,
            'marketing_campaign_post_version_id' => $version->id,
        ]);

        // Mock Integrity Verifier
        $verifier = \Mockery::mock(MarketingCampaignPostPublicationIntegrityVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new PublicationIntegrityResult(true));
        $this->app->instance(MarketingCampaignPostPublicationIntegrityVerifier::class, $verifier);

        $preflight = \Mockery::mock(PublicationSnapshotPreflightService::class);
        $preflight->shouldReceive('runPreflight')->andReturn(new PreflightResult(true));
        $this->app->instance(PublicationSnapshotPreflightService::class, $preflight);

        // Mock Publisher
        $publisher = \Mockery::mock(MetaPublisher::class);
        $publisher->shouldReceive('publish')
            ->once()
            ->andReturn(PublishResult::success('ext123', null, null, null, ['snapshot' => 'test']));
        $this->app->instance(MetaPublisher::class, $publisher);

        $action = app(ExecuteMarketingCampaignPostPublicationAction::class);
        $result = $action->execute($publication->id);

        $this->assertTrue($result->success);

        $publication->refresh();
        $this->assertEquals(PublicationStatus::Published, $publication->status);
        $this->assertEquals('ext123', $publication->external_post_id);
    }

    public function test_it_fails_when_publication_not_pending()
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create(['marketing_campaign_post_id' => $post->id]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Publishing,
            'snapshot_schema_version' => 1,
            'marketing_campaign_post_id' => $post->id,
            'marketing_campaign_post_version_id' => $version->id,
        ]);

        $action = app(ExecuteMarketingCampaignPostPublicationAction::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("La publication {$publication->id} non è in stato Pending.");

        $action->execute($publication->id);
    }

    public function test_dry_run_success_is_not_recorded_as_a_real_publication(): void
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
        ]);
        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Facebook,
            'provider_account_id' => 'provider-123',
        ]);
        $publication = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Pending,
            'platform' => SocialPlatform::Facebook,
            'client_social_account_id' => $account->id,
            'snapshot_schema_version' => 1,
            'payload_snapshot' => [
                'target' => [
                    'social_account_id' => $account->id,
                    'external_id' => $account->provider_account_id,
                    'page_id' => $account->provider_account_id,
                    'profile_id' => null,
                ],
                'post_id' => $post->id,
                'version_id' => $version->id,
                'media' => [],
            ],
            'marketing_campaign_post_id' => $post->id,
            'marketing_campaign_post_version_id' => $version->id,
        ]);

        $verifier = \Mockery::mock(MarketingCampaignPostPublicationIntegrityVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new PublicationIntegrityResult(true));
        $this->app->instance(MarketingCampaignPostPublicationIntegrityVerifier::class, $verifier);

        $preflight = \Mockery::mock(PublicationSnapshotPreflightService::class);
        $preflight->shouldReceive('runPreflight')->andReturn(new PreflightResult(true));
        $this->app->instance(PublicationSnapshotPreflightService::class, $preflight);

        $publisher = \Mockery::mock(MetaPublisher::class);
        $publisher->shouldReceive('publish')
            ->once()
            ->andReturn(PublishResult::success('dryrun_meta_123', null, [
                'dry_run' => true,
                'should_not_count_as_real_publication' => true,
            ]));
        $this->app->instance(MetaPublisher::class, $publisher);

        $outcome = app(ExecuteMarketingCampaignPostPublicationAction::class)
            ->execute($publication->id);

        $this->assertFalse($outcome->success);

        $publication->refresh();
        $this->assertSame(PublicationStatus::NeedsManualReview, $publication->status);
        $this->assertNull($publication->external_post_id);
        $this->assertNull($publication->published_at);
        $this->assertSame('dryrun_meta_123', $publication->response_snapshot['simulation_reference']);
        $this->assertStringContainsString('Simulazione completata', $publication->error_message);
    }
}
