<?php

namespace Tests\Feature\Livewire\Admin\Social;

use App\Domain\Social\Actions\RetryMarketingCampaignPostPublicationAction;
use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\PublicationStatus;
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

        $component = Livewire::actingAs($this->user)
            ->test(SocialOperationsDashboard::class)
            ->assertSee("#{$pending->id}", false)
            ->assertSee("#{$published->id}", false)
            ->assertSee('Dettagli post')
            ->assertSee('Apri sul social')
            ->assertSeeHtml('class="social-operation-filters"')
            ->assertSeeHtml('aria-pressed="true"')
            ->assertSeeHtml('class="t-table u-w-full social-operations-table"')
            ->assertSeeHtml('data-label="Azioni"');

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
