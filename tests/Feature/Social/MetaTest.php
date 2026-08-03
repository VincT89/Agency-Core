<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Actions\RefreshAgencyConnectionAction;
use App\Domain\Social\Actions\SyncMetaAssetsAction;
use App\Domain\Social\Actions\ValidateAgencyAssetAssignmentAction;
use App\Domain\Social\DTO\SyncMetaAssetsResult;
use App\Domain\Social\DTOs\PublicationMediaDeliveryResult;
use App\Domain\Social\Publishing\MetaPublisher;
use App\Domain\Social\Services\PublicationMediaDeliveryService;
use App\Enums\Social\AgencyConnectionStatus;
use App\Enums\Social\PublishingStatus;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialAssetType;
use App\Enums\Social\SocialConnectionStrategy;
use App\Enums\Social\SocialPlatform;
use App\Http\Controllers\Admin\Social\AgencyMetaOAuthController;
use App\Models\AgencySocialAsset;
use App\Models\AgencySocialConnection;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPostPublication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\FacebookProvider;
use Mockery;
use Tests\TestCase;

class MetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.meta.config_id' => 'test-meta-config-id',
            'services.meta.graph_version' => 'v25.0',
            'social.publishing.dry_run' => false,
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    // --- Meta OAuth ---
    public function test_meta_redirect_oauth_correctly_forms_url(): void
    {
        $mockProvider = Mockery::mock(FacebookProvider::class);
        $mockProvider->shouldReceive('usingGraphVersion')
            ->once()
            ->with('v25.0')
            ->andReturnSelf();
        $mockProvider->shouldReceive('setHttpClient')->once()->andReturnSelf();
        $mockProvider->shouldReceive('setScopes')
            ->once()
            ->with([
                'pages_manage_posts',
                'pages_read_engagement',
                'pages_show_list',
                'business_management',
                'instagram_basic',
                'instagram_content_publish',
            ])
            ->andReturnSelf();
        $mockProvider->shouldReceive('with')
            ->once()
            ->with(['config_id' => 'test-meta-config-id'])
            ->andReturnSelf();
        $mockProvider->shouldReceive('redirect')->andReturn(redirect('https://facebook.com/v19.0/dialog/oauth'));

        Socialite::shouldReceive('driver')->with('facebook')->andReturn($mockProvider);

        $controller = app(AgencyMetaOAuthController::class);
        $response = $controller->redirect();

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('facebook.com', $response->getTargetUrl());
    }

    public function test_meta_redirect_stops_when_business_login_configuration_is_missing(): void
    {
        config(['services.meta.config_id' => null]);

        Socialite::shouldReceive('driver')->never();

        $controller = app(AgencyMetaOAuthController::class);
        $response = $controller->redirect();

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('non è configurato', session('error'));
    }

    public function test_meta_callback_processes_authorization_code_successfully(): void
    {
        $mockUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $mockUser->shouldReceive('getId')->andReturn('12345');
        $mockUser->shouldReceive('getName')->andReturn('Test Agency');
        $mockUser->shouldReceive('getNickname')->andReturn('test_agency');
        $mockUser->token = 'fake_token';
        $mockUser->refreshToken = 'fake_refresh';
        $mockUser->expiresIn = 3600;
        $mockUser->approvedScopes = ['pages_manage_posts'];

        $mockProvider = Mockery::mock(FacebookProvider::class);
        $mockProvider->shouldReceive('usingGraphVersion')
            ->once()
            ->with('v25.0')
            ->andReturnSelf();
        $mockProvider->shouldReceive('setHttpClient')->once()->andReturnSelf();
        $mockProvider->shouldReceive('user')->andReturn($mockUser);

        Socialite::shouldReceive('driver')->with('facebook')->andReturn($mockProvider);

        $mockSyncAction = Mockery::mock(SyncMetaAssetsAction::class);
        $mockSyncAction->shouldReceive('execute')
            ->once()
            ->andReturn(new SyncMetaAssetsResult);
        $this->app->instance(SyncMetaAssetsAction::class, $mockSyncAction);

        $request = Request::create('/callback', 'GET', ['code' => 'fake_code']);
        $controller = app(AgencyMetaOAuthController::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $controller->callback($request);

        $this->assertTrue($response->isRedirect());
        $this->assertDatabaseHas('agency_social_connections', [
            'provider' => 'facebook',
            'provider_user_id' => '12345',
        ]);
        $this->assertSame(
            'fake_token',
            AgencySocialConnection::where('provider_user_id', '12345')->firstOrFail()->access_token
        );
    }

    public function test_meta_callback_handles_oauth_error_gracefully(): void
    {
        $request = Request::create('/callback', 'GET', ['error' => 'access_denied', 'error_description' => 'User denied access']);
        $controller = app(AgencyMetaOAuthController::class);

        $response = $controller->callback($request);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('annullato', session('error'));
    }

    public function test_meta_connection_refresh_action_updates_agency_token(): void
    {
        $action = new RefreshAgencyConnectionAction;
        $connection = AgencySocialConnection::forceCreate(['provider' => 'facebook', 'access_token' => 'old_token']);

        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'new_token', 'expires_in' => 5184000], 200),
        ]);

        $mockSyncAction = Mockery::mock(SyncMetaAssetsAction::class);
        $mockSyncAction->shouldReceive('execute')->once();
        $this->app->instance(SyncMetaAssetsAction::class, $mockSyncAction);

        $result = $action->execute($connection);
        $this->assertTrue($result);

        $connection->refresh();
        $this->assertEquals('new_token', $connection->access_token);
    }

    // --- Meta Sync & Capability ---
    public function test_sync_meta_assets_retrieves_and_saves_pages_and_instagram_accounts(): void
    {
        $action = new SyncMetaAssetsAction;
        $connection = AgencySocialConnection::forceCreate(['provider' => 'facebook', 'access_token' => 'fake_token']);

        Http::fake([
            'graph.facebook.com/*/me/accounts*' => Http::response([
                'data' => [
                    [
                        'id' => 'page123',
                        'name' => 'Test Page',
                        'access_token' => 'page_token_123',
                        'tasks' => ['CREATE_CONTENT', 'MANAGE'],
                        'instagram_business_account' => [
                            'id' => 'ig123',
                            'username' => 'test_ig',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $action->execute($connection);

        $this->assertEquals(2, $result->totalFound);
        $this->assertDatabaseHas('agency_social_assets', [
            'provider_asset_id' => 'page123',
            'asset_type' => SocialAssetType::FacebookPage->value,
        ]);
        $this->assertDatabaseHas('agency_social_assets', [
            'provider_asset_id' => 'ig123',
            'asset_type' => SocialAssetType::InstagramBusinessAccount->value,
        ]);

        $facebookAsset = AgencySocialAsset::where('provider_asset_id', 'page123')->firstOrFail();
        $this->assertSame('page_token_123', $facebookAsset->page_access_token);
        $this->assertArrayNotHasKey('access_token', $facebookAsset->raw_payload);

        $storedAsset = DB::table('agency_social_assets')
            ->where('id', $facebookAsset->id)
            ->first();
        $this->assertStringNotContainsString('page_token_123', (string) $storedAsset->raw_payload);
        $this->assertStringNotContainsString('page_token_123', (string) $storedAsset->page_access_token);
    }

    public function test_sync_meta_assets_rejects_untrusted_pagination_url(): void
    {
        $action = new SyncMetaAssetsAction;
        $connection = AgencySocialConnection::forceCreate([
            'provider' => 'facebook',
            'access_token' => 'fake_token',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => [],
                'paging' => [
                    'next' => 'https://attacker.example/collect?access_token=page_secret',
                ],
            ], 200),
        ]);

        $result = $action->execute($connection);

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('non attendibile', $result->errorMessage);
        $this->assertStringNotContainsString('page_secret', $connection->fresh()->last_api_error);
        Http::assertSentCount(1);
    }

    public function test_resolve_asset_access_token_fails_when_token_is_revoked(): void
    {
        $connection = AgencySocialConnection::forceCreate(['provider' => 'facebook', 'access_token' => 'fake_token', 'status' => AgencyConnectionStatus::Revoked]);
        $fbAsset = AgencySocialAsset::forceCreate([
            'agency_social_connection_id' => $connection->id,
            'provider' => 'meta',
            'platform' => SocialPlatform::Facebook->value,
            'asset_type' => SocialAssetType::FacebookPage->value,
            'provider_asset_id' => 'page123',
            'page_access_token' => 'page_token_123',
            'is_active' => false,
        ]);

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Facebook,
            'agency_social_asset_id' => $fbAsset->id,
        ]);

        $action = new ValidateAgencyAssetAssignmentAction;
        $result = $action->execute(
            $fbAsset,
            $account->client_id,
            SocialPlatform::Facebook->value
        );
        $this->assertTrue($result->isBlocked());
        $this->assertStringContainsString('revocato', strtolower($result->message));
    }

    public function test_meta_publishing_fails_when_asset_is_unassigned(): void
    {
        $post = MarketingCampaignPost::factory()->create();
        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Facebook,
            'agency_social_asset_id' => null, // Unassigned
            'api_status' => SocialApiStatus::Connected,
            'connection_strategy' => SocialConnectionStrategy::AgencyOauth,
        ]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Facebook->value,
        ]);

        $publisher = app(MetaPublisher::class);
        $result = $publisher->publish($publication, $account, Str::uuid()->toString());

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Nessun asset Meta', $result->errorMessage);
    }

    // --- Meta Publishing ---
    public function test_meta_publisher_successfully_posts_an_image(): void
    {
        $post = MarketingCampaignPost::factory()->create();
        $media = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id, 'media_type' => 'image']);

        $connection = AgencySocialConnection::forceCreate(['provider' => 'facebook', 'access_token' => 'fake_token', 'status' => AgencyConnectionStatus::Connected]);
        $fbAsset = AgencySocialAsset::forceCreate([
            'agency_social_connection_id' => $connection->id,
            'provider' => 'meta',
            'platform' => SocialPlatform::Facebook->value,
            'asset_type' => SocialAssetType::FacebookPage->value,
            'provider_asset_id' => 'page123',
            'page_access_token' => 'page_token_123',
            'is_active' => true,
            'facebook_page_id' => 'page123',
            'status' => AgencyConnectionStatus::Connected,
            'publishing_status' => PublishingStatus::Ready,
        ]);

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Facebook,
            'agency_social_asset_id' => $fbAsset->id,
            'api_status' => SocialApiStatus::Connected,
            'connection_strategy' => SocialConnectionStrategy::AgencyOauth,
        ]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Facebook->value,
            'payload_snapshot' => [
                'caption' => 'Test image',
                'hashtags' => [],
                'media' => [[
                    'media_id' => $media->id,
                    'storage_source' => 'local',
                    'disk' => 'public',
                    'path' => 'image.jpg',
                    'mime_type' => 'image/jpeg',
                    'media_type' => 'image',
                    'size_bytes' => 100,
                ]],
                'target' => [
                    'external_id' => 'page123',
                    'page_id' => 'page123',
                    'privacy_options' => [],
                    'publication_type' => 'post',
                ],
            ],
        ]);

        $mockDelivery = Mockery::mock(PublicationMediaDeliveryService::class);
        $mockDelivery->shouldReceive('deliver')->andReturn([
            new PublicationMediaDeliveryResult(
                true,
                'https://agency-core.test/dummy.jpg',
                [],
                'image'
            ),
        ]);
        $this->app->instance(PublicationMediaDeliveryService::class, $mockDelivery);

        Http::fake([
            'graph.facebook.com/*/photos' => Http::response(['id' => 'post_id_123'], 200),
        ]);

        $publisher = app(MetaPublisher::class);
        $result = $publisher->publish($publication, $account, Str::uuid()->toString());

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('post_id_123', $result->externalPostId);
    }

    public function test_meta_publisher_successfully_posts_a_video_reels(): void
    {
        $post = MarketingCampaignPost::factory()->create();
        $media = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id, 'media_type' => 'video']);

        $connection = AgencySocialConnection::forceCreate(['provider' => 'facebook', 'access_token' => 'fake_token', 'status' => AgencyConnectionStatus::Connected]);
        $fbAsset = AgencySocialAsset::forceCreate([
            'agency_social_connection_id' => $connection->id,
            'provider' => 'meta',
            'platform' => SocialPlatform::Facebook->value,
            'asset_type' => SocialAssetType::FacebookPage->value,
            'provider_asset_id' => 'page123',
            'page_access_token' => 'page_token_123',
            'is_active' => true,
            'facebook_page_id' => 'page123',
            'status' => AgencyConnectionStatus::Connected,
            'publishing_status' => PublishingStatus::Ready,
        ]);

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Facebook,
            'agency_social_asset_id' => $fbAsset->id,
            'api_status' => SocialApiStatus::Connected,
            'connection_strategy' => SocialConnectionStrategy::AgencyOauth,
        ]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Facebook->value,
            'payload_snapshot' => [
                'caption' => 'Test reel',
                'hashtags' => [],
                'media' => [[
                    'media_id' => $media->id,
                    'storage_source' => 'local',
                    'disk' => 'public',
                    'path' => 'video.mp4',
                    'mime_type' => 'video/mp4',
                    'media_type' => 'video',
                    'size_bytes' => 100,
                ]],
                'target' => [
                    'external_id' => 'page123',
                    'page_id' => 'page123',
                    'privacy_options' => [],
                    'publication_type' => 'reel',
                ],
            ],
        ]);

        $mockDelivery = Mockery::mock(PublicationMediaDeliveryService::class);
        $mockDelivery->shouldReceive('deliver')->andReturn([
            new PublicationMediaDeliveryResult(
                true,
                'https://agency-core.test/dummy.mp4',
                [],
                'video'
            ),
        ]);
        $this->app->instance(PublicationMediaDeliveryService::class, $mockDelivery);

        Http::fake([
            'graph.facebook.com/*/videos' => Http::response(['id' => 'reel_id_123'], 200),
        ]);

        $publisher = app(MetaPublisher::class);
        $result = $publisher->publish($publication, $account, Str::uuid()->toString());

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('reel_id_123', $result->externalPostId);
    }

    public function test_meta_publisher_handles_provider_failure_and_logs_error(): void
    {
        $post = MarketingCampaignPost::factory()->create();
        $media = MarketingCampaignPostMedia::factory()->create(['marketing_campaign_post_id' => $post->id, 'media_type' => 'image']);

        $connection = AgencySocialConnection::forceCreate(['provider' => 'facebook', 'access_token' => 'fake_token', 'status' => AgencyConnectionStatus::Connected]);
        $fbAsset = AgencySocialAsset::forceCreate([
            'agency_social_connection_id' => $connection->id,
            'provider' => 'meta',
            'platform' => SocialPlatform::Facebook->value,
            'asset_type' => SocialAssetType::FacebookPage->value,
            'provider_asset_id' => 'page123',
            'page_access_token' => 'page_token_123',
            'is_active' => true,
            'facebook_page_id' => 'page123',
            'status' => AgencyConnectionStatus::Connected,
            'publishing_status' => PublishingStatus::Ready,
        ]);

        $account = ClientSocialAccount::factory()->create([
            'platform' => SocialPlatform::Facebook,
            'agency_social_asset_id' => $fbAsset->id,
            'api_status' => SocialApiStatus::Connected,
            'connection_strategy' => SocialConnectionStrategy::AgencyOauth,
        ]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Facebook->value,
            'payload_snapshot' => [
                'caption' => 'Test error',
                'hashtags' => [],
                'media' => [[
                    'media_id' => $media->id,
                    'storage_source' => 'local',
                    'disk' => 'public',
                    'path' => 'image.jpg',
                    'mime_type' => 'image/jpeg',
                    'media_type' => 'image',
                    'size_bytes' => 100,
                ]],
                'target' => [
                    'external_id' => 'page123',
                    'page_id' => 'page123',
                    'privacy_options' => [],
                    'publication_type' => 'post',
                ],
            ],
        ]);

        $mockDelivery = Mockery::mock(PublicationMediaDeliveryService::class);
        $mockDelivery->shouldReceive('deliver')->andReturn([
            new PublicationMediaDeliveryResult(
                true,
                'https://agency-core.test/dummy.jpg',
                [],
                'image'
            ),
        ]);
        $this->app->instance(PublicationMediaDeliveryService::class, $mockDelivery);

        Http::fake([
            'graph.facebook.com/*/photos' => Http::response(['error' => ['message' => 'Graph API error']], 400),
        ]);

        $publisher = app(MetaPublisher::class);
        $result = $publisher->publish($publication, $account, Str::uuid()->toString());

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Graph API error', $result->errorMessage);
    }
}
