<?php

namespace Tests\Feature\Livewire\Admin\Social;

use App\Domain\Social\Actions\RetryMarketingCampaignPostPublicationAction;
use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use App\Enums\UserRole;
use App\Jobs\Social\ExecuteMarketingCampaignPostPublicationJob;
use App\Livewire\Admin\Social\SocialOperationsDashboard;
use App\Models\Client;
use App\Models\ClientSocialAccount;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

class SocialOperationsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::Admin->value,
        ]);
    }

    public function test_all_filter_includes_active_and_published_publications(): void
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::create([
            'client_id' => $client->id,
            'name' => 'Operations Campaign',
            'status' => 'active',
        ]);
        $post = MarketingCampaignPost::create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Approved->value,
        ]);

        $pending = MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => 'facebook',
            'status' => PublicationStatus::Pending->value,
            'correlation_id' => 'pending-publication',
        ]);
        $published = MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => 'facebook',
            'status' => PublicationStatus::Published->value,
            'correlation_id' => 'published-publication',
            'external_permalink' => 'https://www.facebook.com/example/posts/1',
        ]);
        MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => 'facebook',
            'status' => PublicationStatus::Published->value,
            'correlation_id' => 'historical-published-publication',
            'external_post_id' => 'page-123_post-456',
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(SocialOperationsDashboard::class)
            ->assertSee("#{$pending->id}", false)
            ->assertSee("#{$published->id}", false)
            ->assertSee('https://www.facebook.com/page-123/posts/post-456', false)
            ->assertSee('Dettagli post')
            ->assertSee('Apri sul social')
            ->assertSeeHtml('class="social-operation-filters"')
            ->assertSeeHtml('aria-pressed="true"')
            ->assertSeeHtml('class="t-table u-w-full social-operations-table"')
            ->assertSeeHtml('data-label="Azioni"');

        $responsiveCss = file_get_contents(resource_path('css/sodano/_social.css'));
        $this->assertStringContainsString('.table-responsive.social-operations-table-container > .t-table.social-operations-table thead tr', $responsiveCss);
        $this->assertStringContainsString('max-width: 1px;', $responsiveCss);

        $component
            ->set('filter', 'active')
            ->assertSee("#{$pending->id}", false)
            ->assertDontSee("#{$published->id}", false)
            ->set('filter', 'published')
            ->assertDontSee("#{$pending->id}", false)
            ->assertSee("#{$published->id}", false);
    }

    public function test_retry_ig_manual_review_creates_new_publication()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::create([
            'client_id' => $client->id,
            'name' => 'Test Campaign',
            'status' => 'active',
        ]);

        $post = MarketingCampaignPost::create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Failed->value,
        ]);

        $socialAccount = ClientSocialAccount::create([
            'client_id' => $client->id,
            'platform' => 'instagram',
            'account_name' => 'test_ig',
            'account_exists' => true,
        ]);

        $publication = MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $post->id,
            'client_social_account_id' => $socialAccount->id,
            'platform' => 'instagram',
            'status' => PublicationStatus::NeedsManualReview->value,
            'correlation_id' => 'abc',
        ]);

        $this->mock(RetryMarketingCampaignPostPublicationAction::class, function (MockInterface $mock) use ($publication) {
            $mock->shouldReceive('execute')->once()->andReturn($publication);
        });

        Queue::fake();

        $this->mock(SyncMarketingCampaignPostPublicationStatusAction::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')->once();
        });

        Livewire::actingAs($this->user)
            ->test(SocialOperationsDashboard::class)
            ->call('retryPublication', $publication->id);

        Queue::assertPushed(ExecuteMarketingCampaignPostPublicationJob::class);
    }

    public function test_tiktok_public_permalink_and_instagram_recovery_are_rendered(): void
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create([
            'client_id' => $client->id,
        ]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
        ]);
        $tiktokAccount = ClientSocialAccount::factory()->create([
            'client_id' => $client->id,
            'platform' => SocialPlatform::Tiktok,
            'api_metadata' => [
                'content_posting_info' => [
                    'creator_username' => 'test.r4',
                ],
            ],
        ]);
        MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'client_social_account_id' => $tiktokAccount->id,
            'platform' => SocialPlatform::Tiktok,
            'status' => PublicationStatus::Published,
            'external_post_id' => null,
            'provider_last_response' => [
                'response_data' => [
                    'data' => [
                        'publicaly_available_post_id' => ['7391234567890123456'],
                    ],
                ],
            ],
        ]);
        $instagramPublication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => SocialPlatform::Instagram,
            'status' => PublicationStatus::Published,
            'external_post_id' => 'ig-media-123',
            'external_permalink' => null,
        ]);

        Livewire::actingAs($this->user)
            ->test(SocialOperationsDashboard::class)
            ->assertSee(
                'https://www.tiktok.com/@test.r4/video/7391234567890123456',
                false
            )
            ->assertSeeHtml('class="btn btn-p btn-xs social-operation-external-link"')
            ->assertSeeHtml("wire:click=\"recoverPermalink({$instagramPublication->id})\"")
            ->assertSee('Recupera collegamento');
    }

    public function test_instagram_permalink_can_be_recovered_for_a_published_row(): void
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create([
            'client_id' => $client->id,
        ]);
        $post = MarketingCampaignPost::factory()->create([
            'marketing_campaign_id' => $campaign->id,
        ]);
        $account = ClientSocialAccount::factory()->create([
            'client_id' => $client->id,
            'platform' => SocialPlatform::Instagram,
            'access_token' => 'instagram-token',
        ]);
        $publication = MarketingCampaignPostPublication::factory()->create([
            'marketing_campaign_post_id' => $post->id,
            'client_social_account_id' => $account->id,
            'platform' => SocialPlatform::Instagram,
            'status' => PublicationStatus::Published,
            'external_post_id' => 'ig-media-123',
            'external_permalink' => null,
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => 'ig-media-123',
                'permalink' => 'https://www.instagram.com/p/example123/',
            ]),
        ]);

        Livewire::actingAs($this->user)
            ->test(SocialOperationsDashboard::class)
            ->call('recoverPermalink', $publication->id)
            ->assertSee('https://www.instagram.com/p/example123/', false);

        $this->assertSame(
            'https://www.instagram.com/p/example123/',
            $publication->fresh()->external_permalink
        );
    }

    public function test_refresh_container_blocks_terminal_state()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::create([
            'client_id' => $client->id,
            'name' => 'Test Campaign',
            'status' => 'active',
        ]);

        $post = MarketingCampaignPost::create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Failed->value,
        ]);

        $socialAccount = ClientSocialAccount::create([
            'client_id' => $client->id,
            'platform' => 'instagram',
            'account_name' => 'test_ig',
            'account_exists' => true,
        ]);

        $publication = MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $post->id,
            'client_social_account_id' => $socialAccount->id,
            'platform' => 'instagram',
            'status' => PublicationStatus::Failed->value,
            'external_container_id' => '12345',
            'correlation_id' => 'abc',
        ]);

        Livewire::actingAs($this->user)
            ->test(SocialOperationsDashboard::class)
            ->call('refreshPublication', $publication->id);

        $this->assertEquals(PublicationStatus::Failed->value, $publication->refresh()->status->value);
    }

    public function test_force_fail_publication_blocks_terminal_state()
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::create([
            'client_id' => $client->id,
            'name' => 'Test Campaign',
            'status' => 'active',
        ]);

        $post = MarketingCampaignPost::create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Cancelled->value,
        ]);

        $socialAccount = ClientSocialAccount::create([
            'client_id' => $client->id,
            'platform' => 'instagram',
            'account_name' => 'test_ig',
            'account_exists' => true,
        ]);

        $publication = MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $post->id,
            'client_social_account_id' => $socialAccount->id,
            'platform' => 'instagram',
            'status' => PublicationStatus::Superseded->value,
            'external_container_id' => '12345',
            'correlation_id' => 'abc',
        ]);

        Livewire::actingAs($this->user)
            ->test(SocialOperationsDashboard::class)
            ->call('forceFailPublication', $publication->id);

        $this->assertEquals(PublicationStatus::Superseded->value, $publication->refresh()->status->value);
    }

    public function test_tiktok_diagnostic_is_visible_without_exposing_publish_id_or_secrets(): void
    {
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::create([
            'client_id' => $client->id,
            'name' => 'TikTok Campaign',
            'status' => 'active',
        ]);
        $post = MarketingCampaignPost::create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Failed->value,
        ]);
        $socialAccount = ClientSocialAccount::create([
            'client_id' => $client->id,
            'platform' => 'tiktok',
            'account_name' => 'test_tiktok',
            'account_exists' => true,
        ]);
        MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $post->id,
            'client_social_account_id' => $socialAccount->id,
            'platform' => 'tiktok',
            'status' => PublicationStatus::NeedsManualReview->value,
            'external_task_id' => 'publish-task-123',
            'correlation_id' => 'tiktok-correlation',
            'error_message' => 'TikTok Fetch Status fallito: token scaduto. access_token=secret-token',
            'provider_last_response' => [
                'status' => 'API_ERROR',
                'http_status' => 401,
                'request_id' => 'safe-request-reference-123',
                'response_data' => [
                    'error' => [
                        'code' => 'access_token_invalid',
                        'message' => 'Expired',
                    ],
                    'access_token' => 'secret-token',
                ],
            ],
        ]);

        Livewire::actingAs($this->user)
            ->test(SocialOperationsDashboard::class)
            ->assertSee('TikTok Fetch Status fallito: token scaduto. access_token=[REDACTED]', false)
            ->assertSee('Accettata da TikTok')
            ->assertSee('Errore API durante il controllo')
            ->assertSee('API_ERROR')
            ->assertSee('access_token_invalid')
            ->assertSee('safe-request-reference-123')
            ->assertDontSee('publish-task-123')
            ->assertDontSee('secret-token');
    }
}
