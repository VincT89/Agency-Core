<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\CreateMarketingCampaignPostPublicationAction;
use App\Domain\Social\Actions\ExecuteMarketingCampaignPostPublicationAction;
use App\Domain\Social\DTOs\PublicationMediaDeliveryResult;
use App\Domain\Social\Services\PublicationMediaDeliveryService;
use App\Enums\Social\AgencyConnectionStatus;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialConnectionStrategy;
use App\Enums\Social\SocialPlatform;
use App\Models\AgencySocialAsset;
use App\Models\AgencySocialConnection;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublisherUsesSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_publisher_uses_snapshot_after_post_changes()
    {
        $post = MarketingCampaignPost::factory()->create([
            'content_type' => 'post',
        ]);

        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'caption' => 'Test caption',
        ]);

        $post->update(['current_version_id' => $version->id]);

        $account = ClientSocialAccount::factory()->create(['platform' => SocialPlatform::Facebook, 'api_status' => 'connected', 'access_token' => 'fake', 'provider_account_id' => 'fake_post_id_123']);
        $post->campaign->update(['client_id' => $account->client_id]);

        $user = User::factory()->create();
        $connection = AgencySocialConnection::create([
            'provider' => 'meta',
            'status' => AgencyConnectionStatus::Connected,
            'access_token' => 'dummy',
            'connected_by' => $user->id,
        ]);

        $asset = AgencySocialAsset::create([
            'platform' => SocialPlatform::Facebook->value,
            'agency_social_connection_id' => $connection->id,
            'provider' => 'meta',
            'asset_type' => 'facebook_page',
            'provider_asset_id' => 'fake_post_id_123',
        ]);
        $account->update([
            'agency_social_asset_id' => $asset->id,
            'connection_strategy' => SocialConnectionStrategy::ManualTokenConfig,
        ]);

        config(['services.meta.delivery_mode' => 'enabled']);
        config(['services.meta.mock_publishing' => false]);
        config(['social.publishing.dry_run' => false]);

        $createAction = app(CreateMarketingCampaignPostPublicationAction::class);
        $publication = $createAction->execute(
            $post,
            $post->currentVersion,
            SocialPlatform::Facebook,
            $account
        );

        // Modifico post live per testare che non viene letto
        $post->update([
            'content_type' => 'reel',
        ]);
        $post->currentVersion->update([
            'caption' => 'New Caption Live',
        ]);

        $mockDelivery = $this->createMock(PublicationMediaDeliveryService::class);
        $mockDelivery->method('deliver')->willReturn([
            new PublicationMediaDeliveryResult(true, 'http://fake.com/img.jpg', [], 'image'),
        ]);
        $this->app->instance(PublicationMediaDeliveryService::class, $mockDelivery);

        Http::fake([
            '*' => Http::response(['id' => 'fake_post_id_123'], 200),
        ]);

        $executeAction = app(ExecuteMarketingCampaignPostPublicationAction::class);
        $executeAction->execute($publication->id);

        $publication->refresh();
        $this->assertEquals(PublicationStatus::Published, $publication->status, 'Publication failed: '.$publication->error_message);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['message']) && str_contains($data['message'], 'Test caption');
        });
    }
}
