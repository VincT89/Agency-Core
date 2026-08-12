<?php

namespace Tests\Unit\Social;

use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use Tests\TestCase;

class ClientSocialAccountCapabilitiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.tiktok.delivery_mode' => 'draft',
            'services.tiktok.direct_publish_enabled' => false,
            'services.tiktok.enable_photo_mode' => false,
        ]);
    }

    public function test_can_publish_tiktok_video_draft_with_upload_scope(): void
    {
        $account = new ClientSocialAccount([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'dummy_token',
            'scopes' => ['user.info.basic', 'video.upload'],
            'publishing_capabilities' => [
                'tiktok' => [
                    'can_upload_video_draft' => true,
                ],
            ],
        ]);

        $this->assertTrue($account->canPublishTikTokVideo());
    }

    public function test_can_publish_tiktok_video_direct_with_publish_scope(): void
    {
        config([
            'services.tiktok.delivery_mode' => 'direct',
            'services.tiktok.direct_publish_enabled' => true,
        ]);

        $account = new ClientSocialAccount([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'dummy_token',
            'scopes' => ['user.info.basic', 'video.publish'],
            'publishing_capabilities' => [
                'tiktok' => [
                    'can_direct_publish_video' => true,
                ],
            ],
        ]);

        $this->assertTrue($account->canPublishTikTokVideo());
    }

    public function test_upload_scope_does_not_authorize_direct_post(): void
    {
        config([
            'services.tiktok.delivery_mode' => 'direct',
            'services.tiktok.direct_publish_enabled' => true,
        ]);

        $account = new ClientSocialAccount([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'dummy_token',
            'scopes' => ['user.info.basic', 'video.upload'],
            'publishing_capabilities' => [
                'tiktok' => [
                    'can_upload_video_draft' => true,
                    'can_publish_video' => true,
                ],
            ],
        ]);

        $this->assertFalse($account->canPublishTikTokVideo());
    }

    public function test_can_publish_tiktok_video_returns_false_when_disconnected(): void
    {
        $account = new ClientSocialAccount([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Disconnected,
            'access_token' => 'dummy_token',
            'scopes' => ['user.info.basic', 'video.upload'],
            'publishing_capabilities' => [
                'tiktok' => [
                    'can_upload_video_draft' => true,
                ],
            ],
        ]);

        $this->assertFalse($account->canPublishTikTokVideo());
    }

    public function test_can_publish_tiktok_photo_returns_true_when_flag_enabled(): void
    {
        config(['services.tiktok.enable_photo_mode' => true]);

        $account = new ClientSocialAccount([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'dummy_token',
            'scopes' => ['user.info.basic', 'video.upload'],
            'publishing_capabilities' => [
                'tiktok' => [
                    'can_publish_photo' => true,
                ],
            ],
        ]);

        $this->assertTrue($account->canPublishTikTokPhoto());
    }

    public function test_can_publish_tiktok_photo_returns_false_when_flag_disabled(): void
    {
        config(['services.tiktok.enable_photo_mode' => false]);

        $account = new ClientSocialAccount([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'dummy_token',
            'scopes' => ['user.info.basic', 'video.upload'],
            'publishing_capabilities' => [
                'tiktok' => [
                    'can_publish_photo' => true,
                ],
            ],
        ]);

        $this->assertFalse($account->canPublishTikTokPhoto());
    }
}
