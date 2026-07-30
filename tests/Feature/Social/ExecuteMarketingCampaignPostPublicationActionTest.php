<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\ExecuteMarketingCampaignPostPublicationAction;
use App\Models\MarketingCampaignPostPublication;
use App\Models\ClientSocialAccount;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Support\SocialPublisherFake;
use App\Domain\Social\Publishing\MetaPublisher;
use App\Domain\Social\Publishing\PublishResult;

class ExecuteMarketingCampaignPostPublicationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_executes_successfully()
    {
        $post = \App\Models\MarketingCampaignPost::factory()->create();
        $version = \App\Models\MarketingCampaignPostVersion::factory()->create(['marketing_campaign_post_id' => $post->id]);
        
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
                'media' => []
            ],
            'marketing_campaign_post_id' => $post->id,
            'marketing_campaign_post_version_id' => $version->id,
        ]);

        // Mock Integrity Verifier
        $verifier = \Mockery::mock(\App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new \App\Domain\Social\DTOs\PublicationIntegrityResult(true));
        $this->app->instance(\App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier::class, $verifier);
        
        $preflight = \Mockery::mock(\App\Domain\Social\Services\PublicationSnapshotPreflightService::class);
        $preflight->shouldReceive('runPreflight')->andReturn(new \App\Domain\Social\DTOs\PreflightResult(true));
        $this->app->instance(\App\Domain\Social\Services\PublicationSnapshotPreflightService::class, $preflight);

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
        $post = \App\Models\MarketingCampaignPost::factory()->create();
        $version = \App\Models\MarketingCampaignPostVersion::factory()->create(['marketing_campaign_post_id' => $post->id]);
        
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
}
