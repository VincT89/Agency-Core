<?php

namespace Tests\Feature\Core;

use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostVersion;
use App\Models\User;
use App\Domain\Core\Actions\DeleteClientAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_client_without_campaigns_can_be_deleted()
    {
        $client = Client::factory()->create();
        
        $action = app(DeleteClientAction::class);
        $action->execute($client);
        
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_client_with_empty_campaign_can_be_deleted()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        
        $action = app(DeleteClientAction::class);
        $action->execute($client);
        
        $this->assertDatabaseMissing('marketing_campaigns', ['id' => $campaign->id]);
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_client_with_multiple_campaigns_and_draft_posts_deletes_all_securely()
    {
        $client = Client::factory()->create();
        $campaign1 = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $campaign2 = MarketingCampaign::factory()->create(['client_id' => $client->id]);

        $post1 = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign1->id]);
        $post2 = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign2->id]);

        Storage::disk('public')->put('draft1.jpg', 'content1');
        Storage::disk('public')->put('draft2.jpg', 'content2');

        $media1 = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post1->id,
            'source' => 'local',
            'disk' => 'public',
            'path' => 'draft1.jpg'
        ]);

        $media2 = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post2->id,
            'source' => 'local',
            'disk' => 'public',
            'path' => 'draft2.jpg'
        ]);

        $action = app(DeleteClientAction::class);
        $action->execute($client);

        // Check records are deleted
        $this->assertDatabaseMissing('marketing_campaign_post_media', ['id' => $media1->id]);
        $this->assertDatabaseMissing('marketing_campaign_post_media', ['id' => $media2->id]);
        $this->assertDatabaseMissing('marketing_campaign_posts', ['id' => $post1->id]);
        $this->assertDatabaseMissing('marketing_campaign_posts', ['id' => $post2->id]);
        $this->assertDatabaseMissing('marketing_campaigns', ['id' => $campaign1->id]);
        $this->assertDatabaseMissing('marketing_campaigns', ['id' => $campaign2->id]);
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);

        // Check files are deleted (via afterCommit in nested action)
        Storage::disk('public')->assertMissing('draft1.jpg');
        Storage::disk('public')->assertMissing('draft2.jpg');
    }

    public function test_client_with_historical_post_blocks_entire_operation()
    {
        $client = Client::factory()->create();
        $campaign1 = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $campaign2 = MarketingCampaign::factory()->create(['client_id' => $client->id]);

        $historicalPost = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign1->id]);
        $draftPost = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign2->id]);

        // Make historical
        MarketingCampaignPostVersion::create([
            'marketing_campaign_post_id' => $historicalPost->id,
            'version_number' => 1,
            'title' => 'V1'
        ]);

        Storage::disk('public')->put('draft.jpg', 'content');
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $draftPost->id,
            'source' => 'local',
            'disk' => 'public',
            'path' => 'draft.jpg'
        ]);

        $action = app(DeleteClientAction::class);

        try {
            $action->execute($client);
            $this->fail('Expected exception for historical posts.');
        } catch (\App\Domain\Social\Exceptions\HistoricalPostProtectedException $e) {
            $this->assertStringContainsString('protected because it contains historical', $e->getMessage());
        }

        // Database records should remain untouched
        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $media->id]);
        $this->assertDatabaseHas('marketing_campaign_posts', ['id' => $draftPost->id]);
        $this->assertDatabaseHas('marketing_campaign_posts', ['id' => $historicalPost->id]);
        $this->assertDatabaseHas('marketing_campaigns', ['id' => $campaign1->id]);
        $this->assertDatabaseHas('marketing_campaigns', ['id' => $campaign2->id]);
        $this->assertDatabaseHas('clients', ['id' => $client->id]);

        // File should not be deleted
        Storage::disk('public')->assertExists('draft.jpg');
    }

    public function test_client_with_publication_blocks_entire_operation()
    {
        $client = Client::factory()->create();
        $campaign1 = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $campaign2 = MarketingCampaign::factory()->create(['client_id' => $client->id]);

        $publishedPost = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign1->id]);
        $draftPost = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign2->id]);

        // Make publication
        $socialAcc = \App\Models\ClientSocialAccount::factory()->create(['client_id' => $client->id]);
        \App\Models\MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $publishedPost->id,
            'client_social_account_id' => $socialAcc->id,
            'platform' => \App\Enums\Social\SocialPlatform::Facebook->value,
            'status' => \App\Enums\Social\PublicationStatus::Published->value,
            'payload_snapshot' => []
        ]);

        Storage::disk('public')->put('draft.jpg', 'content');
        $media = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $draftPost->id,
            'source' => 'local',
            'disk' => 'public',
            'path' => 'draft.jpg'
        ]);

        $action = app(DeleteClientAction::class);

        try {
            $action->execute($client);
            $this->fail('Expected exception for historical posts.');
        } catch (\App\Domain\Social\Exceptions\HistoricalPostProtectedException $e) {
            $this->assertStringContainsString('protected because it contains historical', $e->getMessage());
        }

        // Database records should remain untouched
        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $media->id]);
        $this->assertDatabaseHas('marketing_campaign_posts', ['id' => $draftPost->id]);
        $this->assertDatabaseHas('marketing_campaign_posts', ['id' => $publishedPost->id]);
        $this->assertDatabaseHas('marketing_campaigns', ['id' => $campaign1->id]);
        $this->assertDatabaseHas('marketing_campaigns', ['id' => $campaign2->id]);
        $this->assertDatabaseHas('clients', ['id' => $client->id]);

        // File should not be deleted
        Storage::disk('public')->assertExists('draft.jpg');
    }

    public function test_error_during_nested_deletion_triggers_complete_rollback()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);

        $post1 = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id]);
        $post2 = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id]);

        Storage::disk('public')->put('draft1.jpg', 'content1');
        Storage::disk('public')->put('draft2.jpg', 'content2');

        $media1 = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post1->id,
            'source' => 'local',
            'disk' => 'public',
            'path' => 'draft1.jpg'
        ]);

        $media2 = MarketingCampaignPostMedia::factory()->create([
            'marketing_campaign_post_id' => $post2->id,
            'source' => 'local',
            'disk' => 'public',
            'path' => 'draft2.jpg'
        ]);

        $originalAction = app(\App\Domain\Social\Actions\DeleteMarketingCampaignPostAction::class);
        $decorator = new class($originalAction) extends \App\Domain\Social\Actions\DeleteMarketingCampaignPostAction {
            private $original;
            public int $invocationCount = 0;
            
            // Allow bypassing the constructor dependencies
            public function __construct($original) {
                $this->original = $original;
            }
            
            public function execute(\App\Models\MarketingCampaignPost $post): void {
                $this->invocationCount++;
                if ($this->invocationCount === 2) {
                    throw new \Exception('Simulated Failure on second post');
                }
                $this->original->execute($post);
            }
        };

        $this->app->instance(\App\Domain\Social\Actions\DeleteMarketingCampaignPostAction::class, $decorator);

        $action = app(DeleteClientAction::class);

        try {
            $action->execute($client);
            $this->fail('Expected simulated failure.');
        } catch (\Exception $e) {
            $this->assertEquals('Simulated Failure on second post', $e->getMessage());
        }

        $decorator = app(\App\Domain\Social\Actions\DeleteMarketingCampaignPostAction::class);
        $this->assertEquals(2, $decorator->invocationCount, 'Action should have been called exactly twice');

        // DB Rollback
        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $media1->id]);
        $this->assertDatabaseHas('marketing_campaign_post_media', ['id' => $media2->id]);
        $this->assertDatabaseHas('marketing_campaign_posts', ['id' => $post1->id]);
        $this->assertDatabaseHas('marketing_campaign_posts', ['id' => $post2->id]);
        $this->assertDatabaseHas('marketing_campaigns', ['id' => $campaign->id]);
        $this->assertDatabaseHas('clients', ['id' => $client->id]);

        // Files untouched because outer transaction never committed
        Storage::disk('public')->assertExists('draft1.jpg');
        Storage::disk('public')->assertExists('draft2.jpg');
    }

    public function test_client_controller_destroy_handles_historical_protection()
    {
        $user = User::factory()->create();
        // Assuming standard permissions, bypassed by Gate::before
        
        // Let's just bypass authorization for simplicity of testing the controller exception logic
        \Illuminate\Support\Facades\Gate::before(function () { return true; });

        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $historicalPost = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id]);
        
        MarketingCampaignPostVersion::create([
            'marketing_campaign_post_id' => $historicalPost->id,
            'version_number' => 1,
            'title' => 'V1'
        ]);

        $response = $this->actingAs($user)->delete(route('clients.destroy', $client));
        
        $response->assertSessionHas('error', 'Impossibile eliminare il cliente perché contiene dati storici protetti.');
        $response->assertRedirect();
        
        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_client_controller_destroy_rethrows_unrelated_query_exception()
    {
        $user = User::factory()->create();
        \Illuminate\Support\Facades\Gate::before(function () { return true; });

        $client = Client::factory()->create();
        
        // Mock DeleteClientAction to throw a non-referential QueryException
        $mockAction = \Mockery::mock(DeleteClientAction::class);
        
        $sqlState = '23000'; // Could be a unique constraint
        $pdoException = new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry for key unique_marketing_campaign');
        $pdoException->errorInfo = ['23000', 1062, 'Duplicate entry']; // 1062 is unique constraint, NOT 1451/19
        
        $queryException = new \Illuminate\Database\QueryException(
            'connection_name',
            'INSERT INTO marketing_campaign_posts...',
            [],
            $pdoException
        );
        
        $mockAction->shouldReceive('execute')->andThrow($queryException);
        $this->app->instance(DeleteClientAction::class, $mockAction);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->delete(route('clients.destroy', $client));
            $this->fail('Expected QueryException to be rethrown.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('Duplicate entry', $e->getMessage());
        }
    }

    public function test_client_controller_destroy_rethrows_1451_on_non_historical_fk()
    {
        $user = User::factory()->create();
        \Illuminate\Support\Facades\Gate::before(function () { return true; });

        $client = Client::factory()->create();
        
        $mockAction = \Mockery::mock(DeleteClientAction::class);
        
        // 1451 error but for an unrelated FK that happens to contain "post" in the table name or constraint name
        // e.g. a constraint named "unrelated_post_data_fk"
        $pdoException = new \PDOException('Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`agency`.`unrelated_post_logs`, CONSTRAINT `unrelated_post_logs_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE RESTRICT)');
        $pdoException->errorInfo = ['23000', 1451, 'Cannot delete or update a parent row'];
        
        $mockAction->shouldReceive('execute')->andThrow(new \Illuminate\Database\QueryException(
            'connection_name',
            'DELETE FROM clients',
            [],
            $pdoException
        ));
        $this->app->instance(DeleteClientAction::class, $mockAction);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->delete(route('clients.destroy', $client));
            $this->fail('Expected QueryException to be rethrown for non-historical FK 1451.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('unrelated_post_logs_client_id_foreign', $e->getMessage());
        }
    }

    public function test_client_controller_destroy_catches_referential_query_exception()
    {
        $user = User::factory()->create();
        \Illuminate\Support\Facades\Gate::before(function () { return true; });

        $client = Client::factory()->create();
        
        // Mock DeleteClientAction to throw a recognized referential QueryException
        $mockAction = \Mockery::mock(DeleteClientAction::class);
        
        $pdoException = new \PDOException('Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`agency`.`marketing_campaign_posts`, CONSTRAINT `marketing_campaign_posts_marketing_campaign_id_foreign` FOREIGN KEY (`marketing_campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE RESTRICT)');
        $pdoException->errorInfo = ['23000', 1451, 'Cannot delete or update a parent row'];
        
        $mockAction->shouldReceive('execute')->andThrow(new \Illuminate\Database\QueryException(
            'connection_name',
            'DELETE FROM clients',
            [],
            $pdoException
        ));
        $this->app->instance(DeleteClientAction::class, $mockAction);

        $response = $this->actingAs($user)->delete(route('clients.destroy', $client));
        
        $response->assertSessionHas('error', 'Impossibile eliminare il cliente perché contiene dati storici protetti.');
        $response->assertRedirect();
    }
}
