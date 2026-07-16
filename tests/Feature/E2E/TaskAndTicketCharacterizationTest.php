<?php

namespace Tests\Feature\E2E;

use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAndTicketCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_task_and_ticket_within_project()
    {
        $this->withoutExceptionHandling();
        
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        
        $project->users()->attach($admin->id, [
            'role' => 'sponsor',
            'assignment_status' => 'active',
            'assigned_at' => now(),
        ]);

        $responseTask = $this->actingAs($admin)->post('/tasks', [
            'project_id' => $project->id,
            'title' => 'Test Task',
            'description' => 'Test Task Description',
            'status' => 'todo',
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'priority' => 'medium',
            'assigned_to' => $admin->id,
        ]);

        $responseTask->assertSessionHasNoErrors();
        $responseTask->assertRedirect();
        
        $task = Task::where('title', 'Test Task')->first();
        $this->assertNotNull($task);

        $responseTicket = $this->actingAs($admin)->post('/tickets', [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Test Ticket',
            'description' => 'Test Ticket Description',
            'type' => 'bug',
            'status' => 'open',
            'priority' => 'high',
            'assigned_to' => $admin->id,
        ]);

        $responseTicket->assertSessionHasNoErrors();
        $responseTicket->assertRedirect();
        
        $ticket = Ticket::where('title', 'Test Ticket')->first();
        $this->assertNotNull($ticket);
    }
}
