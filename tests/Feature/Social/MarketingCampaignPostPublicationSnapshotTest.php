<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Services\MarketingCampaignPostPublicationSnapshotBuilder;
use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingCampaignPostPublicationSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_snapshot()
    {
        $post = MarketingCampaignPost::factory()->create(['content_type' => 'post']);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'title' => 'Test title',
            'caption' => 'Test caption',
            'hashtags' => ['#test'],
        ]);

        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'disk' => 'public',
            'path' => 'test.png',
            'mime_type' => 'image/png',
            'media_type' => 'image',
        ]);

        $version->mediaItems()->attach($media->id, ['sort_order' => 1]);

        $account = ClientSocialAccount::factory()->create();

        $builder = new MarketingCampaignPostPublicationSnapshotBuilder;

        $target = [
            'social_account_id' => $account->id,
            'external_id' => 'ext123',
            'page_id' => null,
            'profile_id' => null,
        ];

        $mediaMetadataCache = [
            $media->id => [
                'size_bytes' => 1024,
                'sha256' => hash('sha256', 'snapshot-test'),
            ],
        ];

        $snapshot = $builder->build(
            $version,
            SocialPlatform::Facebook,
            $target,
            [],
            'publish',
            $mediaMetadataCache,
            []
        );

        $this->assertEquals($post->id, $snapshot->post_id);
        $this->assertEquals('meta', $snapshot->provider);
        $this->assertEquals('Test title', $snapshot->title);
        $this->assertEquals('Test caption', $snapshot->caption);
        $this->assertEquals(['#test'], $snapshot->hashtags);
        $this->assertCount(1, $snapshot->media);
        $this->assertEquals('local', $snapshot->media[0]['storage_source']);
        $this->assertEquals('public', $snapshot->media[0]['disk']);
        $this->assertEquals(hash('sha256', 'snapshot-test'), $snapshot->media[0]['sha256']);
    }

    public function test_it_normalizes_empty_manual_copy_fields(): void
    {
        $post = MarketingCampaignPost::factory()->create(['content_type' => 'post']);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'title' => null,
            'caption' => null,
            'hashtags' => null,
        ]);
        $account = ClientSocialAccount::factory()->create();

        $snapshot = (new MarketingCampaignPostPublicationSnapshotBuilder)->build(
            $version,
            SocialPlatform::Facebook,
            [
                'social_account_id' => $account->id,
                'external_id' => 'facebook-page-123',
                'page_id' => 'facebook-page-123',
                'profile_id' => null,
            ]
        );

        $this->assertSame('', $snapshot->title);
        $this->assertSame('', $snapshot->caption);
        $this->assertSame([], $snapshot->hashtags);
    }
}
