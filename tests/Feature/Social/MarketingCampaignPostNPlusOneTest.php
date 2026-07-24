<?php

namespace Tests\Feature\Social;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostVersion;
use App\Models\MarketingCampaignPostMedia;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

class MarketingCampaignPostNPlusOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_campaign_show_does_not_have_n_plus_one_queries()
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);

        // Setup 1 post
        $post1 = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'status' => \App\Enums\Social\MarketingCampaignPostStatus::Draft
        ]);
        $version1 = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post1->id,
            'version_number' => 1
        ]);
        $post1->update(['current_version_id' => $version1->id]);
        $media1 = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post1->id,
        ]);
        $version1->mediaItems()->attach($media1->id, ['sort_order' => 0]);

        // Trigger initial load to warm up cache / auth
        $this->actingAs($admin);
        Livewire::test(\App\Livewire\Social\MarketingCampaigns\MarketingCampaignShow::class, ['campaign' => $campaign]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        Livewire::test(\App\Livewire\Social\MarketingCampaigns\MarketingCampaignShow::class, ['campaign' => $campaign]);

        $queriesWithOnePost = count(DB::getQueryLog());

        DB::flushQueryLog();
        DB::disableQueryLog();

        // Add 9 more posts
        for ($i = 0; $i < 9; $i++) {
            $post = MarketingCampaignPost::factory()->create([
                'marketing_campaign_id' => $campaign->id,
                'status' => \App\Enums\Social\MarketingCampaignPostStatus::Draft
            ]);
            $version = MarketingCampaignPostVersion::factory()->create([
                'marketing_campaign_post_id' => $post->id,
                'version_number' => 1
            ]);
            $post->update(['current_version_id' => $version->id]);
            $media = MarketingCampaignPostMedia::factory()->create([
                'marketing_campaign_post_id' => $post->id,
            ]);
            $version->mediaItems()->attach($media->id, ['sort_order' => 0]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        Livewire::test(\App\Livewire\Social\MarketingCampaigns\MarketingCampaignShow::class, ['campaign' => $campaign]);

        $queriesWithTenPosts = count(DB::getQueryLog());
        DB::disableQueryLog();

        // The number of queries should be relatively stable.
        // Even with 10 posts vs 1 post, it shouldn't grow by 10 or more.
        // Allow a small margin (e.g. 5) for unrelated eager loads or distinct queries.
        $this->assertLessThan(
            $queriesWithOnePost + 5,
            $queriesWithTenPosts,
            "N+1 detectato: 1 post = {$queriesWithOnePost} query, 10 post = {$queriesWithTenPosts} query"
        );
    }
}
