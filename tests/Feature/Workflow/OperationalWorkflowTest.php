<?php

namespace Tests\Feature\Workflow;

use App\Models\{Client, Project, Task, Ticket, User};
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_project_supremacy_works_across_board(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $developerAuthed = User::factory()->create(['role' => UserRole::Developer]);
        $developerOut = User::factory()->create(['role' => UserRole::Developer]);

        $client = Client::create(['name' => 'Client Alpha', 'slug' => 'client-alpha', 'status' => 'active']);
        
        $projectAlpha = Project::create(['client_id' => $client->id, 'name' => 'Project Alpha', 'slug' => 'project-alpha', 'status' => 'active']);
        $projectBeta = Project::create(['client_id' => $client->id, 'name' => 'Project Beta', 'slug' => 'project-beta', 'status' => 'active']);

        // Assegno il Developer Authed solo al Progetto Alpha
        $projectAlpha->users()->attach($developerAuthed->id, ['role' => 'dev', 'assignment_status' => 'active', 'assigned_at' => now()]);

        // Crea dati Progetto Alpha
        $taskAlpha = Task::create([
            'project_id' => $projectAlpha->id,
            'created_by' => $admin->id,
            'title' => 'Task on Alpha',
            'status' => 'todo'
        ]);
        
        $ticketAlpha = Ticket::create([
            'client_id' => $client->id,
            'project_id' => $projectAlpha->id,
            'created_by' => $admin->id,
            'title' => 'Ticket on Alpha',
            'status' => 'open'
        ]);

        // Crea dati Progetto Beta
        $taskBeta = Task::create([
            'project_id' => $projectBeta->id,
            'created_by' => $admin->id,
            'assigned_to' => $developerAuthed->id, // Assegnazione DECOY: non deve poter bypassare il Project Supremacy
            'title' => 'Task on Beta',
            'status' => 'todo'
        ]);

        // Test listato: $developerAuthed naviga task list
        $response = $this->actingAs($developerAuthed)->get(route('tasks.index'));
        $response->assertOk();
        
        $response->assertViewHas('taskList', function ($tasks) use ($taskAlpha, $taskBeta) {
            return $tasks->contains($taskAlpha) && !$tasks->contains($taskBeta);
        });

        // Test dettaglio: $developerAuthed naviga ticket Alpha
        $response = $this->actingAs($developerAuthed)->get(route('tickets.show', $ticketAlpha));
        $response->assertOk();

        // Developer Out testa l'accesso al task Alpha
        $responseOut = $this->actingAs($developerOut)->get(route('tasks.show', $taskAlpha));
        $responseOut->assertNotFound();
    }
}
