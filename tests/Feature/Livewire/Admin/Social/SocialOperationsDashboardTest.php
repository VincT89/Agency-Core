<?php

namespace Tests\Feature\Livewire\Admin\Social;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\PublicationStatus;
use App\Livewire\Admin\Social\SocialOperationsDashboard;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Domain\Social\Actions\PublishMarketingCampaignPostAction;
use App\Domain\Social\Actions\SyncMarketingCampaignPostPublicationStatusAction;
use Mockery\MockInterface;

class SocialOperationsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'role' => \App\Enums\UserRole::Admin->value
        ]);
    }

    public function test_retry_ig_manual_review_creates_new_publication()
    {
        $client = \App\Models\Client::factory()->create();
        $campaign = \App\Models\MarketingCampaign::create([
            'client_id' => $client->id,
            'name' => 'Test Campaign',
            'status' => 'active'
        ]);
        
        $post = MarketingCampaignPost::create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::NeedsManualReview->value,
        ]);
        
        $socialAccount = \App\Models\ClientSocialAccount::create([
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

        $this->mock(PublishMarketingCampaignPostAction::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')->once();
        });
        
        $this->mock(SyncMarketingCampaignPostPublicationStatusAction::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')->once();
        });

        Livewire::actingAs($this->user)
            ->test(SocialOperationsDashboard::class)
            ->call('retryPublication', $publication->id);

        $publication->refresh();
        $this->assertEquals(PublicationStatus::Superseded->value, $publication->status->value);
        $this->assertEquals('Dismesso (sostituito da nuovo tentativo)', $publication->error_message);
    }

    public function test_refresh_container_blocks_terminal_state()
    {
        $client = \App\Models\Client::factory()->create();
        $campaign = \App\Models\MarketingCampaign::create([
            'client_id' => $client->id,
            'name' => 'Test Campaign',
            'status' => 'active'
        ]);
        
        $post = MarketingCampaignPost::create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Failed->value,
        ]);
        
        $socialAccount = \App\Models\ClientSocialAccount::create([
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
            ->call('refreshContainer', $publication->id);
            
        $this->assertEquals(PublicationStatus::Failed->value, $publication->refresh()->status->value);
    }

    public function test_force_fail_publication_blocks_terminal_state()
    {
        $client = \App\Models\Client::factory()->create();
        $campaign = \App\Models\MarketingCampaign::create([
            'client_id' => $client->id,
            'name' => 'Test Campaign',
            'status' => 'active'
        ]);
        
        $post = MarketingCampaignPost::create([
            'marketing_campaign_id' => $campaign->id,
            'status' => MarketingCampaignPostStatus::Superseded->value,
        ]);
        
        $socialAccount = \App\Models\ClientSocialAccount::create([
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
            ->call('forceFailPublication', $publication->id)
            ->assertSessionHas('error'); // Ensure error flash message is set
            
        $this->assertEquals(PublicationStatus::Superseded->value, $publication->refresh()->status->value);
    }
}
