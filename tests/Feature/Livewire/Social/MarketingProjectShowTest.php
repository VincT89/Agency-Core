<?php

namespace Tests\Feature\Livewire\Social;

use App\Livewire\Social\MarketingProjects\MarketingProjectShow;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\MarketingProject;
use App\Enums\UserRole;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarketingProjectShowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Client $client;
    protected MarketingProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => UserRole::Marketing]);
        $this->client = Client::factory()->create();
        
        $project = Project::factory()->create(['client_id' => $this->client->id]);
        
        $this->project = MarketingProject::create([
            'project_id' => $project->id,
            'client_id' => $this->client->id,
            'title' => 'Test Meta Project',
            'type' => \App\Enums\Social\MarketingProjectType::OneShot,
            'status' => \App\Enums\Social\MarketingProjectStatus::Draft,
            'publication_mode' => \App\Enums\Social\PublicationMode::Manual,
            'service_options' => ['platforms' => ['facebook', 'instagram']],
        ]);
    }

    public function test_it_renders_successfully()
    {
        Livewire::actingAs($this->user)
            ->test(MarketingProjectShow::class, ['project' => $this->project])
            ->assertStatus(200);
    }

    public function test_submit_is_blocked_if_meta_is_incomplete()
    {
        // Il client appena creato non ha ClientSocialAccount, quindi Meta è incompleto
        
        Livewire::actingAs($this->user)
            ->test(MarketingProjectShow::class, ['project' => $this->project])
            ->call('submitToN8n')
            ->assertHasErrors(['social_access']);

        $this->assertDatabaseHas('marketing_projects', [
            'id' => $this->project->id,
            'status' => \App\Enums\Social\MarketingProjectStatus::Draft->value,
        ]);
    }
}
