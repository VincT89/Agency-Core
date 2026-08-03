<?php

namespace Tests\Feature\Social;

use App\Enums\Social\AgencyConnectionStatus;
use App\Enums\Social\PublishingStatus;
use App\Enums\Social\SocialAccessMethod;
use App\Enums\Social\SocialAccessStatus;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialAssetType;
use App\Enums\Social\SocialConnectionMode;
use App\Enums\Social\SocialConnectionStrategy;
use App\Enums\Social\SocialPlatform;
use App\Models\AgencySocialAsset;
use App\Models\AgencySocialConnection;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSocialAccountsMetaRequiredTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_is_not_meta_ready_without_accounts()
    {
        $client = Client::factory()->create();
        $this->assertFalse($client->isMetaReady());
    }

    public function test_client_is_meta_ready_when_facebook_and_instagram_are_ready()
    {
        $client = Client::factory()->create();

        $client->socialAccounts()->create([
            'platform' => SocialPlatform::Facebook->value,
            'is_ready_to_publish' => true,
            'access_status' => SocialAccessStatus::ReadyToPublish->value,
            'business_manager_id' => '12345',
            'access_method' => SocialAccessMethod::MetaBusiness->value,
        ]);

        $this->assertFalse($client->isMetaReady()); // Missing IG

        $client->socialAccounts()->create([
            'platform' => SocialPlatform::Instagram->value,
            'is_ready_to_publish' => true,
            'access_status' => SocialAccessStatus::ReadyToPublish->value,
            'business_manager_id' => '12345',
            'access_method' => SocialAccessMethod::MetaBusiness->value,
        ]);

        $this->assertTrue($client->refresh()->isMetaReady());
    }

    public function test_client_is_meta_ready_with_active_agency_oauth_assets(): void
    {
        $client = Client::factory()->create();
        $connection = AgencySocialConnection::forceCreate([
            'provider' => 'facebook',
            'access_token' => 'agency-token',
            'status' => AgencyConnectionStatus::Connected,
            'requires_reauth' => false,
        ]);
        $facebookAsset = AgencySocialAsset::forceCreate([
            'agency_social_connection_id' => $connection->id,
            'provider' => 'facebook',
            'platform' => SocialPlatform::Facebook->value,
            'asset_type' => SocialAssetType::FacebookPage,
            'provider_asset_id' => 'page-123',
            'facebook_page_id' => 'page-123',
            'page_access_token' => 'page-token',
            'status' => AgencyConnectionStatus::Connected,
            'publishing_status' => PublishingStatus::Ready,
            'is_active' => true,
            'is_assignable' => true,
        ]);
        $instagramAsset = AgencySocialAsset::forceCreate([
            'agency_social_connection_id' => $connection->id,
            'parent_asset_id' => $facebookAsset->id,
            'provider' => 'facebook',
            'platform' => SocialPlatform::Instagram->value,
            'asset_type' => SocialAssetType::InstagramBusinessAccount,
            'provider_asset_id' => 'instagram-123',
            'instagram_business_account_id' => 'instagram-123',
            'status' => AgencyConnectionStatus::Connected,
            'publishing_status' => PublishingStatus::Ready,
            'is_active' => true,
            'is_assignable' => true,
        ]);

        $facebook = $client->socialAccounts()->create([
            'platform' => SocialPlatform::Facebook->value,
            'connection_mode' => SocialConnectionMode::Manual,
            'connection_strategy' => SocialConnectionStrategy::AgencyOauth,
            'agency_social_asset_id' => $facebookAsset->id,
            'api_status' => SocialApiStatus::Connected,
        ]);
        $instagram = $client->socialAccounts()->create([
            'platform' => SocialPlatform::Instagram->value,
            'connection_mode' => SocialConnectionMode::Manual,
            'connection_strategy' => SocialConnectionStrategy::AgencyOauth,
            'agency_social_asset_id' => $instagramAsset->id,
            'api_status' => SocialApiStatus::Connected,
        ]);

        $this->assertFalse($facebook->isApiConnected());
        $this->assertFalse($instagram->isApiConnected());
        $this->assertTrue($facebook->isReadyToPublish());
        $this->assertTrue($instagram->isReadyToPublish());
        $this->assertTrue($client->refresh()->isMetaReady());

        $connection->update(['requires_reauth' => true]);

        $this->assertFalse($client->refresh()->isMetaReady());
    }

    public function test_client_is_not_meta_ready_if_business_managers_differ()
    {
        $client = Client::factory()->create();

        $client->socialAccounts()->create([
            'platform' => SocialPlatform::Facebook->value,
            'is_ready_to_publish' => true,
            'access_status' => SocialAccessStatus::ReadyToPublish->value,
            'business_manager_id' => '12345',
            'access_method' => SocialAccessMethod::MetaBusiness->value,
        ]);

        $client->socialAccounts()->create([
            'platform' => SocialPlatform::Instagram->value,
            'is_ready_to_publish' => true,
            'access_status' => SocialAccessStatus::ReadyToPublish->value,
            'business_manager_id' => '99999',
            'access_method' => SocialAccessMethod::MetaBusiness->value,
        ]);

        $this->assertFalse($client->refresh()->isMetaReady());
    }

    public function test_client_is_not_meta_ready_if_access_method_is_not_meta_business()
    {
        $client = Client::factory()->create();

        $client->socialAccounts()->create([
            'platform' => SocialPlatform::Facebook->value,
            'is_ready_to_publish' => true,
            'access_status' => SocialAccessStatus::ReadyToPublish->value,
            'business_manager_id' => '12345',
            'access_method' => SocialAccessMethod::Credentials->value,
        ]);

        $client->socialAccounts()->create([
            'platform' => SocialPlatform::Instagram->value,
            'is_ready_to_publish' => true,
            'access_status' => SocialAccessStatus::ReadyToPublish->value,
            'business_manager_id' => '12345',
            'access_method' => SocialAccessMethod::Credentials->value,
        ]);

        $this->assertFalse($client->refresh()->isMetaReady());
    }

    public function test_tiktok_is_optional_and_tracked_separately()
    {
        $client = Client::factory()->create();

        $account = $client->socialAccounts()->create([
            'platform' => SocialPlatform::Tiktok->value,
            'is_ready_to_publish' => true,
            'access_status' => SocialAccessStatus::ReadyToPublish->value,
        ]);

        $this->assertTrue($account->isTikTok());
        $this->assertTrue($account->isReadyToPublish());
        $this->assertFalse($client->isMetaReady());
    }
}
