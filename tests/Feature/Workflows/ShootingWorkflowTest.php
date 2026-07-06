<?php

namespace Tests\Feature\Workflows;

use Tests\TestCase;

class ShootingWorkflowTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    
    public function test_shooting_full_e2e_workflow(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $photographer = \App\Models\User::factory()->create(['role' => 'photographer']);
        $marketing = \App\Models\User::factory()->create(['role' => 'marketing']);
        
        $client = \App\Models\Client::factory()->create();
        $project = \App\Models\Project::factory()->create(['client_id' => $client->id]);

        $this->actingAs($admin);

        // 1. creazione richiesta shooting
        $shoot = \App\Models\Shooting\Shoot::create([
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'title' => 'Shooting Prodotti',
            'code' => 'SHT-001',
            'location' => 'Studio',
            'status' => \App\Enums\Shooting\ShootStatus::Draft,
        ]);
        
        $this->assertEquals(\App\Enums\Shooting\ShootStatus::Draft, $shoot->status);

        // 2. assegnazione fotografo
        $shoot->update([
            'photographer_id' => $photographer->id,
            'status' => \App\Enums\Shooting\ShootStatus::WaitingPhotographer,
        ]);
        
        $this->assertEquals(\App\Enums\Shooting\ShootStatus::WaitingPhotographer, $shoot->fresh()->status);

        // 3. fotografo accetta/rifiuta
        $this->actingAs($photographer);
        $shoot->update([
            'status' => \App\Enums\Shooting\ShootStatus::WaitingClient,
        ]);
        $this->assertEquals(\App\Enums\Shooting\ShootStatus::WaitingClient, $shoot->fresh()->status);

        // 4. cliente conferma
        $this->actingAs($admin);
        $shoot->update([
            'status' => \App\Enums\Shooting\ShootStatus::ClientConfirmed,
            'client_confirmation_status' => 'confirmed',
            'client_confirmed_at' => now(),
        ]);
        
        $shoot->update([
            'status' => \App\Enums\Shooting\ShootStatus::Scheduled,
        ]);
        $this->assertEquals(\App\Enums\Shooting\ShootStatus::Scheduled, $shoot->fresh()->status);

        // 5. policy: admin vede tutto
        $this->assertTrue($admin->can('view', $shoot));

        // 6. policy: fotografo vede solo i propri
        $this->assertTrue($photographer->can('view', $shoot));
        
        $otherPhotographer = \App\Models\User::factory()->create(['role' => 'photographer']);
        $this->assertFalse($otherPhotographer->can('view', $shoot));

        // 7. policy: marketing vede solo quelli autorizzati
        $this->assertFalse($marketing->can('view', $shoot));
        $project->users()->attach($marketing, ['role' => 'marketing']);
        $this->assertTrue($marketing->fresh()->can('view', $shoot->fresh()));

        // 8. redirect legacy /shoots
        $response = $this->actingAs($admin)->get('/shoots');
        $response->assertRedirect('/admin/shooting');

        // 9. collegamento con campagna marketing
        $campaign = \App\Models\MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $shoot->update([
            'marketing_campaign_id' => $campaign->id,
        ]);
        $this->assertEquals($campaign->id, $shoot->fresh()->marketing_campaign_id);
    }
}
