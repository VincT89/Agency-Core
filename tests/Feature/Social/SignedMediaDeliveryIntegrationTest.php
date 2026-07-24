<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\AddMarketingCampaignPostVersionFromN8nAction;
use App\Domain\Social\DTOs\AddMarketingCampaignPostVersionData;
use App\Domain\Social\Services\SocialMediaPublicUrlService;
use App\Enums\Social\MarketingCampaignPostRegenerationType;
use App\Models\MarketingCampaignPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignedMediaDeliveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        
        // Imposta un URL fittizio HTTPS e non locale per bypassare ensureSecureHost
        Config::set('app.url', 'https://test-agency.com');
        \Illuminate\Support\Facades\URL::forceRootUrl('https://test-agency.com');
        \Illuminate\Support\Facades\URL::forceScheme('https');
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\URL::forceScheme(null);
        \Illuminate\Support\Facades\URL::forceRootUrl(null);

        parent::tearDown();
    }

    public function test_full_chain_from_n8n_action_to_delivery()
    {
        // 1. Setup - Mock external image download for n8n staging
        Http::fake([
            'https://example.com/generated.jpg' => Http::response(file_get_contents(base_path('tests/Fixtures/valid.jpg')), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $post = MarketingCampaignPost::factory()->create();

        // 2. Eseguiamo l'azione n8n
        $data = new AddMarketingCampaignPostVersionData(
            postId: $post->id,
            regenerationType: MarketingCampaignPostRegenerationType::Full,
            title: 'Test Title',
            caption: 'Test Caption',
            hashtags: [],
            imageUrls: ['https://example.com/generated.jpg'],
            externalGenerationId: 'ext-123',
            requestId: 'req-123',
            promptUsed: 'A prompt',
            rawPayload: []
        );

        $action = app(AddMarketingCampaignPostVersionFromN8nAction::class);
        $result = $action->execute($data);

        // 3. Recuperiamo il media creato
        $this->assertCount(1, $result->version->mediaItems);
        $media = $result->version->mediaItems->first();

        $this->assertEquals('n8n', $media->source);
        $this->assertEquals('public', $media->disk);

        // Mock preflight validation from URL Service
        Http::fake([
            'https://test-agency.com/social/media/*' => Http::response('', 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => 18,
            ]),
        ]);

        // 4. Passiamo a SocialMediaPublicUrlService
        $urlService = app(SocialMediaPublicUrlService::class);
        $urlData = $urlService->getValidatedPublicUrl($media);

        // 5. Verifica che venga restituito un URL firmato HTTPS valido
        $url = $urlData['url'];
        $this->assertStringContainsString('https://test-agency.com/social/media/', $url);
        $this->assertStringContainsString('signature=', $url);

        // 6. Eseguiamo GET (delivery) sul controller
        // Convertiamo in url relativo per fare la chiamata di test al controller locale
        $parsedUrl = parse_url($url);
        $relativeUrl = $parsedUrl['path'] . '?' . $parsedUrl['query'];

        $response = $this->get($relativeUrl);
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertEquals(file_get_contents(base_path('tests/Fixtures/valid.jpg')), $response->streamedContent());
    }

    public function test_legacy_route_ambiguity_and_traversal()
    {
        Storage::fake('public');
        Storage::disk('public')->put('marketing/campaign-posts/test.jpg', 'fake-image');

        // Ambiguity test: both these paths should return 200 because of the str_starts_with fallback
        $this->get('/media/marketing-campaign-posts/test.jpg')->assertStatus(200);
        $this->get('/media/marketing-campaign-posts/marketing/campaign-posts/test.jpg')->assertStatus(200);

        // Traversal test: these should return 404
        $this->get('/media/marketing-campaign-posts/../test.jpg')->assertStatus(404);
        
        // Invalid extension
        Storage::disk('public')->put('marketing/campaign-posts/test.txt', 'text');
        $this->get('/media/marketing-campaign-posts/test.txt')->assertStatus(404);
    }

    public function test_signed_route_signature_altered_missing_files_and_range()
    {
        Storage::fake('public');
        $media = \App\Models\MarketingCampaignPostMedia::factory()->create([
            'source' => 'local',
            'disk' => 'public',
            'path' => 'marketing/campaign-posts/video.mp4',
            'mime_type' => 'video/mp4'
        ]);
        Storage::disk('public')->put('marketing/campaign-posts/video.mp4', str_repeat('a', 1000));

        $url = \Illuminate\Support\Facades\URL::signedRoute('social.media.delivery', ['media' => $media->id]);
        
        // Convert to relative URL for testing
        $parsedUrl = parse_url($url);
        $relativeUrl = $parsedUrl['path'] . '?' . $parsedUrl['query'];

        // Normal request
        $this->get($relativeUrl)->assertStatus(200);

        // Altered signature -> 403
        $this->get($relativeUrl . 'x')->assertStatus(403);

        // Range/206 for video
        $this->get($relativeUrl, ['Range' => 'bytes=0-100'])->assertStatus(206);

        // Missing disk/path -> 404
        $media->update(['disk' => null, 'path' => null]);
        $this->get($relativeUrl)->assertStatus(404);
    }
}
