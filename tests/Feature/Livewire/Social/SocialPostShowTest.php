<?php

namespace Tests\Feature\Livewire\Social;

use App\Livewire\Social\Posts\SocialPostShow;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\MarketingProject;
use App\Models\SocialPost;
use App\Enums\Social\SocialPostStatus;
use App\Enums\UserRole;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SocialPostShowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected SocialPost $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => UserRole::Marketing]);
        
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        
        $marketingProject = MarketingProject::create([
            'project_id' => $project->id,
            'client_id' => $client->id,
            'title' => 'Test Marketing Project',
            'type' => \App\Enums\Social\MarketingProjectType::OneShot,
            'status' => \App\Enums\Social\MarketingProjectStatus::Draft,
            'publication_mode' => \App\Enums\Social\PublicationMode::Manual,
        ]);

        $this->post = SocialPost::create([
            'project_id' => $project->id,
            'client_id' => $client->id,
            'marketing_project_id' => $marketingProject->id,
            'title' => 'Test Post',
            'status' => SocialPostStatus::InternalReview,
            'source' => \App\Enums\Social\SocialPostSource::Manual,
        ]);
    }

    public function test_it_renders_successfully()
    {
        Livewire::actingAs($this->user)
            ->test(SocialPostShow::class, ['post' => $this->post])
            ->assertStatus(200);
    }

    public function test_it_can_mark_post_as_ready_for_client()
    {
        Livewire::actingAs($this->user)
            ->test(SocialPostShow::class, ['post' => $this->post])
            ->call('markAsReady');

        $this->assertDatabaseHas('social_posts', [
            'id' => $this->post->id,
            'status' => SocialPostStatus::ReadyForClient->value,
        ]);
    }
}
