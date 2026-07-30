<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Services\SocialMediaPublicUrlService;
use App\Models\MarketingCampaignPostMedia;
use App\Support\Network\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialMediaPublicUrlSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_to_private_address_is_rejected_before_second_request(): void
    {
        $media = MarketingCampaignPostMedia::factory()->create([
            'source' => 'external',
            'disk' => null,
            'path' => null,
            'url' => 'https://media.example/photo.jpg',
            'mime_type' => 'image/jpeg',
            'media_type' => 'image',
        ]);
        Http::fake([
            'https://media.example/photo.jpg' => Http::response('', 302, [
                'Location' => 'https://127.0.0.1/private.jpg',
            ]),
        ]);
        $this->mock(HostResolver::class)
            ->shouldReceive('resolveAndValidatePublicHost')
            ->twice()
            ->with('media.example')
            ->andReturn('203.0.113.10');

        try {
            app(SocialMediaPublicUrlService::class)
                ->getValidatedPublicUrl($media);
            $this->fail('Expected the private redirect to be rejected.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString(
                'host privato locale',
                $exception->getMessage()
            );
        }

        Http::assertSentCount(1);
    }

    public function test_single_public_redirect_is_revalidated_and_accepted(): void
    {
        $media = MarketingCampaignPostMedia::factory()->create([
            'source' => 'external',
            'disk' => null,
            'path' => null,
            'url' => 'https://media.example/photo.jpg',
            'mime_type' => 'image/jpeg',
            'media_type' => 'image',
        ]);
        Http::fake([
            'https://media.example/photo.jpg' => Http::response('', 302, [
                'Location' => 'https://cdn.example/photo.jpg',
            ]),
            'https://cdn.example/photo.jpg' => Http::response('', 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => '123',
            ]),
        ]);
        $resolver = $this->mock(HostResolver::class);
        $resolver->shouldReceive('resolveAndValidatePublicHost')
            ->twice()
            ->with('media.example')
            ->andReturn('203.0.113.10');
        $resolver->shouldReceive('resolveAndValidatePublicHost')
            ->once()
            ->with('cdn.example')
            ->andReturn('203.0.113.11');

        $result = app(SocialMediaPublicUrlService::class)
            ->getValidatedPublicUrl($media);

        $this->assertSame(1, $result['diagnostic']['redirect_count']);
        $this->assertSame(200, $result['diagnostic']['status']);
        Http::assertSentCount(2);
    }
}
