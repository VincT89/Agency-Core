<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\SendMarketingCampaignPostToClientAction;
use App\Domain\Social\Exceptions\MarketingCampaignPostMediaDeliveryException;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostVersion;
use App\Models\MarketingCampaignPostMedia;
use App\Models\ClientReviewToken;
use App\Enums\Social\MarketingCampaignPostStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendMarketingCampaignPostToClientActionTest extends TestCase
{
    use RefreshDatabase;

    private SendMarketingCampaignPostToClientAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(SendMarketingCampaignPostToClientAction::class);
        Mail::fake();
    }

    public function test_fails_and_does_not_modify_state_when_resolution_fails()
    {
        $post = MarketingCampaignPost::factory()->create([
            'status' => MarketingCampaignPostStatus::Generated
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
            'source' => 'nextcloud',
            'nextcloud_share_url' => null,
        ]);

        $version->mediaItems()->attach($media->id, ['sort_order' => 0]);

        // Pre-existing valid token
        $token = ClientReviewToken::create([
            'token' => 'PRE_EXISTING_TOKEN',
            'reviewable_type' => MarketingCampaignPost::class,
            'reviewable_id' => $post->id,
            'marketing_campaign_post_version_id' => $version->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->expectException(MarketingCampaignPostMediaDeliveryException::class);

        try {
            $this->action->execute($post);
        } finally {
            // Assert state is unchanged
            $post->refresh();
            $this->assertEquals(MarketingCampaignPostStatus::Generated, $post->status);

            // Vecchio token ancora valido
            $token->refresh();
            $this->assertFalse($token->isExpired());

            // Nessuna mail inviata
            Mail::assertNothingQueued();
            
            // Nessun nuovo token
            $this->assertEquals(1, ClientReviewToken::where('reviewable_id', $post->id)->count());
        }
    }

    public function test_successful_send_invalidates_old_tokens_and_uses_locked_version()
    {
        $post = MarketingCampaignPost::factory()->create([
            'status' => MarketingCampaignPostStatus::Generated
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
            'source' => 'local',
            'path' => 'dummy/path.jpg',
            'disk' => 'public',
        ]);
        $version->mediaItems()->attach($media->id, ['sort_order' => 0]);

        $oldToken = ClientReviewToken::create([
            'token' => 'PRE_EXISTING_TOKEN',
            'reviewable_type' => MarketingCampaignPost::class,
            'reviewable_id' => $post->id,
            'marketing_campaign_post_version_id' => $version->id,
            'expires_at' => now()->addDays(7),
        ]);

        $token = $this->action->execute($post);

        $this->assertEquals($version->id, $token->marketing_campaign_post_version_id);
        
        $oldToken->refresh();
        $this->assertTrue($oldToken->isExpired(), 'Old token should be invalidated');
        $this->assertFalse($token->isExpired(), 'New token should not be expired');
        
        $post->refresh();
        $this->assertEquals(MarketingCampaignPostStatus::SentToClient, $post->status);
    }
}
