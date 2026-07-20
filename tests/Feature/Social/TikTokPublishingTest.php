<?php

namespace Tests\Feature\Social;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Domain\Social\Services\TikTokPreflightService;
use App\Domain\Social\Publishing\TikTokPublisher;
use App\Enums\Social\SocialPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

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
            'connection_strategy' => \App\Enums\Social\SocialConnectionStrategy::PlatformOauth->value,
            'publishing_capabilities' => ['tiktok' => ['can_publish_video' => true]]
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
        $this->assertContains("TikTok richiede almeno un file multimediale (Video o Foto).", $result->errors);
    }

    public function test_preflight_blocks_mixed_media()
    {
        MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'original_name' => 'image.jpg',
            'media_type' => 'image',
            'path' => 'test/image.jpg',
            'sort_order' => 1
        ]);
        
        MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'original_name' => 'video.mp4',
            'media_type' => 'video',
            'path' => 'test/video.mp4',
            'sort_order' => 2
        ]);
        
        $preflightService = app(TikTokPreflightService::class);
        $result = $preflightService->runPreflight($this->post->fresh(), $this->account);
        
        $this->assertFalse($result->isPass);
        $this->assertContains("TikTok non supporta media misti. Carica solo un video o un set di foto.", $result->errors);
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
            'sort_order' => 1
        ]);

        $mockMediaUrlService = \Mockery::mock(\App\Domain\Social\Services\SocialMediaPublicUrlService::class);
        $mockMediaUrlService->shouldReceive('getValidatedPublicUrl')->andReturn(['url' => 'https://example.com/mock.mp4', 'diagnostic' => []]);
        $this->app->instance(\App\Domain\Social\Services\SocialMediaPublicUrlService::class, $mockMediaUrlService);

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
                'error' => 'invalid_grant'
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
            'sort_order' => 1
        ]);

        $mockMediaUrlService = \Mockery::mock(\App\Domain\Social\Services\SocialMediaPublicUrlService::class);
        $mockMediaUrlService->shouldReceive('getValidatedPublicUrl')->andReturn(['url' => 'https://example.com/mock.mp4', 'diagnostic' => []]);
        $this->app->instance(\App\Domain\Social\Services\SocialMediaPublicUrlService::class, $mockMediaUrlService);

        Http::fake([
            'open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
                'data' => ['publish_id' => 'v.g.12345'],
                'error' => ['code' => 'ok']
            ], 200),
        ]);

        $publication = \App\Models\MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'platform' => SocialPlatform::Tiktok->value,
            'payload_snapshot' => [
                'media' => [
                    ['media_id' => 1]
                ]
            ]
        ]);

        $publisher = app(TikTokPublisher::class);
        $result = $publisher->publish($publication, $this->account);


        $this->assertTrue($result->success, $result->errorMessage . " Config: " . config('services.tiktok.delivery_mode'));
        $this->assertTrue($result->isProcessing());
        $this->assertEquals('v.g.12345', $result->externalTaskId);
    }

    public function test_tiktok_polling_job_marks_failed_status()
    {
        $publication = \App\Models\MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'client_social_account_id' => $this->account->id,
            'platform' => SocialPlatform::Tiktok->value,
            'status' => \App\Enums\Social\PublicationStatus::Publishing->value,
            'external_container_id' => 'v.g.failed123'
        ]);

        Http::fake([
            'open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
                'data' => [
                    'status' => 'FAILED',
                    'fail_reason' => 'Video non supportato'
                ],
                'error' => ['code' => 'ok']
            ], 200),
        ]);

        $job = new \App\Jobs\Social\TikTok\CheckTikTokPostStatusJob($publication->id);
        $job->handle(app(\App\Domain\Social\TikTok\TikTokContentPostingService::class), app(\App\Domain\Social\TikTok\TikTokPostStatusService::class));

        $publication->refresh();
        $this->assertEquals(\App\Enums\Social\PublicationStatus::Failed, $publication->status);
        $this->assertEquals('La pubblicazione è fallita asincronamente su TikTok.', $publication->error_message);
        $this->assertEquals(\App\Enums\Social\MarketingCampaignPostStatus::Failed, $this->post->fresh()->status);
    }
}

