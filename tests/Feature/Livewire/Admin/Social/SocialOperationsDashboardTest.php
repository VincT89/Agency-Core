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

    public function test_tiktok_publish_id_is_rendered_from_external_task_id(): void
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
            'status' => PublicationStatus::Failed->value,
            'external_task_id' => 'publish-task-123',
            'correlation_id' => 'tiktok-correlation',
        ]);

        Livewire::actingAs($this->user)
            ->test(SocialOperationsDashboard::class)
            ->assertSee('Publish ID: publish-task-123');
    }
}
