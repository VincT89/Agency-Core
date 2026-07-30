<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Publishing\MetaPublisher;
use App\Domain\Social\Services\SocialMediaPublicUrlService;
use App\Enums\Social\MarketingCampaignPostType;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MetaPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_if_ig_reel_has_no_video()
    {
        config(['social.publishing.dry_run' => false]);

        $post = MarketingCampaignPost::factory()->create([
            'content_type' => MarketingCampaignPostType::Reel,
        ]);

        // Add a photo media item instead of a video
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'media_type' => 'photo',
            'mime_type' => 'image/jpeg',
            'path' => 'dummy.jpg',
        ]);

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Instagram,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'dummy_token',
            'provider_account_id' => '123456789',
        ]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Instagram->value,
            'payload_snapshot' => [
                'target' => ['page_id' => '123', 'external_id' => 'fake_external', 'publication_type' => 'reel'], 'caption' => 'Test', 'hashtags' => [], 'media' => [
                    ['media_id' => $media->id, 'media_type' => 'photo', 'path' => 'dummy.jpg'],
                ],
            ],
        ]);

        $mockUrlService = \Mockery::mock(SocialMediaPublicUrlService::class);
        $mockUrlService->shouldReceive('getValidatedPublicUrls')
            ->andReturn([
                [
                    'url' => 'https://agency-core.test/dummy.jpg',
                    'diagnostic' => [],
                ],
            ]);
        $this->app->instance(SocialMediaPublicUrlService::class, $mockUrlService);

        $publisher = app(MetaPublisher::class);
        $result = $publisher->publish($publication, $account, Str::uuid()->toString());

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('Dominio Meta: Un Reel richiede obbligatoriamente un file video.', $result->errorMessage);
    }

    public function test_direct_meta_accounts_accept_platform_specific_target_ids(): void
    {
        $publisher = app(MetaPublisher::class);
        $facebook = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Facebook,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'facebook-token',
            'provider_account_id' => null,
            'facebook_page_id' => 'facebook-page',
        ]);
        $instagram = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Instagram,
            'api_status' => SocialApiStatus::Connected,
            'access_token' => 'instagram-token',
            'provider_account_id' => null,
            'instagram_business_account_id' => 'instagram-profile',
        ]);

        $this->assertTrue(
            $publisher->verifyAccountCapabilities($facebook)
        );
        $this->assertTrue(
            $publisher->verifyAccountCapabilities($instagram)
        );
    }
}
