<?php

namespace Tests\Feature\Livewire\Social;

use App\Livewire\Social\MarketingProjects\MarketingProjectCreate;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\Shooting\Shoot;
use App\Enums\UserRole;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarketingCampaignShootingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $photographer;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => UserRole::Marketing]);
        $this->photographer = User::factory()->create(['role' => UserRole::Photographer]);
        $this->client = Client::factory()->create();
    }

    public function test_can_create_campaign_without_shooting()
    {
        $project = Project::factory()->create(['client_id' => $this->client->id]);

        Livewire::actingAs($this->user)
            ->test(MarketingProjectCreate::class)
            ->set('client_id', $this->client->id)
            ->set('project_mode', 'existing')
            ->set('project_id', $project->id)
            ->call('nextStep')
            ->set('service_type', 'social_management')
            ->set('campaign_structure', 'one_shot')
            ->call('nextStep')
            ->set('title', 'Campaign No Shoot')
            ->set('brief', 'Some brief')
            ->set('service_options.platforms', ['facebook'])
            ->set('service_options.frequency', '3 post')
            ->set('shooting_mode', 'none')
            ->call('nextStep')
            ->call('save')
            ->assertRedirect();

        $this->assertDatabaseHas('marketing_projects', [
            'client_id' => $this->client->id,
            'title' => 'Campaign No Shoot',
        ]);
        
        $marketingProject = \App\Models\MarketingProject::where('title', 'Campaign No Shoot')->first();
        $this->assertCount(0, $marketingProject->shoots);
    }

    public function test_can_create_campaign_linking_existing_shooting()
    {
        $project = Project::factory()->create(['client_id' => $this->client->id]);
        $project->users()->attach($this->user->id, ['role' => 'manager']);
        
        $shoot = Shoot::create([
            'project_id' => $project->id,
            'photographer_id' => $this->photographer->id,
            'created_by' => $this->user->id,
            'title' => 'Shooting Test',
            'code' => 'SHT-1234',
            'status' => \App\Enums\Shooting\ShootStatus::WaitingPhotographer,
        ]);

        Livewire::actingAs($this->user)
            ->test(MarketingProjectCreate::class)
            ->set('client_id', $this->client->id)
            ->set('project_mode', 'existing')
            ->set('project_id', $project->id)
            ->call('nextStep')
            ->set('service_type', 'social_management')
            ->set('campaign_structure', 'one_shot')
            ->call('nextStep')
            ->set('title', 'Campaign With Existing Shoot')
            ->set('brief', 'Some brief')
            ->set('service_options.platforms', ['facebook'])
            ->set('service_options.frequency', '3 post')
            ->set('shooting_mode', 'existing')
            ->set('existing_shoot_id', $shoot->id)
            ->call('nextStep')
            ->call('save')
            ->assertRedirect();

        $marketingProject = \App\Models\MarketingProject::where('title', 'Campaign With Existing Shoot')->first();
        
        $this->assertDatabaseHas('shoots', [
            'id' => $shoot->id,
            'marketing_project_id' => $marketingProject->id,
        ]);
    }

    public function test_cannot_link_shooting_from_another_project()
    {
        $project1 = Project::factory()->create(['client_id' => $this->client->id]);
        $project1->users()->attach($this->user->id, ['role' => 'manager']);
        $project2 = Project::factory()->create(['client_id' => $this->client->id]);
        $project2->users()->attach($this->user->id, ['role' => 'manager']);
        
        $shootFromOtherProject = Shoot::create([
            'project_id' => $project2->id,
            'photographer_id' => $this->photographer->id,
            'created_by' => $this->user->id,
            'title' => 'Other Shoot',
            'code' => 'SHT-9999',
            'status' => \App\Enums\Shooting\ShootStatus::WaitingPhotographer,
        ]);

        Livewire::actingAs($this->user)
            ->test(MarketingProjectCreate::class)
            ->set('client_id', $this->client->id)
            ->set('project_mode', 'existing')
            ->set('project_id', $project1->id) // Seleziono project1
            ->set('service_type', 'social_management')
            ->set('campaign_structure', 'one_shot')
            ->call('nextStep')
            ->set('title', 'Campaign Wrong Shoot')
            ->set('brief', 'brief')
            ->set('service_options.platforms', ['facebook'])
            ->set('service_options.frequency', '3 post')
            ->set('shooting_mode', 'existing')
            ->set('existing_shoot_id', $shootFromOtherProject->id) // Tento di collegare shoot del project2
            ->call('nextStep')
            ->call('save')
            ->assertHasErrors(['existing_shoot_id']);
            
        $this->assertDatabaseHas('shoots', [
            'id' => $shootFromOtherProject->id,
            'marketing_project_id' => null, // Deve rimanere null
        ]);
    }

    public function test_can_create_campaign_with_new_shooting_request()
    {
        $project = Project::factory()->create(['client_id' => $this->client->id]);
        $project->users()->attach($this->user->id, ['role' => 'manager']);

        Livewire::actingAs($this->user)
            ->test(MarketingProjectCreate::class)
            ->set('client_id', $this->client->id)
            ->set('project_mode', 'existing')
            ->set('project_id', $project->id)
            ->call('nextStep')
            ->set('service_type', 'social_management')
            ->set('campaign_structure', 'one_shot')
            ->call('nextStep')
            ->set('title', 'Campaign New Shoot')
            ->set('brief', 'Some brief')
            ->set('service_options.platforms', ['facebook'])
            ->set('service_options.frequency', '3 post')
            ->set('shooting_mode', 'new')
            ->set('photographer_id', $this->photographer->id)
            ->set('shooting_location', 'Milano Centro')
            ->set('shooting_brief', 'Richiesto video reel in 4k')
            ->set('shooting_proposed_slots', [
                ['date' => '2026-05-15', 'period' => 'morning']
            ])
            ->call('nextStep')
            ->call('save')
            ->assertRedirect();

        $marketingProject = \App\Models\MarketingProject::where('title', 'Campaign New Shoot')->first();
        
        $this->assertDatabaseHas('shoots', [
            'project_id' => $project->id,
            'marketing_project_id' => $marketingProject->id,
            'photographer_id' => $this->photographer->id,
            'location' => 'Milano Centro',
            'internal_notes' => 'Richiesto video reel in 4k',
            'status' => \App\Enums\Shooting\ShootStatus::WaitingPhotographer->value,
        ]);
        
        $shoot = Shoot::where('marketing_project_id', $marketingProject->id)->first();
        $this->assertDatabaseHas('shoot_slots', [
            'shoot_id' => $shoot->id,
            'date' => '2026-05-15 00:00:00',
            'period' => 'morning',
        ]);
    }
}
