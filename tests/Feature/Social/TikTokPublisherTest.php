<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Publishing\PublishResult;
use App\Domain\Social\Publishing\TikTokPublisher;
use App\Domain\Social\Services\SocialMediaPublicUrlService;
use App\Domain\Social\TikTok\TikTokContentPostingService;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Cache\Lock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class TikTokPublisherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.tiktok.delivery_mode', 'draft');
        Config::set('social.publishing.dry_run', false);
        Config::set('services.tiktok.mock_publishing', false);
        URL::forceRootUrl('https://agency-core.test');
        URL::forceScheme('https');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function test_fails_if_mixed_media()
    {
        $post = MarketingCampaignPost::factory()->create();
        MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id, 'media_type' => 'video', 'mime_type' => 'video/mp4']);
        MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id, 'media_type' => 'photo', 'mime_type' => 'image/jpeg']);

        $account = ClientSocialAccount::factory()->create(['platform' => SocialPlatform::Tiktok, 'api_status' => SocialApiStatus::Connected]);

        $post->load('orderedMediaItems'); // Reload to ensure relation is populated

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Tiktok->value,
            'payload_snapshot' => [
                'media' => [
                    ['media_id' => 1, 'media_type' => 'video', 'path' => 'video.mp4'],
                    ['media_id' => 2],
                ],
            ],
        ]);

        $publisher = app(TikTokPublisher::class);
        $result = $publisher->publish($publication, $account, Str::uuid()->toString());

        $this->assertFalse($result->success);
        $this->assertEquals('TikTok non supporta media misti. Carica solo un video o un set di foto.', $result->errorMessage);
    }

    public function test_publish_creates_publication_with_mockery()
    {
        $post = MarketingCampaignPost::factory()->create();
        MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id, 'media_type' => 'video', 'mime_type' => 'video/mp4']);

        $post->load('orderedMediaItems');

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'token',
            'token_expires_at' => now()->addDays(1),
            'publishing_capabilities' => ['tiktok' => ['can_upload_video_draft' => true]],
            'scopes' => ['video.upload'],
        ]);

        $mockService = Mockery::mock(TikTokContentPostingService::class);
        $mockService->shouldReceive('initializeVideoPost')
            ->once()
            ->andReturn(['publish_id' => 'dummy_external_id']);

        $this->app->instance(TikTokContentPostingService::class, $mockService);

        $mockUrlService = Mockery::mock(SocialMediaPublicUrlService::class);
        $mockUrlService->shouldReceive('getValidatedPublicUrl')
            ->andReturn([
                'url' => 'https://agency-core.test/dummy.mp4',
                'diagnostic' => [],
            ]);
        $this->app->instance(SocialMediaPublicUrlService::class, $mockUrlService);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Tiktok->value,
            'payload_snapshot' => [
                'media' => [
                    [
                        'media_id' => 1,
                        'storage_source' => 'local',
                        'disk' => 'public',
                        'path' => 'video.mp4',
                        'mime_type' => 'video/mp4',
                        'media_type' => 'video',
                        'size_bytes' => 100,
                    ],
                ],
            ],
        ]);

        $publisher = app(TikTokPublisher::class);

        // TikTok publishing returns async processing state
        $result = $publisher->publish($publication, $account, Str::uuid()->toString());

        $this->assertInstanceOf(PublishResult::class, $result);
        $this->assertTrue($result->isProcessing(), 'Publish failed with error: '.$result->errorMessage);
        $this->assertEquals('dummy_external_id', $result->externalTaskId);
    }

    public function test_aborts_on_lock_collision()
    {
        $post = MarketingCampaignPost::factory()->create();
        MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id, 'media_type' => 'video', 'mime_type' => 'video/mp4']);

        $post->load('orderedMediaItems');

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'token',
            'token_expires_at' => now()->addDays(1),
            'publishing_capabilities' => ['tiktok' => ['can_upload_video_draft' => true]],
            'scopes' => ['video.upload'],
        ]);

        $lockKey = "tiktok_publish_lock_{$post->id}_{$account->id}";
        $mockLock = Mockery::mock(Lock::class);
        $mockLock->shouldReceive('get')->once()->andReturn(false); // Simulate lock not acquired
        Cache::shouldReceive('lock')->with($lockKey, 300)->andReturn($mockLock);

        $mockUrlService = Mockery::mock(SocialMediaPublicUrlService::class);
        $mockUrlService->shouldReceive('getValidatedPublicUrl')
            ->andReturn([
                'url' => 'https://agency-core.test/dummy.mp4',
                'diagnostic' => [],
            ]);
        $this->app->instance(SocialMediaPublicUrlService::class, $mockUrlService);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Tiktok->value,
            'payload_snapshot' => [
                'media' => [
                    [
                        'media_id' => 1,
                        'storage_source' => 'local',
                        'disk' => 'public',
                        'path' => 'video.mp4',
                        'mime_type' => 'video/mp4',
                        'media_type' => 'video',
                        'size_bytes' => 100,
                    ],
                ],
            ],
        ]);

        $publisher = app(TikTokPublisher::class);
        $result = $publisher->publish($publication, $account, Str::uuid()->toString());

        $this->assertFalse($result->success);
        $this->assertEquals('Pubblicazione già in corso, attendere.', $result->errorMessage);
    }

    public function test_rejects_photo_if_disabled_in_config()
    {
        Config::set('services.tiktok.enable_photo_mode', false);

        $post = MarketingCampaignPost::factory()->create();
        MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id, 'media_type' => 'photo', 'mime_type' => 'image/jpeg']);

        $post->load('orderedMediaItems');

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'token',
            'token_expires_at' => now()->addDays(1),
            'publishing_capabilities' => ['tiktok' => ['can_publish_photo' => true]],
        ]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Tiktok->value,
            'payload_snapshot' => [
                'media' => [
                    ['media_id' => 1, 'media_type' => 'photo', 'path' => 'photo.jpg'],
                ],
            ],
        ]);

        $publisher = app(TikTokPublisher::class);
        $result = $publisher->publish($publication, $account, Str::uuid()->toString());

        $this->assertFalse($result->success);
        $this->assertEquals('La pubblicazione foto su TikTok non è supportata o disabilitata dalle configurazioni di questo account.', $result->errorMessage);
    }
}
