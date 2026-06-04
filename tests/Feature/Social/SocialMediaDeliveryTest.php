<?php

namespace Tests\Feature\Social;

use Tests\TestCase;
use App\Models\MarketingCampaignPostMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;

class SocialMediaDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a fake disk and file
        Storage::fake('public');
        Storage::disk('public')->put('safe/file.mp4', 'dummy content');
        Storage::disk('public')->put('safe/image.jpg', 'dummy image content');
    }

    public function test_blocks_invalid_signature()
    {
        $media = MarketingCampaignPostMedia::factory()->create([
            'disk' => 'public',
            'path' => 'safe/file.mp4',
            'mime_type' => 'video/mp4',
        ]);

        $url = route('social.media.delivery', $media);
        
        $response = $this->get($url);
        $response->assertStatus(403);
        $response->assertSee('Invalid signature');
    }

    public function test_blocks_expired_signature()
    {
        $media = MarketingCampaignPostMedia::factory()->create([
            'disk' => 'public',
            'path' => 'safe/file.mp4',
            'mime_type' => 'video/mp4',
        ]);

        // Create an expired URL
        $url = URL::temporarySignedRoute('social.media.delivery', now()->subMinutes(10), ['media' => $media->id]);
        
        $response = $this->get($url);
        
        // The framework middleware should block this before the controller even loads
        $response->assertStatus(403);
        $response->assertSee('Invalid signature');
    }

    public function test_allows_valid_signature_and_serves_file()
    {
        $media = MarketingCampaignPostMedia::factory()->create([
            'disk' => 'public',
            'path' => 'safe/image.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $url = URL::temporarySignedRoute('social.media.delivery', now()->addMinutes(10), ['media' => $media->id]);
        
        $response = $this->get($url);
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_blocks_path_traversal()
    {
        // Traversal is blocked in controller via URL checks or by default because we query DB path,
        // but if someone manipulates DB path to contain '../' it should be blocked.
        $media = MarketingCampaignPostMedia::factory()->create([
            'disk' => 'public',
            'path' => '../../etc/passwd',
            'mime_type' => 'text/plain',
        ]);

        $url = URL::temporarySignedRoute('social.media.delivery', now()->addMinutes(10), ['media' => $media->id]);
        
        $response = $this->get($url);
        $response->assertStatus(403); // Assuming controller aborts with 403 on traversal
    }

    public function test_range_header_support()
    {
        $media = MarketingCampaignPostMedia::factory()->create([
            'disk' => 'public',
            'path' => 'safe/file.mp4',
            'mime_type' => 'video/mp4',
        ]);

        $url = URL::temporarySignedRoute('social.media.delivery', now()->addMinutes(10), ['media' => $media->id]);
        
        // Use withHeaders to simulate a range request
        $response = $this->get($url, ['Range' => 'bytes=0-4']);
        
        // 206 Partial Content
        $response->assertStatus(206);
    }
}
