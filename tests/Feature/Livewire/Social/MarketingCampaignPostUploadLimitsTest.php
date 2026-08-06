<?php

namespace Tests\Feature\Livewire\Social;

use App\Enums\UserRole;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostCreate;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingCampaignPostUploadLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_upload_limits_are_visible_in_the_form(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::factory()->create();
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);

        Livewire::actingAs($user)
            ->test(MarketingCampaignPostCreate::class, ['campaign' => $campaign])
            ->assertSee('Massimo 10 media per post')
            ->assertSee('massimo 200 MB per file')
            ->assertSee('Foto: JPG, PNG, WEBP')
            ->assertSee('Video: MP4, WEBM, MOV');
    }
}
