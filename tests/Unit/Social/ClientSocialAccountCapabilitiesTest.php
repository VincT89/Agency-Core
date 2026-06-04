<?php

namespace Tests\Unit\Social;

use Tests\TestCase;
use App\Models\ClientSocialAccount;
use App\Enums\Social\SocialPlatform;
use App\Enums\Social\SocialApiStatus;

class ClientSocialAccountCapabilitiesTest extends TestCase
{
    public function test_can_publish_tiktok_video_returns_true_when_configured()
    {
        $account = new ClientSocialAccount([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'dummy_token',
            'publishing_capabilities' => [
                'tiktok' => [
                    'can_upload_video_draft' => true
                ]
            ]
        ]);

        $this->assertTrue($account->canPublishTikTokVideo());
    }

    public function test_can_publish_tiktok_video_returns_false_when_disconnected()
    {
        $account = new ClientSocialAccount([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Disconnected,
            'access_token' => 'dummy_token',
            'publishing_capabilities' => [
                'tiktok' => [
                    'can_upload_video_draft' => true
                ]
            ]
        ]);

        $this->assertFalse($account->canPublishTikTokVideo());
    }

    public function test_can_publish_tiktok_photo_returns_true_when_flag_enabled()
    {
        config(['services.tiktok.enable_photo_mode' => true]);

        $account = new ClientSocialAccount([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'dummy_token',
            'publishing_capabilities' => [
                'tiktok' => [
                    'can_publish_photo' => true
                ]
            ]
        ]);

        $this->assertTrue($account->canPublishTikTokPhoto());
    }

    public function test_can_publish_tiktok_photo_returns_false_when_flag_disabled()
    {
        config(['services.tiktok.enable_photo_mode' => false]);

        $account = new ClientSocialAccount([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'dummy_token',
            'publishing_capabilities' => [
                'tiktok' => [
                    'can_publish_photo' => true
                ]
            ]
        ]);

        $this->assertFalse($account->canPublishTikTokPhoto());
    }
}
