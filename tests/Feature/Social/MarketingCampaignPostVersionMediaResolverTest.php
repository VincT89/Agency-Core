<?php

namespace Tests\Feature\Social;

use App\Domain\Social\DTOs\MarketingCampaignPostMediaResolution;
use App\Domain\Social\Enums\MarketingCampaignPostMediaResolutionSource;
use App\Domain\Social\Exceptions\MarketingCampaignPostMediaResolutionException;
use App\Domain\Social\Services\MarketingCampaignPostVersionMediaResolver;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingCampaignPostVersionMediaResolverTest extends TestCase
{
    use RefreshDatabase;

    private MarketingCampaignPostVersionMediaResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new MarketingCampaignPostVersionMediaResolver();
    }

    public function test_authoritative_pivot_only_returns_pivot_media()
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $post->update(['current_version_id' => $version->id]);

        $media1 = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id]);
        $media2 = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id]);
        $mediaExtra = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id]);

        // Attach only 2 media to pivot
        $version->mediaItems()->attach([
            $media2->id => ['sort_order' => 0],
            $media1->id => ['sort_order' => 1],
        ]);

        $resolution = $this->resolver->resolveForVersion($version);

        $this->assertEquals(MarketingCampaignPostMediaResolutionSource::VERSION_PIVOT, $resolution->source);
        $this->assertFalse($resolution->usesLegacyFallback);
        $this->assertCount(2, $resolution->mediaItems);
        
        // Check order
        $this->assertEquals($media2->id, $resolution->mediaItems[0]->id);
        $this->assertEquals($media1->id, $resolution->mediaItems[1]->id);
    }

    public function test_pivot_with_foreign_media_throws_exception()
    {
        $post1 = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post1->id,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        
        $post2 = MarketingCampaignPost::factory()->create();
        $mediaForeign = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post2->id]);

        $version->mediaItems()->attach($mediaForeign->id, ['sort_order' => 0]);

        $this->expectException(MarketingCampaignPostMediaResolutionException::class);
        $this->expectExceptionMessage("belongs to post ID {$post2->id}, but expected post ID {$post1->id}");

        $this->resolver->resolveForVersion($version);
    }

    public function test_local_legacy_version_resolves_media()
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_path' => 'some/local/path.jpg',
            'image_urls' => null,
            'image_url' => null,
        ]);
        
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'path' => 'some/local/path.jpg',
            'disk' => 'public',
        ]);

        $resolution = $this->resolver->resolveForVersion($version);

        $this->assertEquals(MarketingCampaignPostMediaResolutionSource::VERSION_LEGACY, $resolution->source);
        $this->assertTrue($resolution->usesLegacyFallback);
        $this->assertCount(1, $resolution->mediaItems);
        $this->assertEquals($media->id, $resolution->mediaItems[0]->id);
    }

    public function test_multiple_legacy_urls_resolves_multiple_media_in_order()
    {
        $post = MarketingCampaignPost::factory()->create();
        
        $mediaA = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'path' => 'path/a.jpg',
            'disk' => 'public',
        ]);
        
        $mediaB = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'path' => 'path/b.jpg',
            'disk' => 'public',
        ]);

        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_urls' => ['path/b.jpg', 'path/a.jpg'],
            'image_url' => null,
            'image_path' => null,
        ]);

        $resolution = $this->resolver->resolveForVersion($version);

        $this->assertEquals(MarketingCampaignPostMediaResolutionSource::VERSION_LEGACY, $resolution->source);
        $this->assertCount(2, $resolution->mediaItems);
        $this->assertEquals($mediaB->id, $resolution->mediaItems[0]->id);
        $this->assertEquals($mediaA->id, $resolution->mediaItems[1]->id);
    }

    public function test_nextcloud_legacy_url_with_and_without_download_resolves_to_same_media()
    {
        $post = MarketingCampaignPost::factory()->create();
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'nextcloud_share_url' => 'https://nc.example.com/s/123',
        ]);

        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_url' => 'https://nc.example.com/s/123/download', // with /download
            'image_urls' => null,
            'image_path' => null,
        ]);

        $resolution = $this->resolver->resolveForVersion($version);

        $this->assertCount(1, $resolution->mediaItems);
        $this->assertEquals($media->id, $resolution->mediaItems[0]->id);
    }

    public function test_historical_version_without_pivot_ignores_current_post_media()
    {
        $post = MarketingCampaignPost::factory()->create();
        $historicalVersion = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'version_number' => 1,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $currentVersion = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'version_number' => 2,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $post->update(['current_version_id' => $currentVersion->id]);

        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'path' => 'current/media.jpg',
        ]);

        // If historicalVersion has NO references, it should throw, not fallback to post
        $this->expectException(MarketingCampaignPostMediaResolutionException::class);
        $this->resolver->resolveForVersion($historicalVersion);
    }

    public function test_current_version_without_pivot_and_references_uses_current_post_legacy()
    {
        $post = MarketingCampaignPost::factory()->create([
            'media_path' => 'fallback/path.jpg',
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $post->update(['current_version_id' => $version->id]);

        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'path' => 'fallback/path.jpg',
            'disk' => 'public',
        ]);

        
        $resolution = $this->resolver->resolveForVersion($version);

        $this->assertEquals(MarketingCampaignPostMediaResolutionSource::CURRENT_POST_LEGACY, $resolution->source);
        $this->assertCount(1, $resolution->mediaItems);
        $this->assertEquals($media->id, $resolution->mediaItems[0]->id);
    }

    public function test_current_version_without_pivot_and_no_legacy_fields_throws_exception()
    {
        $post = MarketingCampaignPost::factory()->create([
            'media_path' => null,
            'nextcloud_share_url' => null,
        ]);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $post->update(['current_version_id' => $version->id]);

        // Even if there is one media in the post, it should NOT auto-associate if there's no actual string reference
        MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'path' => 'fallback/path.jpg',
            'disk' => 'public',
        ]);

        $this->expectException(MarketingCampaignPostMediaResolutionException::class);
        $this->resolver->resolveForVersion($version);
    }

    public function test_missing_legacy_reference_throws_exception()
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_path' => 'nonexistent/path.jpg',
            'image_urls' => null,
            'image_url' => null,
        ]);

        $this->expectException(MarketingCampaignPostMediaResolutionException::class);
        $this->expectExceptionMessage("could not be resolved");
        $this->resolver->resolveForVersion($version);
    }

    public function test_ambiguous_legacy_reference_throws_exception()
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_path' => 'same/path.jpg',
            'image_urls' => null,
            'image_url' => null,
        ]);

        // Create two media items with exact same path
        MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'path' => 'same/path.jpg',
            'disk' => 'public',
        ]);
        MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'path' => 'same/path.jpg',
            'disk' => 'public',
        ]);

        $this->expectException(MarketingCampaignPostMediaResolutionException::class);
        $this->expectExceptionMessage("ambiguous and matches multiple");
        $this->resolver->resolveForVersion($version);
    }

    public function test_inconsistent_current_version_id_throws_exception()
    {
        $post = MarketingCampaignPost::factory()->create();
        $otherPost = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $otherPost->id,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        
        $post->update(['current_version_id' => $version->id]);

        $this->expectException(MarketingCampaignPostMediaResolutionException::class);
        $this->expectExceptionMessage("missing or invalid");
        $this->resolver->resolveForPost($post);
    }

    public function test_draft_post_resolves_to_ordered_media_items()
    {
        $post = MarketingCampaignPost::factory()->create(['current_version_id' => null]);
        
        $media1 = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'sort_order' => 1,
        ]);
        $media0 = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'sort_order' => 0,
        ]);

        $resolution = $this->resolver->resolveForPost($post);

        $this->assertEquals(MarketingCampaignPostMediaResolutionSource::DRAFT_POST, $resolution->source);
        $this->assertFalse($resolution->usesLegacyFallback);
        $this->assertCount(2, $resolution->mediaItems);
        // Should be ordered by sort_order ascending
        $this->assertEquals($media0->id, $resolution->mediaItems[0]->id);
        $this->assertEquals($media1->id, $resolution->mediaItems[1]->id);
    }

    public function test_empty_draft_resolves_to_empty_collection()
    {
        $post = MarketingCampaignPost::factory()->create(['current_version_id' => null]);
        
        $resolution = $this->resolver->resolveForPost($post);

        $this->assertEquals(MarketingCampaignPostMediaResolutionSource::DRAFT_POST, $resolution->source);
        $this->assertCount(0, $resolution->mediaItems);
    }
}
