<?php

namespace Tests\Feature\Livewire\Social;

use App\Livewire\Social\PublicationBoard;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\MarketingProject;
use App\Models\SocialPost;
use App\Enums\Social\SocialPostStatus;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\PublicationMode;
use App\Enums\UserRole;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PublicationBoardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Client $client;
    protected Project $project;
    protected MarketingProject $marketingProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => UserRole::Marketing]);
        
        $this->client = Client::factory()->create();
        $this->project = Project::factory()->create(['client_id' => $this->client->id]);
        
        $this->marketingProject = MarketingProject::create([
            'project_id' => $this->project->id,
            'client_id' => $this->client->id,
            'title' => 'Test Marketing Project',
            'type' => \App\Enums\Social\MarketingProjectType::EditorialPlan,
            'status' => \App\Enums\Social\MarketingProjectStatus::Draft,
            'publication_mode' => PublicationMode::Manual,
        ]);
    }

    public function test_it_renders_successfully()
    {
        Livewire::actingAs($this->user)
            ->test(PublicationBoard::class)
            ->assertStatus(200);
    }

    public function test_it_can_mark_a_post_as_published()
    {
        $post = SocialPost::create([
            'project_id' => $this->project->id,
            'client_id' => $this->client->id,
            'marketing_project_id' => $this->marketingProject->id,
            'title' => 'Test Post',
            'status' => SocialPostStatus::ClientApproved,
            'publication_status' => PublicationStatus::Ready,
            'publication_mode' => PublicationMode::Manual,
            'source' => \App\Enums\Social\SocialPostSource::Manual,
        ]);

        Livewire::actingAs($this->user)
            ->test(PublicationBoard::class)
            ->assertSee('Test Post')
            ->call('markAsPublished', $post->id);

        $this->assertDatabaseHas('social_posts', [
            'id' => $post->id,
            'publication_status' => PublicationStatus::Published->value,
        ]);
        
        $this->assertNotNull($post->refresh()->published_at);
    }
}
