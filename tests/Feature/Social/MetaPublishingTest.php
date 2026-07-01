<?php

namespace Tests\Feature\Social;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Domain\Social\Services\MetaPreflightService;
use App\Enums\Social\SocialPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MetaPublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\URL::forceRootUrl('https://agency-core.test');
        \Illuminate\Support\Facades\URL::forceScheme('https');
        
        $this->user = User::factory()->create();
        $this->client = Client::factory()->create();
        $this->campaign = MarketingCampaign::factory()->create(['client_id' => $this->client->id]);
        
        $this->account = ClientSocialAccount::factory()->create([
            'client_id' => $this->client->id,
            'platform' => SocialPlatform::Instagram->value,
            'access_token' => 'fake_valid_token',
            'provider_account_id' => '123456789',
        ]);
        
        $this->post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $this->campaign->id,
        ]);
    }

    public function test_preflight_blocks_instagram_without_media()
    {
        $preflightService = app(MetaPreflightService::class);
        $result = $preflightService->runPreflight($this->post, $this->account);
        
        $this->assertFalse($result->isPass);
        $this->assertContains("Instagram richiede almeno un file multimediale (Immagine o Video).", $result->errors);
    }

    public function test_preflight_blocks_mixed_carousel()
    {
        // Add one image and one video
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
        $mockMediaUrlService = \Mockery::mock(\App\Domain\Social\Services\SocialMediaPublicUrlService::class);
        $mockMediaUrlService->shouldReceive('getValidatedPublicUrl')->andReturn(['url' => 'https://example.com/mock.jpg', 'diagnostic' => []]);
        $mockMediaUrlService->shouldReceive('getValidatedPublicUrls')->andReturn([
            ['url' => 'https://example.com/mock1.jpg', 'diagnostic' => []],
            ['url' => 'https://example.com/mock2.mp4', 'diagnostic' => []]
        ]);
        $this->app->instance(\App\Domain\Social\Services\SocialMediaPublicUrlService::class, $mockMediaUrlService);

        $preflightService = app(MetaPreflightService::class);
        $result = $preflightService->runPreflight($this->post->fresh(), $this->account);
        
        $this->assertFalse($result->isPass);

        $this->assertContains("Supporto carousel immagini-only per ora. Non sono ammessi formati misti o video multipli in questa versione.", $result->errors);
    }

    public function test_preflight_allows_image_carousel()
    {
        // Add two images
        MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'original_name' => 'image1.jpg',
            'media_type' => 'image',
            'path' => 'test/image1.jpg',
            'sort_order' => 1
        ]);
        
        MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'original_name' => 'image2.png',
            'media_type' => 'image',
            'path' => 'test/image2.png',
            'sort_order' => 2
        ]);
        
        // Mock getValidatedPublicUrl per evitare la HEAD request reale
        $mockMediaUrlService = \Mockery::mock(\App\Domain\Social\Services\SocialMediaPublicUrlService::class);
        $mockMediaUrlService->shouldReceive('getValidatedPublicUrl')->andReturn(['url' => 'https://example.com/mock.jpg', 'diagnostic' => []]);
        $mockMediaUrlService->shouldReceive('getValidatedPublicUrls')->andReturn([
            ['url' => 'https://example.com/mock1.jpg', 'diagnostic' => []],
            ['url' => 'https://example.com/mock2.jpg', 'diagnostic' => []]
        ]);
        
        $this->app->instance(\App\Domain\Social\Services\SocialMediaPublicUrlService::class, $mockMediaUrlService);
        
        $preflightService = app(MetaPreflightService::class);
        $result = $preflightService->runPreflight($this->post->fresh(), $this->account);
        
        $this->assertTrue($result->isPass, "Preflight failed: " . implode(', ', $result->errors));
    }
}
