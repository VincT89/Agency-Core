<?php

namespace Tests\Feature\E2E;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSlugRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_creation_generates_a_unique_slug_without_soft_deletes(): void
    {
        $this->withoutExceptionHandling();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $client = Client::factory()->create();

        // Primo progetto
        $response1 = $this->actingAs($admin)->post(route('projects.store'), [
            'client_id' => $client->id,
            'name' => 'Progetto Alfa',
            'status' => 'active',
            'members' => [$admin->id],
            'roles' => [$admin->id => 'sponsor'],
        ]);
        
        $response1->assertRedirect();
        
        $project1 = Project::orderBy('id', 'desc')->first();
        $this->assertEquals('progetto-alfa', $project1->slug);

        // Secondo progetto con lo stesso nome
        $response2 = $this->actingAs($admin)->post(route('projects.store'), [
            'client_id' => $client->id,
            'name' => 'Progetto Alfa',
            'status' => 'active',
            'members' => [$admin->id],
            'roles' => [$admin->id => 'sponsor'],
        ]);

        $response2->assertRedirect();

        // Verifica assenza di errore e slug differenti.
        $project2 = Project::orderBy('id', 'desc')->first();
        
        $this->assertNotEquals($project1->slug, $project2->slug);
        $this->assertStringStartsWith('progetto-alfa-', $project2->slug);
    }
}
