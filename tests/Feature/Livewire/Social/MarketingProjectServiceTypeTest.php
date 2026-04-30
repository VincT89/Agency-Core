<?php

namespace Tests\Feature\Livewire\Social;

use App\Livewire\Social\MarketingProjects\MarketingProjectCreate;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingProjectServiceTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'marketing']);
        $this->client = Client::factory()->create();
    }

    public function test_validates_service_options_strictly()
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
            ->set('title', 'Test strict validation')
            ->set('brief', 'Some brief')
            ->set('shooting_mode', 'none')
            // Mancano piattaforme e frequenza
            ->call('nextStep')
            ->assertHasErrors(['service_options.platforms', 'service_options.frequency'])
            ->set('service_options.platforms', ['facebook'])
            ->set('service_options.frequency', '2 post')
            ->call('nextStep')
            ->assertHasNoErrors(['service_options.platforms', 'service_options.frequency']);
    }

    public function test_resets_options_on_type_change()
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
            ->set('service_options.platforms', ['facebook'])
            ->set('service_options.frequency', '3 post')
            // Cambiamo servizio
            ->set('service_type', 'ads')
            ->assertSet('service_options', []);
    }

    public function test_syncs_legacy_type_correctly()
    {
        $project = Project::factory()->create(['client_id' => $this->client->id]);
        $project->users()->attach($this->user->id, ['role' => 'manager']);

        Livewire::actingAs($this->user)
            ->test(MarketingProjectCreate::class)
            ->set('client_id', $this->client->id)
            ->set('project_mode', 'existing')
            ->set('project_id', $project->id)
            ->call('nextStep')
            ->set('service_type', 'editorial_plan')
            ->set('campaign_structure', 'plan')
            ->call('nextStep')
            ->set('title', 'Legacy Sync Test')
            ->set('brief', 'briefing')
            ->set('shooting_mode', 'none')
            ->call('nextStep') // to step 4
            ->set('duration_days', 30)
            ->set('start_date', now()->addDays(1)->format('Y-m-d'))
            ->set('end_date', now()->addDays(31)->format('Y-m-d'))
            ->call('addSlot')
            ->set('planSlots.0.date', now()->addDays(5)->format('Y-m-d'))
            ->set('planSlots.0.time', '12:00')
            ->set('planSlots.0.platforms', ['facebook'])
            ->call('nextStep') // to step 5
            ->call('save');

        $this->assertDatabaseHas('marketing_projects', [
            'title' => 'Legacy Sync Test',
            'type' => 'editorial_plan', // Legacy value synced
            'service_type' => 'editorial_plan',
            'campaign_structure' => 'plan',
        ]);
    }
}
