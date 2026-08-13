<?php

namespace Tests\Feature\Authorization;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\HostingService;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Enums\UserRole;

class TutorRoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    public static function roleMatrixProvider(): array
    {
        return [
            // Clienti
            ['get', '/clients', UserRole::OperationsManager, 200],
            ['post', '/clients', UserRole::OperationsManager, 302], // Redirect on success or 422 if invalid
            
            // Progetti
            ['get', '/projects', UserRole::OperationsManager, 200],
            ['post', '/projects', UserRole::OperationsManager, 302],
            
            // Task
            ['get', '/tasks', UserRole::OperationsManager, 200],
            ['post', '/tasks', UserRole::OperationsManager, 302],
            
            // Ticket
            ['get', '/tickets', UserRole::OperationsManager, 200],
            ['post', '/tickets', UserRole::OperationsManager, 302],
            
            // Calendario
            ['get', '/calendar-events', UserRole::OperationsManager, 200],
            ['post', '/calendar-events', UserRole::OperationsManager, 302],
            
            // Domini/Hosting
            ['get', '/hosting-services', UserRole::OperationsManager, 200],
            ['post', '/hosting-services', UserRole::OperationsManager, 302],
            
            // Finanza
            ['get', '/invoices', UserRole::OperationsManager, 403],
            ['get', '/payments', UserRole::OperationsManager, 403],
            ['get', '/expenses', UserRole::OperationsManager, 403],
            
            // System/Admin
            ['get', '/users', UserRole::OperationsManager, 403],
            ['get', '/admin/social/connections', UserRole::OperationsManager, 403],
            ['get', '/admin/social/operations', UserRole::OperationsManager, 403],
        ];
    }

    #[DataProvider('roleMatrixProvider')]
    public function test_operations_manager_role_matrix(string $method, string $uri, UserRole $role, int $expectedStatus)
    {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->actingAs($user)->{$method}($uri);

        if ($expectedStatus === 302) {
            // For POST requests to store endpoints, we might get 302 validation errors because we send empty data.
            // This is expected and means the request passed authorization.
            $response->assertStatus(302);
            // 403 would mean unauthorized.
            $this->assertNotEquals(403, $response->status(), "User was unauthorized for $method $uri");
        } else {
            // Se la route /social/admin non esiste potrebbe dare 404
            if ($expectedStatus === 404 && $response->status() === 403) {
                 $response->assertStatus(403);
            } else {
                 $response->assertStatus($expectedStatus);
            }
        }
    }

    public function test_operations_manager_cannot_delete_client()
    {
        $user = User::factory()->create(['role' => UserRole::OperationsManager]);
        $client = Client::factory()->create();

        $response = $this->actingAs($user)->delete("/clients/{$client->id}");
        $response->assertStatus(403);
    }
    
    public function test_operations_manager_cannot_delete_task()
    {
        $user = User::factory()->create(['role' => UserRole::OperationsManager]);
        $task = Task::factory()->create();

        $response = $this->actingAs($user)->delete("/tasks/{$task->id}");
        $response->assertStatus(403);
    }

    public function test_operations_manager_sees_global_projects_but_cannot_access_finance()
    {
        $user = User::factory()->create(['role' => UserRole::OperationsManager]);
        
        $this->assertTrue($user->canBypassProjectScope());
        $this->assertTrue($user->canAccessAllProjects());
        $this->assertFalse($user->canAccessFinance());
    }

    public function test_ticket_creation_requires_project_id()
    {
        $user = User::factory()->create(['role' => UserRole::OperationsManager]);
        $client = Client::factory()->create();

        $response = $this->actingAs($user)->post('/tickets', [
            'client_id' => $client->id,
            'title' => 'Test',
            'type' => Ticket::TYPES[0],
            'status' => Ticket::STATUSES[0],
            'priority' => Ticket::PRIORITIES[0],
        ]);

        $response->assertSessionHasErrors(['project_id']);
    }

    public function test_ticket_creation_fails_if_project_not_belonging_to_client()
    {
        $user = User::factory()->create(['role' => UserRole::OperationsManager]);
        
        $client1 = Client::factory()->create();
        $project1 = Project::factory()->create(['client_id' => $client1->id]);
        
        $client2 = Client::factory()->create();

        $response = $this->actingAs($user)->post('/tickets', [
            'client_id' => $client2->id,
            'project_id' => $project1->id,
            'title' => 'Test',
            'type' => Ticket::TYPES[0],
            'status' => Ticket::STATUSES[0],
            'priority' => Ticket::PRIORITIES[0],
        ]);

        $response->assertSessionHasErrors(['project_id']);
    }

    public function test_personal_calendar_event_privacy()
    {
        $user1 = User::factory()->create(['role' => UserRole::Developer]);
        $event = CalendarEvent::create([
            'title' => 'Test',
            'type' => 'personal',
            'status' => 'scheduled',
            'start_at' => now(),
            'end_at' => now()->addHour(),
            'created_by' => $user1->id,
            'assigned_to' => $user1->id,
        ]);

        $operationsManager = User::factory()->create(['role' => UserRole::OperationsManager]);

        // Non può visualizzare l'evento personale di un altro
        $response = $this->actingAs($operationsManager)->get("/calendar-events/{$event->id}");
        $response->assertStatus(403);

        // Può invece visualizzare un evento operativo
        $operationalEvent = CalendarEvent::create([
            'title' => 'Test',
            'type' => 'other',
            'status' => 'scheduled',
            'start_at' => now(),
            'end_at' => now()->addHour(),
            'created_by' => $user1->id,
            'assigned_to' => $user1->id,
        ]);
        
        $response2 = $this->actingAs($operationsManager)->get("/calendar-events/{$operationalEvent->id}");
        $response2->assertStatus(200);
    }
    
    public function test_hosting_service_default_status()
    {
        $user = User::factory()->create(['role' => UserRole::OperationsManager]);
        $client = Client::factory()->create();

        $response = $this->actingAs($user)->post('/hosting-services', [
            'client_id' => $client->id,
            'name' => 'Test Domain',
            'type' => 'domain',
            'domain' => 'test-domain.example',
            // status volutamente omesso
        ]);
        
        // La request valida dovrebbe inserirlo con status 'active' e non dare errori su 'status'
        $response->assertSessionDoesntHaveErrors(['status']);
        $response->assertRedirect();
        
        $this->assertDatabaseHas('hosting_services', [
            'name' => 'Test Domain',
            'status' => 'active',
        ]);
    }
}
