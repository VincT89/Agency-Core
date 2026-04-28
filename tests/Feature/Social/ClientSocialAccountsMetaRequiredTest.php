<?php

namespace Tests\Feature\Social;

use App\Models\User;
use App\Models\Client;
use App\Enums\Social\SocialPlatform;
use App\Enums\Social\SocialAccessMethod;
use App\Enums\Social\SocialAccessStatus;
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
