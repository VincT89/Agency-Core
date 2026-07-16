<?php

namespace Tests\Feature\E2E;

use App\Models\User;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CampaignAndPostCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_campaign_and_post()
    {
        $this->withoutExceptionHandling();
        
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $client = Client::factory()->create();

        // 1. Create Campaign via Livewire
        Livewire::actingAs($admin)
            ->test(\App\Livewire\Social\MarketingCampaigns\MarketingCampaignCreate::class)
            ->set('client_id', $client->id)
            ->set('name', 'Test Campaign')
            ->set('starts_at', now()->format('Y-m-d'))
            ->set('ends_at', now()->addMonth()->format('Y-m-d'))
            ->set('monthly_fee', 500)
            ->call('save')
            ->assertRedirect();
            
        $campaign = MarketingCampaign::where('name', 'Test Campaign')->first();
        $this->assertNotNull($campaign);

        // 2. Create Post via Livewire
        Livewire::actingAs($admin)
            ->test(\App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostCreate::class, ['campaign' => $campaign])
            ->set('form.content_type', 'post')
            ->set('form.status', 'draft')
            ->set('form.publishing_platforms', ['instagram', 'facebook'])
            ->set('form.scheduled_date', now()->addDays(2)->format('Y-m-d'))
            ->set('form.scheduled_time', '10:00')
            ->set('form.description', 'Test notes')
            ->call('save')
            ->assertRedirect();
            
        $post = MarketingCampaignPost::where('marketing_campaign_id', $campaign->id)->first();
        $this->assertNotNull($post);
    }
}
