<?php

namespace Tests\Feature\Social;

use App\Domain\Social\DTOs\NextcloudFileInfo;
use App\Models\MarketingCampaignPostPublication;
use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SocialPublicationMediaDeliveryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_missing_signature(): void
    {
        $publication = MarketingCampaignPostPublication::factory()->create();

        $this->get("/publication/{$publication->id}/media/0/deliver")
            ->assertForbidden();
    }

    public function test_delivers_complete_local_media(): void
    {
        [$publication, $url] = $this->localPublication('fake-image-content');

        $response = $this->get($url);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('Content-Length', '18');
        $this->assertSame('fake-image-content', $response->streamedContent());
    }

    public function test_rejects_same_size_local_content_that_changed_after_snapshot(): void
    {
        [, $url] = $this->localPublication('original');
        Storage::disk('local')->put('test.jpg', 'mutated!');

        $this->get($url)
            ->assertStatus(409)
            ->assertSee('checksum no longer matches');
    }

    public function test_rejects_nextcloud_content_that_changed_after_snapshot(): void
    {
        $hash = str_repeat('a', 64);
        $publication = MarketingCampaignPostPublication::factory()->create([
            'snapshot_hash' => $hash,
            'payload_snapshot' => [
                'media' => [[
                    'storage_source' => 'nextcloud',
                    'nextcloud_path' => '/social/photo.jpg',
                    'nextcloud_file_id' => 'file-123',
                    'nextcloud_etag' => 'etag-frozen',
                    'mime_type' => 'image/jpeg',
                    'size_bytes' => 128,
                ]],
            ],
        ]);
        $nextcloud = $this->mock(NextcloudService::class);
        $nextcloud->shouldReceive('getFileInfo')
            ->once()
            ->with('/social/photo.jpg')
            ->andReturn(new NextcloudFileInfo(
                path: '/social/photo.jpg',
                fileId: 'file-123',
                etag: 'etag-changed',
                mimeType: 'image/jpeg',
                sizeBytes: 128
            ));
        $nextcloud->shouldNotReceive('streamFileResponse');

        $this->get($this->signedUrl($publication, 0))
            ->assertStatus(409)
            ->assertSee('no longer matches');
    }

    public function test_head_returns_headers_without_a_body(): void
    {
        [, $url] = $this->localPublication('0123456789');

        $response = $this->head($url);

        $response->assertOk();
        $response->assertHeader('Content-Length', '10');
        $response->assertHeader('Accept-Ranges', 'bytes');
        $this->assertSame('', $response->getContent());
    }

    public function test_delivers_closed_range(): void
    {
        [, $url] = $this->localPublication('0123456789');

        $response = $this->withHeader('Range', 'bytes=2-5')->get($url);

        $response->assertStatus(206);
        $response->assertHeader('Content-Range', 'bytes 2-5/10');
        $response->assertHeader('Content-Length', '4');
        $this->assertSame('2345', $response->streamedContent());
    }

    public function test_delivers_open_range(): void
    {
        [, $url] = $this->localPublication('0123456789');

        $response = $this->withHeader('Range', 'bytes=5-')->get($url);

        $response->assertStatus(206);
        $response->assertHeader('Content-Range', 'bytes 5-9/10');
        $this->assertSame('56789', $response->streamedContent());
    }

    public function test_delivers_suffix_range(): void
    {
        [, $url] = $this->localPublication('0123456789');

        $response = $this->withHeader('Range', 'bytes=-3')->get($url);

        $response->assertStatus(206);
        $response->assertHeader('Content-Range', 'bytes 7-9/10');
        $this->assertSame('789', $response->streamedContent());
    }

    public function test_returns_404_for_missing_media_index(): void
    {
        [$publication] = $this->localPublication('0123456789');
        $url = $this->signedUrl($publication, 1);

        $this->get($url)->assertNotFound();
    }

    public function test_returns_416_for_unsatisfiable_range(): void
    {
        [, $url] = $this->localPublication('0123456789');

        $response = $this->withHeader('Range', 'bytes=10-12')->get($url);

        $response->assertStatus(416);
        $response->assertHeader('Content-Range', 'bytes */10');
    }

    public function test_returns_429_when_delivery_rate_limit_is_exhausted(): void
    {
        [$publication, $url] = $this->localPublication('0123456789');
        $limiterKey = md5(
            'social-media-delivery'.
            "pub_{$publication->id}_index_0|127.0.0.1"
        );

        RateLimiter::clear($limiterKey);
        RateLimiter::increment($limiterKey, 60, 300);

        try {
            $this->get($url)->assertStatus(429);
        } finally {
            RateLimiter::clear($limiterKey);
        }
    }

    public function test_aborts_if_hash_mismatch(): void
    {
        $publication = MarketingCampaignPostPublication::factory()->create([
            'snapshot_hash' => str_repeat('a', 64),
        ]);

        $url = URL::signedRoute(
            'public.social.publication-media.deliver',
            [
                'publication' => $publication->id,
                'mediaIndex' => 0,
                'hash' => str_repeat('b', 64),
            ]
        );

        $this->get($url)
            ->assertForbidden()
            ->assertSee('Snapshot hash mismatch');
    }

    private function localPublication(string $contents): array
    {
        Storage::fake('local');
        Storage::disk('local')->put('test.jpg', $contents);
        $hash = str_repeat('a', 64);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'snapshot_hash' => $hash,
            'payload_snapshot' => [
                'media' => [[
                    'storage_source' => 'local',
                    'disk' => 'local',
                    'path' => 'test.jpg',
                    'mime_type' => 'image/jpeg',
                    'size_bytes' => strlen($contents),
                    'sha256' => hash('sha256', $contents),
                ]],
            ],
        ]);

        return [$publication, $this->signedUrl($publication, 0)];
    }

    private function signedUrl(
        MarketingCampaignPostPublication $publication,
        int $mediaIndex
    ): string {
        return URL::signedRoute(
            'public.social.publication-media.deliver',
            [
                'publication' => $publication->id,
                'mediaIndex' => $mediaIndex,
                'hash' => $publication->snapshot_hash,
            ]
        );
    }
}
