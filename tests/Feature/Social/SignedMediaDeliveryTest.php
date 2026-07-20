<?php

namespace Tests\Feature\Social;

use App\Models\MarketingCampaignPostMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SignedMediaDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_it_delivers_file_for_valid_signed_url()
    {
        Storage::disk('public')->put('n8n_test_media.jpg', 'fake_image_content');

        $media = MarketingCampaignPostMedia::factory()->create([
            'source' => 'n8n',
            'disk' => 'public',
            'path' => 'n8n_test_media.jpg',
            'mime_type' => 'image/jpeg',
            'media_type' => 'image',
        ]);

        $url = URL::temporarySignedRoute(
            'social.media.delivery',
            now()->addMinutes(10),
            ['media' => $media->id]
        );

        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertEquals('fake_image_content', $response->streamedContent());
    }

    public function test_it_rejects_unsigned_or_tampered_url()
    {
        Storage::disk('public')->put('n8n_test_media.jpg', 'fake_image_content');

        $media = MarketingCampaignPostMedia::factory()->create([
            'source' => 'n8n',
            'disk' => 'public',
            'path' => 'n8n_test_media.jpg',
        ]);

        $url = URL::temporarySignedRoute(
            'social.media.delivery',
            now()->addMinutes(10),
            ['media' => $media->id]
        );

        // Tamper with URL
        $tamperedUrl = str_replace('signature=', 'signature=invalid', $url);
        
        $response = $this->get($tamperedUrl);
        
        $response->assertStatus(403);
    }
}
