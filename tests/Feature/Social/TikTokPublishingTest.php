<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Publishing\TikTokPublisher;
use App\Domain\Social\Services\SocialMediaPublicUrlService;
use App\Domain\Social\Services\TikTokPreflightService;
use App\Domain\Social\TikTok\TikTokContentPostingService;
use App\Domain\Social\TikTok\TikTokPostStatusService;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialConnectionStrategy;
use App\Enums\Social\SocialPlatform;
use App\Jobs\Social\TikTok\CheckTikTokPostStatusJob;
use App\Models\Client;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostPublication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TikTokPublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->client = Client::factory()->create();
        $this->campaign = MarketingCampaign::factory()->create(['client_id' => $this->client->id]);

        $this->account = ClientSocialAccount::factory()->create([
            'client_id' => $this->client->id,
            'platform' => SocialPlatform::Tiktok->value,
            'access_token' => 'fake_valid_token',
            'refresh_token' => 'fake_refresh_token',
            'token_expires_at' => now()->addDays(1),
            'provider_account_id' => '123456789',
            'connection_strategy' => SocialConnectionStrategy::PlatformOauth->value,
            'scopes' => ['user.info.basic', 'video.publish'],
            'publishing_capabilities' => [
                'tiktok' => [
                    'can_direct_publish_video' => true,
                    'can_publish_video' => true,
                ],
            ],
        ]);

        $this->post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $this->campaign->id,
        ]);

        config(['services.tiktok.delivery_mode' => 'direct']);
        config(['services.tiktok.direct_publish_enabled' => true]);
        config(['social.publishing.dry_run' => false]);
        config(['services.tiktok.mock_publishing' => false]);
    }

    public function test_preflight_blocks_tiktok_without_media()
    {
        $preflightService = app(TikTokPreflightService::class);
        $result = $preflightService->runPreflight($this->post, $this->account);

        $this->assertFalse($result->isPass);
        $this->assertContains('TikTok richiede almeno un file multimediale (Video o Foto).', $result->errors);
    }

    public function test_preflight_requires_publish_scope_in_direct_mode(): void
    {
        $this->account->update([
            'scopes' => ['user.info.basic', 'video.upload'],
            'publishing_capabilities' => [
                'tiktok' => [
                    'can_upload_video_draft' => true,
                    'can_publish_video' => true,
                ],
            ],
        ]);

        $result = app(TikTokPreflightService::class)
            ->runPreflight($this->post, $this->account->fresh());

        $this->assertFalse($result->isPass);
        $this->assertContains(
            "Account TikTok privo del permesso video.publish. Ricollegare l'account.",
            $result->errors
        );
    }

    public function test_preflight_blocks_mixed_media()
    {
        MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'original_name' => 'image.jpg',
            'media_type' => 'image',
            'path' => 'test/image.jpg',
            'sort_order' => 1,
        ]);

        MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'original_name' => 'video.mp4',
            'media_type' => 'video',
            'path' => 'test/video.mp4',
            'sort_order' => 2,
        ]);

        $preflightService = app(TikTokPreflightService::class);
        $result = $preflightService->runPreflight($this->post->fresh(), $this->account);

        $this->assertFalse($result->isPass);
        $this->assertContains('TikTok non supporta media misti. Carica solo un video o un set di foto.', $result->errors);
    }

    public function test_preflight_refreshes_token_if_expired()
    {
        $this->account->update(['token_expires_at' => now()->subMinutes(10)]);

        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'access_token' => 'new_access_token',
                'refresh_token' => 'new_refresh_token',
                'expires_in' => 86400,
            ], 200),
        ]);

        MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'original_name' => 'video.mp4',
            'media_type' => 'video',
            'path' => 'test/video.mp4',
            'mime_type' => 'video/mp4',
            'sort_order' => 1,
        ]);

        $mockMediaUrlService = \Mockery::mock(SocialMediaPublicUrlService::class);
        $mockMediaUrlService->shouldReceive('getValidatedPublicUrl')->andReturn(['url' => 'https://example.com/mock.mp4', 'diagnostic' => []]);
        $this->app->instance(SocialMediaPublicUrlService::class, $mockMediaUrlService);

        $preflightService = app(TikTokPreflightService::class);
        $result = $preflightService->runPreflight($this->post->fresh(), $this->account);

        $this->assertTrue($result->isPass);
        $this->assertEquals('new_access_token', $this->account->fresh()->access_token);
    }

    public function test_preflight_blocks_if_refresh_fails()
    {
        $this->account->update(['token_expires_at' => now()->subMinutes(10)]);

        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'error' => 'invalid_grant',
            ], 401),
        ]);

        $preflightService = app(TikTokPreflightService::class);
        $result = $preflightService->runPreflight($this->post->fresh(), $this->account);

        $this->assertFalse($result->isPass);
        $this->assertContains("Token TikTok scaduto o non valido e refresh fallito. Ricollegare l'account.", $result->errors);
    }

    public function test_publisher_initializes_video_correctly()
    {
        MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'original_name' => 'video.mp4',
            'media_type' => 'video',
            'path' => 'test/video.mp4',
            'mime_type' => 'video/mp4',
            'sort_order' => 1,
        ]);

        $mockMediaUrlService = \Mockery::mock(SocialMediaPublicUrlService::class);
        $mockMediaUrlService->shouldReceive('getValidatedPublicUrl')->andReturn(['url' => 'https://example.com/mock.mp4', 'diagnostic' => []]);
        $this->app->instance(SocialMediaPublicUrlService::class, $mockMediaUrlService);

        Http::fake([
            'open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
                'data' => [
                    'privacy_level_options' => ['SELF_ONLY'],
                    'comment_disabled' => false,
                    'duet_disabled' => true,
                    'stitch_disabled' => true,
                    'max_video_post_duration_sec' => 600,
                ],
                'error' => ['code' => 'ok'],
            ], 200),
            'open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
                'data' => ['publish_id' => 'v.g.12345'],
                'error' => ['code' => 'ok'],
            ], 200),
        ]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
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
        $result = $publisher->publish($publication, $this->account);

        $this->assertTrue($result->success, $result->errorMessage.' Config: '.config('services.tiktok.delivery_mode'));
        $this->assertTrue($result->isProcessing());
        $this->assertEquals('v.g.12345', $result->externalTaskId);
        $this->assertSame(
            ['SELF_ONLY'],
            $this->account->fresh()
                ->publishing_capabilities['tiktok']['privacy_levels_supported']
        );
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/v2/post/publish/video/init/')
                && $request['post_info']['privacy_level'] === 'SELF_ONLY'
                && $request['post_info']['disable_duet'] === true
                && $request['post_info']['disable_stitch'] === true;
        });
    }

    public function test_tiktok_polling_job_marks_failed_status()
    {
        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'client_social_account_id' => $this->account->id,
            'platform' => SocialPlatform::Tiktok->value,
            'status' => PublicationStatus::Publishing->value,
            'external_container_id' => 'v.g.failed123',
        ]);

        Http::fake([
            'open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
                'data' => [
                    'status' => 'FAILED',
                    'fail_reason' => 'Video non supportato',
                ],
                'error' => ['code' => 'ok'],
            ], 200),
        ]);

        $job = new CheckTikTokPostStatusJob($publication->id);
        $job->handle(app(TikTokContentPostingService::class), app(TikTokPostStatusService::class));

        $publication->refresh();
        $this->assertEquals(PublicationStatus::Failed, $publication->status);
        $this->assertEquals(
            'TikTok ha rifiutato la pubblicazione: Video non supportato',
            $publication->error_message
        );
        $this->assertEquals(MarketingCampaignPostStatus::Failed, $this->post->fresh()->status);
    }
}
