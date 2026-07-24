<?php

namespace Tests\Feature\Social;

use App\Livewire\Public\MarketingCampaignPostReview;
use App\Models\ClientReviewToken;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingCampaignPostReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_is_blocked_if_media_delivery_fails()
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
            'nextcloud_share_url' => null, // This will throw MarketingCampaignPostMediaDeliveryException
        ]);

        $version->mediaItems()->attach($media->id, ['sort_order' => 0]);

        $token = ClientReviewToken::create([
            'token' => 'VALID_TOKEN_123',
            'reviewable_type' => MarketingCampaignPost::class,
            'reviewable_id' => $post->id,
            'marketing_campaign_post_version_id' => $version->id,
            'expires_at' => now()->addDays(7),
        ]);

        $component = Livewire::test(MarketingCampaignPostReview::class, ['token' => 'VALID_TOKEN_123']);

        $component->assertStatus(409);
    }
}
