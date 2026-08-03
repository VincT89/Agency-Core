<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\CreateMarketingCampaignPostPublicationAction;
use App\Enums\Social\SocialPlatform;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateMarketingCampaignPostPublicationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_publication_successfully()
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
        ]);
        $post->update(['current_version_id' => $version->id]);

        $account = ClientSocialAccount::factory()->create([
            'client_id' => $post->campaign->client_id,
            'platform' => SocialPlatform::Facebook,
            'provider_account_id' => 'fake_page_id',
        ]);

        $action = app(CreateMarketingCampaignPostPublicationAction::class);

        $publication = $action->execute(
            $post,
            $version,
            SocialPlatform::Facebook,
            $account,
            [],
            'publish',
            []
        );

        $this->assertNotNull($publication);
        $this->assertEquals($post->id, $publication->marketing_campaign_post_id);
        $this->assertEquals('pending', $publication->status->value);
        $this->assertEquals(1, $publication->attempt_count);
        $this->assertNotNull($publication->stale_deadline_at);
        $this->assertTrue($publication->stale_deadline_at->isFuture());
    }

    public function test_it_creates_publication_for_manual_image_without_copy(): void
    {
        Storage::fake('social_media');
        Storage::disk('social_media')->put(
            'marketing/campaign-posts/manual-only-image.jpg',
            file_get_contents(base_path('tests/Fixtures/test.jpg'))
        );

        $post = MarketingCampaignPost::factory()->create(['content_type' => 'post']);
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'title' => null,
            'caption' => null,
            'hashtags' => null,
        ]);
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'source' => 'local',
            'disk' => 'social_media',
            'path' => 'marketing/campaign-posts/manual-only-image.jpg',
            'media_type' => 'image',
            'original_name' => 'manual-only-image.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        $version->mediaItems()->attach($media->id, ['sort_order' => 0]);
        $post->update(['current_version_id' => $version->id]);

        $account = ClientSocialAccount::factory()->create([
            'client_id' => $post->campaign->client_id,
            'platform' => SocialPlatform::Facebook,
            'provider_account_id' => 'fake_page_id',
        ]);

        $publication = app(CreateMarketingCampaignPostPublicationAction::class)->execute(
            $post,
            $version,
            SocialPlatform::Facebook,
            $account
        );

        $this->assertSame('', $publication->payload_snapshot['title']);
        $this->assertSame('', $publication->payload_snapshot['caption']);
        $this->assertSame([], $publication->payload_snapshot['hashtags']);
        $this->assertCount(1, $publication->payload_snapshot['media']);
        $this->assertSame($media->id, $publication->payload_snapshot['media'][0]['media_id']);
    }

    public function test_it_fails_if_version_does_not_belong_to_post()
    {
        $post = MarketingCampaignPost::factory()->create();
        $otherPost = MarketingCampaignPost::factory()->create();

        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $otherPost->id,
        ]);

        $account = ClientSocialAccount::factory()->create([
            'client_id' => $post->campaign->client_id,
            'platform' => SocialPlatform::Facebook,
        ]);

        $action = app(CreateMarketingCampaignPostPublicationAction::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('La versione non appartiene a questo post.');

        $action->execute($post, $version, SocialPlatform::Facebook, $account);
    }

    public function test_it_fails_if_lock_cannot_be_acquired()
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
        ]);
        $post->update(['current_version_id' => $version->id]);

        $account = ClientSocialAccount::factory()->create([
            'client_id' => $post->campaign->client_id,
            'platform' => SocialPlatform::Facebook,
        ]);

        $lockKey = "create_pub_{$version->id}_{$account->id}_facebook";

        // Simulate lock acquired by another process
        Cache::lock($lockKey, 30)->get();

        $action = app(CreateMarketingCampaignPostPublicationAction::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Creazione publication già in corso per questa versione e account.');

        $action->execute($post, $version, SocialPlatform::Facebook, $account);
    }
}
