<?php

namespace Tests\Feature\Social;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaign;
use App\Models\Client;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostPublication;
use App\Domain\Social\Actions\DeleteMarketingCampaignPostAction;
use App\Domain\Social\Exceptions\HistoricalPostProtectedException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Exception;

class MarketingCampaignPostDeletionTest extends TestCase
{
    use RefreshDatabase;

    private $post;

    protected function setUp(): void
    {
        parent::setUp();
        
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $this->post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id]);
    }

    public function test_post_with_versions_cannot_be_deleted()
    {
        $this->post->versions()->create(['version_number' => 1, 'title' => 'V1']);

        $action = app(DeleteMarketingCampaignPostAction::class);

        try {
            $action->execute($this->post);
            $this->fail('Expected exception for historical post');
        } catch (Exception $e) {
            $this->assertStringContainsString('historical versions', $e->getMessage());
        }
        
        $this->assertDatabaseHas('marketing_campaign_posts', ['id' => $this->post->id]);
    }

    public function test_post_with_publications_cannot_be_deleted()
    {
        $socialAcc = \App\Models\ClientSocialAccount::factory()->create(['client_id' => $this->post->campaign->client_id]);

        MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $this->post->id,
            'client_social_account_id' => $socialAcc->id,
            'platform' => \App\Enums\Social\SocialPlatform::Facebook->value,
            'status' => \App\Enums\Social\PublicationStatus::Published->value,
            'payload_snapshot' => []
        ]);

        $action = app(DeleteMarketingCampaignPostAction::class);

        try {
            $action->execute($this->post);
            $this->fail('Expected exception for historical post');
        } catch (Exception $e) {
            $this->assertStringContainsString('historical versions or publications', $e->getMessage());
        }
        
        $this->assertDatabaseHas('marketing_campaign_posts', ['id' => $this->post->id]);
    }

    public function test_post_never_versioned_deletes_itself_and_media_via_action()
    {
        Storage::fake('public');
        Storage::disk('public')->put('orphan.jpg', 'content');

        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'source' => 'local',
            'disk' => 'public',
            'path' => 'orphan.jpg'
        ]);

        $action = app(DeleteMarketingCampaignPostAction::class);
        $action->execute($this->post);

        $this->assertDatabaseMissing('marketing_campaign_post_media', ['id' => $media->id]);
        $this->assertDatabaseMissing('marketing_campaign_posts', ['id' => $this->post->id]);
        Storage::disk('public')->assertMissing('orphan.jpg');
    }

    public function test_deleting_campaign_fails_if_post_has_media_due_to_restrict()
    {
        MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
        ]);

        try {
            DB::table('marketing_campaigns')->where('id', $this->post->marketing_campaign_id)->delete();
            $this->fail('Expected DB query exception');
        } catch (\Illuminate\Database\QueryException $e) {}
        
        $this->assertDatabaseHas('marketing_campaign_posts', ['id' => $this->post->id]);
    }
    
    public function test_post_with_version_and_no_media_blocks_campaign_deletion_due_to_restrict()
    {
        $this->post->versions()->create(['version_number' => 1, 'title' => 'V1']);
        
        try {
            DB::table('marketing_campaigns')->where('id', $this->post->marketing_campaign_id)->delete();
            $this->fail('Expected DB query exception for restrict constraint on campaign');
        } catch (\Illuminate\Database\QueryException $e) {}
        
        $this->assertDatabaseHas('marketing_campaign_posts', ['id' => $this->post->id]);
        $this->assertDatabaseHas('marketing_campaign_post_versions', ['marketing_campaign_post_id' => $this->post->id]);
    }

    public function test_post_with_version_and_no_media_blocks_direct_post_deletion_due_to_restrict()
    {
        $this->post->versions()->create(['version_number' => 1, 'title' => 'V1']);

        try {
            DB::table('marketing_campaign_posts')->where('id', $this->post->id)->delete();
            $this->fail('Expected DB query exception for restrict constraint on post versions');
        } catch (\Illuminate\Database\QueryException $e) {}
        
        $this->assertDatabaseHas('marketing_campaign_posts', ['id' => $this->post->id]);
        $this->assertDatabaseHas('marketing_campaign_post_versions', ['marketing_campaign_post_id' => $this->post->id]);
    }

    public function test_post_with_publication_and_no_media_blocks_direct_post_deletion_due_to_restrict()
    {
        $socialAcc = \App\Models\ClientSocialAccount::factory()->create(['client_id' => $this->post->campaign->client_id]);

        $pub = MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $this->post->id,
            'client_social_account_id' => $socialAcc->id,
            'platform' => \App\Enums\Social\SocialPlatform::Facebook->value,
            'status' => \App\Enums\Social\PublicationStatus::Published->value,
        ]);

        try {
            DB::table('marketing_campaign_posts')->where('id', $this->post->id)->delete();
            $this->fail('Expected DB query exception for restrict constraint on post publications');
        } catch (\Illuminate\Database\QueryException $e) {}
        
        $this->assertDatabaseHas('marketing_campaign_posts', ['id' => $this->post->id]);
        $this->assertDatabaseHas('marketing_campaign_post_publications', ['id' => $pub->id]);
    }
}
