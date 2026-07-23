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
}
