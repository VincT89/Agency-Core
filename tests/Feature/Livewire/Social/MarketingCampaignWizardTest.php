<?php

namespace Tests\Feature\Livewire\Social;

use App\Livewire\Social\MarketingProjects\MarketingProjectCreate;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Enums\UserRole;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarketingCampaignWizardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => UserRole::Marketing]);
        $this->client = Client::factory()->create();
    }

    public function test_can_create_campaign_with_existing_project()
    {
        $project = Project::factory()->create(['client_id' => $this->client->id]);

        Livewire::actingAs($this->user)
            ->test(MarketingProjectCreate::class)
            ->set('client_id', $this->client->id)
            ->set('project_mode', 'existing')
            ->set('project_id', $project->id)
            ->call('nextStep') // Step 2
            ->set('type', 'one_shot')
            ->call('nextStep') // Step 3
            ->set('title', 'Campaign Title')
            ->set('brief', 'Some brief')
            ->set('platforms', ['facebook'])
            ->set('publication_mode', 'manual')
            ->call('nextStep') // Step 5 (since one_shot)
            ->call('save')
            ->assertRedirect();

        $this->assertDatabaseHas('marketing_projects', [
            'client_id' => $this->client->id,
            'project_id' => $project->id,
            'title' => 'Campaign Title',
        ]);
    }

    public function test_can_create_campaign_with_new_project()
    {
        Livewire::actingAs($this->user)
            ->test(MarketingProjectCreate::class)
            ->set('client_id', $this->client->id)
            ->set('project_mode', 'new')
            ->set('new_project_name', 'Nuova Commessa Test')
            ->call('nextStep')
            ->set('type', 'one_shot')
            ->call('nextStep')
            ->set('title', 'Campaign with New Project')
            ->set('brief', 'Test brief')
            ->set('platforms', ['instagram'])
            ->set('publication_mode', 'manual')
            ->call('nextStep')
            ->call('save');

        $this->assertDatabaseHas('projects', [
            'client_id' => $this->client->id,
            'name' => 'Nuova Commessa Test',
        ]);

        $project = Project::where('name', 'Nuova Commessa Test')->first();

        $this->assertDatabaseHas('marketing_projects', [
            'client_id' => $this->client->id,
            'project_id' => $project->id,
            'title' => 'Campaign with New Project',
        ]);
    }

    public function test_cannot_create_campaign_without_project()
    {
        Livewire::actingAs($this->user)
            ->test(MarketingProjectCreate::class)
            ->set('client_id', $this->client->id)
            ->set('project_mode', 'existing')
            ->set('project_id', '') // Vuoto
            ->call('nextStep')
            ->assertHasErrors(['project_id']);
    }
}
