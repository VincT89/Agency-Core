<?php

namespace Tests\Feature\E2E;

use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAndMemberAssignmentCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_project_and_assign_members()
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $client = Client::factory()->create();
        
        $member1 = User::factory()->create(['role' => UserRole::Developer, 'status' => 'active']);
        $member2 = User::factory()->create(['role' => UserRole::GraphicDesigner, 'status' => 'active']);

        $this->withoutExceptionHandling();
        $response = $this->actingAs($admin)->post('/projects', [
            'name' => 'Test Project',
            'client_id' => $client->id,
            'code' => 'PRJ-123',
            'status' => 'active',
            'members' => [$member1->id, $member2->id],
            'roles' => [
                $member1->id => 'member',
                $member2->id => 'lead'
            ]
        ]);

        $response->assertSessionHasNoErrors();

        $project = Project::where('name', 'Test Project')->first();
        $response->assertRedirect(route('projects.show', $project));
        
        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project',
            'client_id' => $client->id,
            'code' => 'PRJ-123'
        ]);

        // Verify members were attached, plus admin as sponsor
        $this->assertEquals(3, $project->users()->count());
        
        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $member1->id,
            'role' => 'member',
            'assignment_status' => 'active'
        ]);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $admin->id,
            'role' => 'sponsor'
        ]);
    }
}
