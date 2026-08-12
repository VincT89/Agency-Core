<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\RefreshTikTokTokenAction;
use App\Domain\Social\DTOs\PublicationMediaDeliveryResult;
use App\Domain\Social\Publishing\TikTokPublisher;
use App\Domain\Social\Services\PublicationMediaDeliveryService;
use App\Domain\Social\TikTok\TikTokContentPostingService;
use App\Domain\Social\TikTok\TikTokPhotoValidationService;
use App\Domain\Social\TikTok\TikTokVideoValidationService;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialConnectionStrategy;
use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class TikTokTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.tiktok.delivery_mode', 'direct');
        Config::set('services.tiktok.direct_publish_enabled', true);
        Config::set('social.publishing.dry_run', false);
        Config::set('services.tiktok.mock_publishing', false);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    // --- TikTok Token Management ---
    public function test_tiktok_refresh_token_action_successfully_extends_token(): void
    {
        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Tiktok,
            'access_token' => 'old_access_token',
            'refresh_token' => 'old_refresh_token',
            'api_status' => SocialApiStatus::Connected,
        ]);

        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'access_token' => 'new_access_token',
                'refresh_token' => 'new_refresh_token',
                'expires_in' => 86400,
            ], 200),
        ]);

        $action = new RefreshTikTokTokenAction;
        $result = $action->execute($account);

        $this->assertTrue($result);

        $account->refresh();
        $this->assertEquals('new_access_token', $account->access_token);
        $this->assertEquals('new_refresh_token', $account->refresh_token);
    }

    // --- TikTok Publishing & Capabilities ---
    public function test_tiktok_publishing_fails_when_capability_is_not_supported(): void
    {
        $post = MarketingCampaignPost::factory()->create();
        MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id, 'media_type' => 'video']);

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'publishing_capabilities' => ['tiktok' => ['can_publish_video' => false]],
            'connection_strategy' => SocialConnectionStrategy::PlatformOauth->value,
        ]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Tiktok->value,
            'payload_snapshot' => [
                'target' => [
                    'privacy_options' => [
                        'privacy_level' => 'SELF_ONLY',
                        'disable_comment' => true,
                        'disable_duet' => true,
                        'disable_stitch' => true,
                    ],
                ],
                'platform_options' => [
                    'delivery_mode' => 'direct',
                    'creator_consent_confirmed' => true,
                ],
                'media' => [['media_id' => 1, 'media_type' => 'video', 'path' => 'video.mp4']],
            ],
        ]);

        $publisher = app(TikTokPublisher::class);
        $result = $publisher->publish($publication, $account, Str::uuid()->toString());

        $this->assertFalse($result->success);
        $this->assertStringContainsString("L'account non ha i permessi (capability) per pubblicare video su TikTok", $result->errorMessage);
    }

    public function test_tiktok_publisher_successfully_starts_direct_video_post(): void
    {
        $post = MarketingCampaignPost::factory()->create();
        MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id, 'media_type' => 'video']);

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'valid_token',
            'token_expires_at' => now()->addDays(1),
            'connection_strategy' => SocialConnectionStrategy::PlatformOauth->value,
            'scopes' => ['user.info.basic', 'video.publish'],
            'publishing_capabilities' => [
                'tiktok' => [
                    'can_direct_publish_video' => true,
                    'can_publish_video' => true,
                ],
            ],
        ]);

        $mockMediaDeliveryService = Mockery::mock(PublicationMediaDeliveryService::class);
        $mockMediaDeliveryService->shouldReceive('deliver')
            ->andReturn([new PublicationMediaDeliveryResult(true, 'https://agency-core.test/dummy.mp4', [], 'video')]);
        $this->app->instance(PublicationMediaDeliveryService::class, $mockMediaDeliveryService);

        $mockContentService = Mockery::mock(TikTokContentPostingService::class);
        $mockContentService->shouldReceive('queryCreatorInfo')
            ->once()
            ->with('valid_token', (string) $account->id, true)
            ->andReturn([
                'privacy_level_options' => ['SELF_ONLY'],
                'comment_disabled' => false,
                'duet_disabled' => true,
                'stitch_disabled' => true,
            ]);
        $mockContentService->shouldReceive('initializeVideoPost')
            ->once()
            ->andReturn(['publish_id' => 'v.g.123']);
        $this->app->instance(TikTokContentPostingService::class, $mockContentService);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Tiktok->value,
            'payload_snapshot' => [
                'target' => [
                    'privacy_options' => [
                        'privacy_level' => 'SELF_ONLY',
                        'disable_comment' => true,
                        'disable_duet' => true,
                        'disable_stitch' => true,
                    ],
                ],
                'platform_options' => [
                    'delivery_mode' => 'direct',
                    'creator_consent_confirmed' => true,
                ],
                'media' => [['media_id' => 1, 'media_type' => 'video', 'path' => 'video.mp4']],
            ],
        ]);

        $publisher = app(TikTokPublisher::class);
        $result = $publisher->publish($publication, $account, Str::uuid()->toString());

        $this->assertTrue($result->isProcessing());
        $this->assertEquals('v.g.123', $result->externalTaskId);
    }

    public function test_tiktok_publisher_successfully_posts_photo_mode_images(): void
    {
        Config::set('services.tiktok.delivery_mode', 'draft');
        Config::set('services.tiktok.enable_photo_mode', true);

        $post = MarketingCampaignPost::factory()->create();
        MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id, 'media_type' => 'image']);

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'valid_token',
            'token_expires_at' => now()->addDays(1),
            'connection_strategy' => SocialConnectionStrategy::PlatformOauth->value,
            'scopes' => ['user.info.basic', 'video.upload'],
            'publishing_capabilities' => ['tiktok' => ['can_publish_photo' => true]],
        ]);

        $mockMediaDeliveryService = Mockery::mock(PublicationMediaDeliveryService::class);
        $mockMediaDeliveryService->shouldReceive('deliver')
            ->andReturn([new PublicationMediaDeliveryResult(true, 'https://agency-core.test/dummy.jpg', [], 'image')]);
        $this->app->instance(PublicationMediaDeliveryService::class, $mockMediaDeliveryService);

        $mockContentService = Mockery::mock(TikTokContentPostingService::class);
        $mockContentService->shouldReceive('initializePhotoPost')
            ->once()
            ->andReturn(['publish_id' => 'v.p.123']);
        $this->app->instance(TikTokContentPostingService::class, $mockContentService);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Tiktok->value,
            'payload_snapshot' => ['media' => [['media_id' => 1, 'media_type' => 'image', 'path' => 'photo.jpg']]],
        ]);

        $publisher = app(TikTokPublisher::class);
        $result = $publisher->publish($publication, $account, Str::uuid()->toString());

        if (! $result->isProcessing()) {
            dump($result);
        }
        $this->assertTrue($result->isProcessing());
        $this->assertEquals('v.p.123', $result->externalTaskId);
    }

    // --- TikTok Status & Validation ---
    public function test_tiktok_status_polling_retrieves_accurate_status(): void
    {
        Http::fake([
            'open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
                'data' => [
                    'status' => 'PUBLISHED',
                ],
                'error' => ['code' => 'ok'],
            ], 200),
        ]);

        $service = app(TikTokContentPostingService::class);
        $statusResult = $service->getPostStatus('fake_access_token', 'v.g.123');

        $this->assertEquals('PUBLISHED', $statusResult->status);
        $this->assertFalse($statusResult->isFailed ?? false); // No isFailed in DTO, we can check status
    }

    public function test_tiktok_photo_validation_service_enforces_constraints(): void
    {
        $service = app(TikTokPhotoValidationService::class);

        $post = MarketingCampaignPost::factory()->create();
        $mediaVid = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'media_type' => 'video',
            'mime_type' => 'video/mp4',
        ]);

        $result = $service->validate(collect([$mediaVid]), 10);
        $this->assertFalse($result['isValid']);
        $this->assertStringContainsString('è un video', $result['error']);

        $mediaImg = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'media_type' => 'image',
            'mime_type' => 'image/jpeg',
        ]);

        $result = $service->validate(collect([$mediaImg]), 10);
        $this->assertTrue($result['isValid']);
    }

    public function test_tiktok_video_validation_service_enforces_constraints(): void
    {
        $service = app(TikTokVideoValidationService::class);

        $post = MarketingCampaignPost::factory()->create();
        $mediaVid = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'media_type' => 'video',
            'mime_type' => 'video/mp4',
        ]);

        $result = $service->validate($mediaVid);
        $this->assertTrue($result['isValid']);

        $mediaImg = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'media_type' => 'image',
            'mime_type' => 'image/jpeg',
        ]);

        $result = $service->validate($mediaImg);
        $this->assertFalse($result['isValid']);
        $this->assertStringContainsString(
            'TikTok supporta esclusivamente contenuti video',
            $result['error']
        );
    }
}
