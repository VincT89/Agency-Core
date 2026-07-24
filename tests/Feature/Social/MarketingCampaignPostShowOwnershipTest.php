<?php

namespace Tests\Feature\Social;

use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\User;
use App\Models\MarketingCampaignPostPublication;
use App\Enums\Social\SocialPlatform;
use App\Enums\Social\PublicationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingCampaignPostShowOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_owner_cannot_retry_or_force_fail_publication()
    {
        Queue::fake();

        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Marketing->value]);
        $otherUser = User::factory()->create(['role' => \App\Enums\UserRole::Photographer->value]);

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);
        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Facebook->value,
            'status' => PublicationStatus::Failed->value,
        ]);

        // Access as non-marketing user
        Livewire::actingAs($otherUser)
            ->test(\App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->assertForbidden(); // The whole component fails to mount due to authorize('view')

        Queue::assertNothingPushed();
    }

    public function test_instagram_cancellation_branch_on_retry()
    {
        Queue::fake();

        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Marketing->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post->value,
        ]);
        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Instagram->value,
            'status' => PublicationStatus::Failed->value,
        ]);

        Livewire::actingAs($owner)
            ->test(\App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->call('retryPublication', $publication->id);

        $publication->refresh();
        
        $this->assertEquals(PublicationStatus::Cancelled, $publication->status);
        $this->assertEquals('Dismesso (sostituito da nuovo tentativo)', $publication->error_message);
        
        Queue::assertPushed(\App\Jobs\Social\PublishMarketingCampaignPostJob::class, function ($job) use ($post) {
            return $job->post->id === $post->id && $job->platform === 'instagram';
        });
    }
}
