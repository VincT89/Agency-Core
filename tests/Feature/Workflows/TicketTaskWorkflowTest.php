<?php

namespace Tests\Feature\Workflows;

use Tests\TestCase;

class TicketTaskWorkflowTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    
    public function test_ticket_and_task_full_e2e_workflow(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $developer = \App\Models\User::factory()->create(['role' => 'developer']);
        $client = \App\Models\Client::factory()->create();
        $project = \App\Models\Project::factory()->create(['client_id' => $client->id]);
        
        $project->users()->attach($developer, ['role' => 'developer']);

        // 1. ticket manuale
        $ticket = \App\Models\Ticket::factory()->create([
            'project_id' => $project->id,
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'title' => 'Test Ticket',
            'status' => 'open',
            'type' => 'request',
        ]);
        
        $this->assertEquals('open', $ticket->status);

        // 2. ticket da n8n (simulato via factory o endpoint, useremo factory)
        $n8nTicket = \App\Models\Ticket::factory()->create([
            'project_id' => $project->id,
            'client_id' => $client->id,
            'title' => 'N8N Ticket',
            'source' => 'n8n',
            'status' => 'open',
        ]);
        $this->assertEquals('n8n', $n8nTicket->source);

        // 3. assegnazione ticket
        $ticket->update(['assigned_to' => $developer->id]);
        $this->assertEquals($developer->id, $ticket->assigned_to);

        // 4. commento ticket
        $comment = $ticket->comments()->create([
            'user_id' => $developer->id,
            'body' => 'Inizio analisi',
        ]);
        $this->assertCount(1, $ticket->comments);

        // 5. checklist ticket
        $checklistItem = $ticket->checklistItems()->create([
            'title' => 'Verifica log',
            'is_completed' => false,
        ]);
        $checklistItem->update(['is_completed' => true]);
        $this->assertTrue($checklistItem->fresh()->is_completed);

        // 6. cambio stato ticket
        $ticket->update(['status' => 'in_progress']);
        $this->assertEquals('in_progress', $ticket->status);

        // 7. task collegato a ticket
        $task = \App\Models\Task::factory()->create([
            'project_id' => $project->id,
            'ticket_id' => $ticket->id,
            'created_by' => $developer->id,
            'assigned_to' => $developer->id,
            'status' => 'todo',
        ]);
        $this->assertEquals($ticket->id, $task->ticket_id);

        // 8. task checklist
        $task->comments()->create([
            'user_id' => $developer->id,
            'body' => 'Task avviato',
        ]);

        $task->update(['status' => 'done']);
        
        // 9. chiusura ticket
        $ticket->update(['status' => 'resolved']);
        $this->assertEquals('resolved', $ticket->status);
    }
}
