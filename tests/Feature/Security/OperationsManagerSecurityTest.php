<?php

namespace Tests\Feature\Security;

use App\Models\Client;
use App\Models\ClientSocialAccount;
use App\Models\HostingService;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperationsManagerSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_manager_cannot_mount_client_social_account_form_livewire()
    {
        $user = User::factory()->create(['role' => UserRole::OperationsManager]);
        $client = Client::factory()->create();

        Livewire::actingAs($user)
            ->test(\App\Livewire\Client\ClientSocialAccountForm::class, ['client' => $client])
            ->assertForbidden();
    }

    public function test_developer_cannot_mount_client_social_account_form_livewire()
    {
        $user = User::factory()->create(['role' => UserRole::Developer]);
        $client = Client::factory()->create();

        Livewire::actingAs($user)
            ->test(\App\Livewire\Client\ClientSocialAccountForm::class, ['client' => $client])
            ->assertForbidden();
    }

    public function test_admin_can_mount_client_social_account_form_livewire()
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $client = Client::factory()->create();

        Livewire::actingAs($user)
            ->test(\App\Livewire\Client\ClientSocialAccountForm::class, ['client' => $client])
            ->assertOk();
    }

    public function test_operations_manager_cannot_manage_hosting_credentials()
    {
        $user = User::factory()->create(['role' => UserRole::OperationsManager]);
        $client = Client::factory()->create();
        $hosting = HostingService::create([
            'client_id' => $client->id,
            'name' => 'test.com',
            'type' => 'domain',
            'status' => 'active'
        ]);

        $this->assertFalse($user->can('manageCredentials', $hosting));
    }

    public function test_admin_can_manage_hosting_credentials()
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $client = Client::factory()->create();
        $hosting = HostingService::create([
            'client_id' => $client->id,
            'name' => 'test2.com',
            'type' => 'domain',
            'status' => 'active'
        ]);

        $this->assertTrue($user->can('manageCredentials', $hosting));
    }

    public function test_operations_manager_http_security_restrictions()
    {
        $user = User::factory()->create(['role' => UserRole::OperationsManager]);
        $client = Client::factory()->create();
        
        $hosting = HostingService::create([
            'client_id' => $client->id,
            'name' => 'test2.com',
            'type' => 'domain',
            'status' => 'active'
        ]);

        // POST credentials and finance rejected
        $response = $this->actingAs($user)->post('/hosting-services', [
            'client_id' => $client->id,
            'type' => 'domain',
            'name' => 'SecureDomain',
            'status' => 'active',
            'username' => 'admin',
            'password' => 'secret',
            'renewal_cost' => 100,
        ]);
        $response->assertSessionHasErrors(['username', 'password', 'renewal_cost']);

        // PATCH credentials and finance rejected
        $response = $this->actingAs($user)->put("/hosting-services/{$hosting->id}", [
            'client_id' => $client->id,
            'type' => 'domain',
            'name' => 'test2.com',
            'status' => 'active',
            'username' => 'admin',
            'password' => 'secret',
            'resource_cost' => 50,
        ]);
        $response->assertSessionHasErrors(['username', 'password', 'resource_cost']);

        // POST intervention cost rejected
        $response = $this->actingAs($user)->post("/hosting-services/{$hosting->id}/interventions", [
            'title' => 'Update',
            'intervention_date' => now()->format('Y-m-d'),
            'cost' => 5000,
        ]);
        $response->assertSessionHasErrors(['cost']);
    }

    public function test_operations_manager_historic_ticket_without_project()
    {
        $user = User::factory()->create(['role' => UserRole::OperationsManager]);
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);

        $ticket = Ticket::factory()->create([
            'client_id' => $client->id,
            'project_id' => null, // Historic ticket without project
            'title' => 'Historic ticket',
            'type' => Ticket::TYPES[0],
            'status' => Ticket::STATUSES[0],
            'priority' => Ticket::PRIORITIES[0],
        ]);

        // GET show
        $response = $this->actingAs($user)->get("/tickets/{$ticket->id}");
        $response->assertOk();

        // PATCH without project should fail validation
        $response = $this->actingAs($user)->put("/tickets/{$ticket->id}", [
            'client_id' => $client->id,
            'title' => 'Update title',
            'type' => Ticket::TYPES[0],
            'status' => Ticket::STATUSES[0],
            'priority' => Ticket::PRIORITIES[0],
        ]);
        $response->assertSessionHasErrors(['project_id']);

        // PATCH with valid project should pass validation (redirect 302)
        $response = $this->actingAs($user)->put("/tickets/{$ticket->id}", [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Update title',
            'type' => Ticket::TYPES[0],
            'status' => Ticket::STATUSES[0],
            'priority' => Ticket::PRIORITIES[0],
        ]);
        $response->assertRedirect();
        
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'project_id' => $project->id,
        ]);
    }
}
