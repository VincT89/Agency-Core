<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Client;
use App\Domain\Core\Queries\TaskQuery;
use App\Enums\UserRole;

class QueryObjectSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_query_object_respects_global_scope()
    {
        // Client
        $client = Client::create(['name' => 'Test', 'slug' => 'test-client', 'status' => 'active']);

        // Operatore (Developer limitato)
        $user = User::factory()->create(['role' => UserRole::Developer, 'status' => 'active']);

        // Progetto A (Assegnato all'utente)
        $projectAssigned = Project::create(['client_id' => $client->id, 'name' => 'Project A', 'slug' => 'project-a', 'status' => 'active']);
        $projectAssigned->users()->attach($user->id, ['role' => 'developer']);

        // Progetto B (Non assegnato)
        $projectUnassigned = Project::create(['client_id' => $client->id, 'name' => 'Project B', 'slug' => 'project-b', 'status' => 'active']);

        // Crea una task su Progetto A
        Task::create([
            'project_id' => $projectAssigned->id,
            'title' => 'Task Visibile',
            'created_by' => $user->id,
            'status' => 'todo',
        ]);

        // Crea una task su Progetto B
        Task::create([
            'project_id' => $projectUnassigned->id,
            'title' => 'Task Nascosta',
            'created_by' => $user->id,
            'status' => 'todo',
        ]);

        // Esegui come utebte
        $this->actingAs($user);

        // Usiamo il Query Object (che non deve rompere i global scopes caricando tutto)
        $queryObject = new TaskQuery();
        $tasks = $queryObject->forIndex([])->get();

        $this->assertCount(1, $tasks);
        $this->assertEquals('Task Visibile', $tasks->first()->title);
    }
}
