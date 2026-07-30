<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Publishing\MetaPublisher;
use App\Models\MarketingCampaignPostPublication;
use App\Models\MarketingCampaignPost;
use App\Models\ClientSocialAccount;
use App\Enums\Social\SocialPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublisherUsesSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_publisher_uses_snapshot_after_post_changes()
    {
        $post = MarketingCampaignPost::factory()->create([
            'content_type' => 'post'
        ]);
        
        $version = \App\Models\MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'caption' => 'Test caption'
        ]);
        
        $post->update(['current_version_id' => $version->id]);
        
        $account = ClientSocialAccount::factory()->create(['platform' => SocialPlatform::Facebook, 'api_status' => 'connected', 'access_token' => 'fake', 'provider_account_id' => 'fake_post_id_123']);
        $post->campaign->update(['client_id' => $account->client_id]);
        
        \App\Models\AgencySocialConnection::create(['id' => 1, 'provider' => 'meta', 'status' => \App\Enums\Social\AgencyConnectionStatus::Connected, 'access_token' => 'dummy', 'connected_by' => 1]);
        
        $asset = \App\Models\AgencySocialAsset::create(['platform' => SocialPlatform::Facebook->value, 'agency_social_connection_id' => 1, 'provider' => 'meta', 'asset_type' => 'facebook_page', 'provider_asset_id' => 'fake_post_id_123']);
        $account->update(['agency_social_asset_id' => $asset->id, 'connection_strategy' => \App\Enums\Social\SocialConnectionStrategy::ManualTokenConfig]);
        
        config(['services.meta.delivery_mode' => 'enabled']);
        config(['services.meta.mock_publishing' => false]);
        config(['social.publishing.dry_run' => false]);
        
        $createAction = app(\App\Domain\Social\Actions\CreateMarketingCampaignPostPublicationAction::class);
        $publication = $createAction->execute(
            $post,
            $post->currentVersion,
            SocialPlatform::Facebook,
            $account
        );

        // Modifico post live per testare che non viene letto
        $post->update([
            'content_type' => 'reel'
        ]);
        $post->currentVersion->update([
            'caption' => 'New Caption Live'
        ]);

        $mockDelivery = $this->createMock(\App\Domain\Social\Services\PublicationMediaDeliveryService::class);
        $mockDelivery->method('deliver')->willReturn([
            new \App\Domain\Social\DTOs\PublicationMediaDeliveryResult(true, 'http://fake.com/img.jpg', [], 'image')
        ]);
        $this->app->instance(\App\Domain\Social\Services\PublicationMediaDeliveryService::class, $mockDelivery);

        Http::fake([
            '*' => Http::response(['id' => 'fake_post_id_123'], 200)
        ]);
        
        $executeAction = app(\App\Domain\Social\Actions\ExecuteMarketingCampaignPostPublicationAction::class);
        $result = $executeAction->execute($publication->id);
        
        $publication->refresh();
        $this->assertEquals(\App\Enums\Social\PublicationStatus::Published, $publication->status, 'Publication failed: ' . $publication->error_message);
        
        \Illuminate\Support\Facades\Log::info("PublishResult: ", ['res' => $result]);

        Http::assertSent(function ($request) {
            $data = $request->data();
            \Illuminate\Support\Facades\Log::info("Request sent: ", $data);
            return isset($data['message']) && str_contains($data['message'], 'Test caption'); 
        });
    }
}
