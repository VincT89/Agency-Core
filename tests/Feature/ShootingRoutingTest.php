<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Shooting\Shoot;

class ShootingRoutingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $photographer;
    private User $social;
    private Shoot $shoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->photographer = User::factory()->create(['role' => 'photographer']); // Usiamo fotografo se esiste, o controlleremo isPhotographer
        $this->social = User::factory()->create(['role' => 'marketing']);

        $this->shoot = \App\Models\Shooting\Shoot::factory()->create();
    }

    public function test_legacy_shoots_redirects_correctly_based_on_role()
    {
        // Admin
        $this->actingAs($this->admin)
             ->get('/shoots')
             ->assertRedirect(route('admin.shooting.index'));

        // Photographer
        $this->actingAs($this->photographer)
             ->get('/shoots')
             ->assertRedirect(route('photography.shooting.index'));

        // Social
        $this->actingAs($this->social)
             ->get('/shoots')
             ->assertRedirect(route('social.shooting.index'));
    }

    public function test_legacy_shoot_show_redirects_correctly_based_on_role()
    {
        // Admin (can see all)
        $this->actingAs($this->admin)
             ->get('/shoots/' . $this->shoot->id)
             ->assertRedirect(route('admin.shooting.show', $this->shoot));

        // Photographer (needs to be assigned)
        $this->shoot->update(['photographer_id' => $this->photographer->id]);
        $this->actingAs($this->photographer)
             ->get('/shoots/' . $this->shoot->id)
             ->assertRedirect(route('photography.shooting.show', $this->shoot));

        // Social (needs access to project)
        $project = \App\Models\Project::factory()->create();
        $this->shoot->update(['project_id' => $project->id]);
        $project->users()->attach($this->social->id, ['role' => 'owner']);
        
        $this->actingAs($this->social)
             ->get('/shoots/' . $this->shoot->id)
             ->assertRedirect(route('social.shooting.show', $this->shoot));
    }

    public function test_photographer_cannot_access_social_routes()
    {
        // Social tries to access a photography route
        $response = $this->actingAs($this->social)
             ->get(route('photography.shooting.show', $this->shoot));
             
        $response->assertNotFound();
    }
}
