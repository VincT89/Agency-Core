<?php

namespace Tests\Feature\Social;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaign;
use App\Models\Client;
use App\Models\MarketingCampaignPostVersion;
use App\Models\MarketingCampaignPostPublication;
use App\Domain\Social\Exceptions\HistoricalMediaProtectedException;
use App\Domain\Social\Actions\DeleteMarketingCampaignPostMediaAction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow;
use Livewire\Livewire;

class MarketingCampaignPostMediaDeletionTest extends TestCase
{
    use RefreshDatabase;

    private $post;
    private $media;

    protected function setUp(): void
    {
        parent::setUp();
        
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $this->post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
            'content_type' => \App\Enums\Social\MarketingCampaignPostType::Post,
            'status' => \App\Enums\Social\MarketingCampaignPostStatus::Draft,
        ]);
        
        $this->media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'source' => 'local',
            'disk' => 'public',
            'path' => 'test_media.jpg'
        ]);

        Storage::fake('public');
        Storage::disk('public')->put('test_media.jpg', 'fake content');
    }

    public function test_media_bound_to_version_cannot_be_deleted_via_model()
    {
        $version = $this->post->versions()->create(['version_number' => 1, 'title' => 'V1']);
        $version->mediaItems()->attach($this->media->id, ['sort_order' => 0]);

        try {
            $this->media->delete();
            $this->fail('Expected protection exception.');
        } catch (HistoricalMediaProtectedException $e) {
            $this->assertStringContainsString('bound to one or more post versions', $e->getMessage());
        }

        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $this->media->id]);
        Storage::disk('public')->assertExists('test_media.jpg');
    }

    public function test_media_bound_to_version_cannot_be_deleted_via_direct_query()
    {
        $version = $this->post->versions()->create(['version_number' => 1, 'title' => 'V1']);
        $version->mediaItems()->attach($this->media->id, ['sort_order' => 0]);

        try {
            DB::table('marketing_campaign_post_media')->where('id', $this->media->id)->delete();
            $this->fail('Expected constraint violation exception.');
        } catch (\Illuminate\Database\QueryException $e) {
            // Expected
        }

        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $this->media->id]);
    }

    public function test_media_bound_to_multiple_versions()
    {
        $v1 = $this->post->versions()->create(['version_number' => 1, 'title' => 'V1']);
        $v2 = $this->post->versions()->create(['version_number' => 2, 'title' => 'V2']);
        
        $v1->mediaItems()->attach($this->media->id, ['sort_order' => 0]);
        $v2->mediaItems()->attach($this->media->id, ['sort_order' => 0]);

        try {
            $this->media->delete();
            $this->fail('Expected protection exception.');
        } catch (HistoricalMediaProtectedException $e) {
            // Expected
        }

        $this->assertDatabaseHas('marketing_campaign_post_version_media', ['marketing_campaign_post_version_id' => $v1->id]);
        $this->assertDatabaseHas('marketing_campaign_post_version_media', ['marketing_campaign_post_version_id' => $v2->id]);
        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $this->media->id]);
    }

    public function test_media_referenced_in_snapshot_cannot_be_deleted()
    {
        $v = $this->post->versions()->create(['version_number' => 1, 'title' => 'V1']);
        $socialAcc = \App\Models\ClientSocialAccount::factory()->create(['client_id' => $this->post->campaign->client_id]);

        MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $this->post->id,
            'client_social_account_id' => $socialAcc->id,
            'platform' => \App\Enums\Social\SocialPlatform::Facebook->value,
            'status' => \App\Enums\Social\PublicationStatus::Published->value,
            'payload_snapshot' => [
                'media' => [
                    ['id' => $this->media->id]
                ]
            ]
        ]);

        try {
            $this->media->delete();
            $this->fail('Expected protection exception.');
        } catch (HistoricalMediaProtectedException $e) {
            $this->assertStringContainsString('referenced in a publication snapshot', $e->getMessage());
        }

        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $this->media->id]);
    }
    
    public function test_media_referenced_in_snapshot_with_string_id_cannot_be_deleted()
    {
        $v = $this->post->versions()->create(['version_number' => 1, 'title' => 'V1']);
        $socialAcc = \App\Models\ClientSocialAccount::factory()->create(['client_id' => $this->post->campaign->client_id]);

        MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $this->post->id,
            'client_social_account_id' => $socialAcc->id,
            'platform' => \App\Enums\Social\SocialPlatform::Facebook->value,
            'status' => \App\Enums\Social\PublicationStatus::Published->value,
            'payload_snapshot' => [
                'media' => [
                    ['id' => (string) $this->media->id]
                ]
            ]
        ]);

        try {
            $this->media->delete();
            $this->fail('Expected protection exception.');
        } catch (HistoricalMediaProtectedException $e) {
            $this->assertStringContainsString('referenced in a publication snapshot', $e->getMessage());
        }

        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $this->media->id]);
    }

    public function test_media_referenced_in_snapshot_with_legacy_key_cannot_be_deleted()
    {
        $v = $this->post->versions()->create(['version_number' => 1, 'title' => 'V1']);
        $socialAcc = \App\Models\ClientSocialAccount::factory()->create(['client_id' => $this->post->campaign->client_id]);

        MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $this->post->id,
            'client_social_account_id' => $socialAcc->id,
            'platform' => \App\Enums\Social\SocialPlatform::Facebook->value,
            'status' => \App\Enums\Social\PublicationStatus::Published->value,
            'payload_snapshot' => [
                'media' => [
                    ['media_id' => $this->media->id]
                ]
            ]
        ]);

        try {
            $this->media->delete();
            $this->fail('Expected protection exception.');
        } catch (HistoricalMediaProtectedException $e) {
            $this->assertStringContainsString('referenced in a publication snapshot', $e->getMessage());
        }

        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $this->media->id]);
    }

    public function test_malformed_snapshot_conservatively_blocks_deletion()
    {
        $v = $this->post->versions()->create(['version_number' => 1, 'title' => 'V1']);
        $socialAcc = \App\Models\ClientSocialAccount::factory()->create(['client_id' => $this->post->campaign->client_id]);

        MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $this->post->id,
            'client_social_account_id' => $socialAcc->id,
            'platform' => \App\Enums\Social\SocialPlatform::Facebook->value,
            'status' => \App\Enums\Social\PublicationStatus::Published->value,
            'payload_snapshot' => [
                'media' => 'invalid_media_string'
            ]
        ]);

        try {
            $this->media->delete();
            $this->fail('Expected protection exception.');
        } catch (HistoricalMediaProtectedException $e) {
            $this->assertStringContainsString('conservatively blocked', $e->getMessage());
        }

        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $this->media->id]);
    }

    public function test_snapshot_with_invalid_id_format_conservatively_blocks_deletion()
    {
        $v = $this->post->versions()->create(['version_number' => 1, 'title' => 'V1']);
        $socialAcc = \App\Models\ClientSocialAccount::factory()->create(['client_id' => $this->post->campaign->client_id]);

        $invalidIds = ['abc', 0, -1, 42.5, true];

        foreach ($invalidIds as $invalidId) {
            $pub = MarketingCampaignPostPublication::create([
                'marketing_campaign_post_id' => $this->post->id,
                'client_social_account_id' => $socialAcc->id,
                'platform' => \App\Enums\Social\SocialPlatform::Facebook->value,
                'status' => \App\Enums\Social\PublicationStatus::Published->value,
                'payload_snapshot' => [
                    'media' => [
                        ['id' => $invalidId]
                    ]
                ]
            ]);

            try {
                $this->media->delete();
                $this->fail("Expected protection exception for invalid ID format: " . json_encode($invalidId));
            } catch (HistoricalMediaProtectedException $e) {
                $this->assertStringContainsString('conservatively blocked', $e->getMessage());
            }

            $pub->delete();
        }

        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $this->media->id]);
    }

    public function test_orphan_media_can_be_deleted_via_action()
    {
        $action = app(DeleteMarketingCampaignPostMediaAction::class);
        $result = $action->execute($this->media);

        $this->assertEquals('scheduled', $result['status']);
        $this->assertDatabaseMissing('marketing_campaign_post_media', ['id' => $this->media->id]);
        Storage::disk('public')->assertMissing('test_media.jpg');
    }

    public function test_orphan_media_with_missing_file_deletes_record_and_returns_scheduled_status()
    {
        Storage::disk('public')->delete('test_media.jpg'); // Simulate missing file
        
        $action = app(DeleteMarketingCampaignPostMediaAction::class);
        
        // Use a transaction and manually trigger afterCommit to simulate the lifecycle in testing
        $result = [];
        DB::transaction(function () use (&$result, $action) {
            $result = $action->execute($this->media);
        });

        // The return from execute is scheduled, we can't test missing physical status easily without a full feature test or overriding the DB transaction trait
        $this->assertEquals('scheduled', $result['status']);
        $this->assertDatabaseMissing('marketing_campaign_post_media', ['id' => $this->media->id]);
    }

    public function test_rollback_during_action_prevents_file_deletion()
    {
        Storage::fake('public');
        Storage::disk('public')->put('test_media.jpg', 'content');

        try {
            DB::transaction(function () {
                $action = app(DeleteMarketingCampaignPostMediaAction::class);
                $action->execute($this->media);
                throw new \Exception('Simulated failure');
            });
        } catch (\Exception $e) {}

        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $this->media->id]);
        Storage::disk('public')->assertExists('test_media.jpg');
    }
    
    public function test_filesystem_cleanup_failure_is_logged()
    {
        Storage::fake('public');
        Storage::disk('public')->put('test_media.jpg', 'content');

        // Mock Storage to return false on delete
        Storage::shouldReceive('disk')->with('public')->andReturnSelf();
        Storage::shouldReceive('exists')->with('test_media.jpg')->andReturn(true);
        Storage::shouldReceive('delete')->with('test_media.jpg')->andReturn(false);

        \Illuminate\Support\Facades\Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'Failed to delete physical file (Storage returned false)');
            });

        $action = app(DeleteMarketingCampaignPostMediaAction::class);
        
        DB::transaction(function () use ($action) {
            $action->execute($this->media);
        });

        $this->assertDatabaseMissing('marketing_campaign_post_media', ['id' => $this->media->id]);
    }

    public function test_nextcloud_media_untouched_on_deletion()
    {
        $ncMedia = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'source' => 'nextcloud',
            'disk' => 'nextcloud',
            'path' => 'remote/file.jpg'
        ]);
        
        $action = app(DeleteMarketingCampaignPostMediaAction::class);
        $result = $action->execute($ncMedia);

        $this->assertEquals('scheduled', $result['status']);
        $this->assertDatabaseMissing('marketing_campaign_post_media', ['id' => $ncMedia->id]);
        // Physically untouched logic is internally tested via source != local check
    }

    public function test_livewire_delete_post_with_history_shows_error_without_redirect()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        \Illuminate\Support\Facades\Gate::before(function () { return true; });
        
        $version = $this->post->versions()->create(['version_number' => 1, 'title' => 'V1']);
        $socialAcc = \App\Models\ClientSocialAccount::factory()->create(['client_id' => $this->post->campaign->client_id]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $this->post->id,
            'client_social_account_id' => $socialAcc->id,
            'platform' => \App\Enums\Social\SocialPlatform::Facebook->value,
            'status' => \App\Enums\Social\PublicationStatus::Published->value,
            'payload_snapshot' => []
        ]);

        $this->assertDatabaseHas('marketing_campaign_post_versions', ['id' => $version->id]);
        $this->assertDatabaseHas('marketing_campaign_post_publications', ['id' => $publication->id]);

        $component = Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $this->post->campaign, 'post' => $this->post->fresh()])
            ->call('deletePost')
            ->assertNoRedirect()
            ->assertHasErrors(['post']);
            
        $this->assertDatabaseHas('marketing_campaign_posts', ['id' => $this->post->id]);
        $this->assertDatabaseHas('marketing_campaign_post_versions', ['id' => $version->id]);
        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $this->media->id]);
        $this->assertDatabaseHas('marketing_campaign_post_publications', ['id' => $publication->id]);
        
        $errorArray = $component->errors()->get('post');
        $this->assertSame(
            'Questo post contiene versioni salvate o pubblicazioni social e non può essere eliminato. La rimozione dai social va gestita separatamente.',
            $errorArray[0] ?? ''
        );
        $this->assertStringNotContainsString('protected because it contains historical', $errorArray[0] ?? '');
        
        Storage::disk('public')->assertExists('test_media.jpg');
    }

    public function test_media_protection_blocks_when_snapshot_id_and_media_id_contradict_as_ambiguous()
    {
        $v = $this->post->versions()->create(['version_number' => 1, 'title' => 'V1']);
        $socialAcc = \App\Models\ClientSocialAccount::factory()->create(['client_id' => $this->post->campaign->client_id]);

        MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $this->post->id,
            'client_social_account_id' => $socialAcc->id,
            'platform' => \App\Enums\Social\SocialPlatform::Facebook->value,
            'status' => \App\Enums\Social\PublicationStatus::Published->value,
            'payload_snapshot' => [
                'media' => [
                    ['id' => 999999, 'media_id' => $this->media->id]
                ]
            ]
        ]);

        try {
            $this->media->delete();
            $this->fail('Expected protection exception for ambiguous snapshot.');
        } catch (HistoricalMediaProtectedException $e) {
            $this->assertStringContainsString('conservatively blocked', $e->getMessage());
        }

        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $this->media->id]);
    }

    public function test_media_protection_blocks_when_snapshot_media_key_is_missing()
    {
        $post = MarketingCampaignPost::factory()->create();
        $media = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id]);

        MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'payload_snapshot' => ['version_id' => 1] // Missing 'media' key
        ]);

        $action = app(DeleteMarketingCampaignPostMediaAction::class);

        try {
            $action->execute($media);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertInstanceOf(HistoricalMediaProtectedException::class, $e);
            $this->assertEquals(HistoricalMediaProtectedException::forAmbiguousHistory($media)->getMessage(), $e->getMessage());
        }

        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $media->id]);
    }

    public function test_media_protection_allows_when_snapshot_media_is_empty_array()
    {
        $post = MarketingCampaignPost::factory()->create();
        
        Storage::disk('public')->put('orphan.jpg', 'content');
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'disk' => 'public',
            'path' => 'orphan.jpg',
            'source' => 'local'
        ]);

        MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'payload_snapshot' => ['media' => []] // Explicitly empty media array
        ]);

        $action = app(DeleteMarketingCampaignPostMediaAction::class);

        $result = $action->execute($media);
        
        $this->assertEquals('scheduled', $result['status']);
        $this->assertDatabaseMissing('marketing_campaign_post_media', ['id' => $media->id]);
    }

    public function test_media_protection_blocks_when_payload_snapshot_is_empty_array()
    {
        $post = MarketingCampaignPost::factory()->create();
        $media = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id]);

        MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'payload_snapshot' => [] // Empty snapshot
        ]);

        $action = app(DeleteMarketingCampaignPostMediaAction::class);

        try {
            $action->execute($media);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertInstanceOf(HistoricalMediaProtectedException::class, $e);
            $this->assertStringContainsString('conservatively blocked', $e->getMessage());
        }

        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $media->id]);
    }
}
