<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Builders\MarketingCampaignPostMediaPayloadBuilder;
use App\Domain\Social\Exceptions\MarketingCampaignPostMediaDeliveryException;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingCampaignPostMediaPayloadBuilderTest extends TestCase
{
    use RefreshDatabase;

    private MarketingCampaignPostMediaPayloadBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = app(MarketingCampaignPostMediaPayloadBuilder::class);
    }

    public function test_throws_delivery_exception_for_nextcloud_without_share_url()
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $post->update(['current_version_id' => $version->id]);

        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'nextcloud',
            'nextcloud_share_url' => null,
        ]);

        $version->mediaItems()->attach($media->id, ['sort_order' => 0]);

        $this->expectException(MarketingCampaignPostMediaDeliveryException::class);
        $this->builder->build($post);
    }

    public function test_throws_delivery_exception_for_local_media_without_path()
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $post->update(['current_version_id' => $version->id]);

        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'path' => null,
        ]);

        $version->mediaItems()->attach($media->id, ['sort_order' => 0]);

        $this->expectException(MarketingCampaignPostMediaDeliveryException::class);
        $this->builder->build($post);
    }

    public function test_builder_with_pivot_subset_uses_only_pivot_media()
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $post->update(['current_version_id' => $version->id]);

        $mediaA = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'nextcloud',
            'nextcloud_share_url' => 'https://nextcloud.test/s/AAA',
        ]);
        
        $mediaB = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'nextcloud',
            'nextcloud_share_url' => 'https://nextcloud.test/s/BBB',
        ]);

        // Pivot solo B
        $version->mediaItems()->attach($mediaB->id, ['sort_order' => 0]);

        $payload = $this->builder->build($post);

        $this->assertEquals(1, $payload['media_count']);
        $this->assertCount(1, $payload['media_items']);
        $this->assertEquals('https://nextcloud.test/s/BBB/download', $payload['primary_media_url']);
        $this->assertEquals($mediaB->id, $payload['media_items'][0]['id']);
    }

    public function test_builder_with_pivot_reordering_respects_pivot_sort_order()
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'image_urls' => null,
            'image_url' => null,
            'image_path' => null,
        ]);
        $post->update(['current_version_id' => $version->id]);

        $mediaA = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'nextcloud',
            'nextcloud_share_url' => 'https://nextcloud.test/s/AAA',
            'sort_order' => 0, // Ordine globale A prima di B
        ]);
        
        $mediaB = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'nextcloud',
            'nextcloud_share_url' => 'https://nextcloud.test/s/BBB',
            'sort_order' => 1,
        ]);

        // Pivot B prima di A
        $version->mediaItems()->attach($mediaB->id, ['sort_order' => 0]);
        $version->mediaItems()->attach($mediaA->id, ['sort_order' => 1]);

        $payload = $this->builder->build($post);

        $this->assertEquals(2, $payload['media_count']);
        $this->assertCount(2, $payload['media_items']);
        $this->assertEquals('https://nextcloud.test/s/BBB/download', $payload['primary_media_url']);
        $this->assertEquals($mediaB->id, $payload['media_items'][0]['id']);
        $this->assertEquals($mediaA->id, $payload['media_items'][1]['id']);
    }
}
