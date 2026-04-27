<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // ── fixtures ───────────────────────────────────────────────────────────────

    private User $admin;
    private User $administration;
    private User $operativo;
    private User $otherOperativo;
    private Client $client;
    private Project $project;       // progetto primario
    private Project $otherProject;  // progetto estraneo all'operativo
    private Ticket $ticket;         // ticket globale nel progetto
    private Ticket $assignedTicket; // ticket assegnato all'operativo

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin          = User::factory()->create(['role' => UserRole::Admin]);
        $this->administration = User::factory()->create(['role' => UserRole::Administration]);
        $this->operativo      = User::factory()->create(['role' => UserRole::Developer]);
        $this->otherOperativo = User::factory()->create(['role' => UserRole::Developer]);

        $this->client = Client::create([
            'name'   => 'Acme Srl',
            'slug'   => 'acme-srl',
            'status' => 'active',
        ]);

        $this->project = Project::create([
            'client_id' => $this->client->id,
            'name'      => 'Progetto Alpha',
            'slug'      => 'progetto-alpha',
            'status'    => 'active',
        ]);

        $this->otherProject = Project::create([
            'client_id' => $this->client->id,
            'name'      => 'Progetto Beta',
            'slug'      => 'progetto-beta',
            'status'    => 'active',
        ]);

        // Operativo assegnato al progetto (ha accesso a tutti i ticket)
        $this->project->users()->attach($this->operativo->id, [
            'role'              => 'developer',
            'assignment_status' => 'active',
            'assigned_at'       => now(),
        ]);

        $this->ticket = Ticket::create([
            'client_id'  => $this->client->id,
            'project_id' => $this->project->id,
            'created_by' => $this->admin->id,
            'title'      => 'Bug critico',
            'type'       => 'bug',
            'status'     => 'open',
            'priority'   => 'high',
            'opened_at'  => now(),
        ]);

        $this->assignedTicket = Ticket::create([
            'client_id'   => $this->client->id,
            'project_id'  => $this->project->id,
            'created_by'  => $this->admin->id,
            'assigned_to' => $this->operativo->id, // Assegnato a operativo
            'title'       => 'Task operativo',
            'type'        => 'support',
            'status'      => 'open',
            'priority'    => 'medium',
            'opened_at'   => now(),
        ]);
    }

    // ── index (lista) ──────────────────────────────────────────────────────────

    public function test_admin_can_see_ticket_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('tickets.index'))
            ->assertOk();
    }

    public function test_administration_cannot_see_ticket_index(): void
    {
        $this->actingAs($this->administration)
            ->get(route('tickets.index'))
            ->assertForbidden();
    }

    public function test_operativo_can_see_ticket_index(): void
    {
        $this->actingAs($this->operativo)
            ->get(route('tickets.index'))
            ->assertOk();
    }

    public function test_guest_is_redirected_from_ticket_index(): void
    {
        $this->get(route('tickets.index'))
            ->assertRedirect(route('login'));
    }

    // ── show ───────────────────────────────────────────────────────────────────

    public function test_admin_can_view_any_ticket(): void
    {
        $this->actingAs($this->admin)
            ->get(route('tickets.show', $this->ticket))
            ->assertOk();
    }

    public function test_developer_can_view_ticket_in_own_project(): void
    {
        $this->actingAs($this->operativo)
            ->get(route('tickets.show', $this->ticket))
            ->assertOk();
    }

    public function test_developer_cannot_view_ticket_outside_own_projects(): void
    {
        $foreignTicket = Ticket::create([
            'client_id'  => $this->client->id,
            'project_id' => $this->otherProject->id,
            'created_by' => $this->admin->id,
            'title'      => 'Ticket estraneo',
            'type'       => 'support',
            'status'     => 'open',
            'priority'   => 'low',
            'opened_at'  => now(),
        ]);

        $this->actingAs($this->operativo)
            ->get(route('tickets.show', $foreignTicket))
            ->assertNotFound();
    }

    public function test_operativo_can_view_ticket_assigned_to_self(): void
    {
        $this->actingAs($this->operativo)
            ->get(route('tickets.show', $this->assignedTicket))
            ->assertOk();
    }

    public function test_operativo_can_view_ticket_created_by_self(): void
    {
        $ownTicket = Ticket::create([
            'client_id'  => $this->client->id,
            'project_id' => $this->project->id,
            'created_by' => $this->operativo->id,
            'title'      => 'Mio ticket',
            'type'       => 'request',
            'status'     => 'open',
            'priority'   => 'low',
            'opened_at'  => now(),
        ]);

        $this->actingAs($this->operativo)
            ->get(route('tickets.show', $ownTicket))
            ->assertOk();
    }

    public function test_operativo_cannot_view_ticket_not_assigned_to_self(): void
    {
        $this->actingAs($this->otherOperativo)
            ->get(route('tickets.show', $this->assignedTicket))
            ->assertNotFound();
    }

    // ── create/store ───────────────────────────────────────────────────────────

    public function test_admin_can_access_ticket_create_form(): void
    {
        $this->actingAs($this->admin)
            ->get(route('tickets.create'))
            ->assertOk();
    }

    public function test_administration_cannot_access_ticket_create_form(): void
    {
        $this->actingAs($this->administration)
            ->get(route('tickets.create'))
            ->assertForbidden();
    }

    public function test_operativo_can_access_ticket_create_form(): void
    {
        $this->actingAs($this->operativo)
            ->get(route('tickets.create'))
            ->assertOk();
    }

    // ── update ─────────────────────────────────────────────────────────────────

    public function test_admin_can_update_any_ticket(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('tickets.update', $this->ticket), [
                'client_id' => $this->client->id,
                'title'     => 'Titolo aggiornato',
                'type'      => 'bug',
                'status'    => 'in_progress',
                'priority'  => 'high',
            ])
            ->assertRedirect(route('tickets.show', $this->ticket));
    }

    public function test_developer_can_update_ticket_in_own_project(): void
    {
        $this->actingAs($this->operativo)
            ->patch(route('tickets.update', $this->ticket), [
                'client_id' => $this->client->id,
                'title'     => 'Aggiornato dall\'operativo nel progetto',
                'type'      => 'bug',
                'status'    => 'in_progress',
                'priority'  => 'high',
            ])
            ->assertRedirect(route('tickets.show', $this->ticket));
    }

    public function test_developer_cannot_update_ticket_outside_own_projects(): void
    {
        $foreignTicket = Ticket::create([
            'client_id'  => $this->client->id,
            'project_id' => $this->otherProject->id,
            'created_by' => $this->admin->id,
            'title'      => 'Ticket estraneo',
            'type'       => 'support',
            'status'     => 'open',
            'priority'   => 'low',
            'opened_at'  => now(),
        ]);

        $this->actingAs($this->operativo)
            ->patch(route('tickets.update', $foreignTicket), [
                'client_id' => $this->client->id,
                'title'     => 'Tentativo di modifica',
                'type'      => 'support',
                'status'    => 'open',
                'priority'  => 'low',
            ])
            ->assertNotFound();
    }

    public function test_operativo_can_update_ticket_assigned_to_self(): void
    {
        $this->actingAs($this->operativo)
            ->patch(route('tickets.update', $this->assignedTicket), [
                'client_id' => $this->client->id,
                'title'     => 'Aggiornato dall\'operativo',
                'type'      => 'support',
                'status'    => 'in_progress',
                'priority'  => 'medium',
            ])
            ->assertRedirect(route('tickets.show', $this->assignedTicket));
    }

    public function test_operativo_cannot_update_ticket_not_assigned_to_self(): void
    {
        $this->actingAs($this->otherOperativo)
            ->patch(route('tickets.update', $this->assignedTicket), [
                'client_id' => $this->client->id,
                'title'     => 'Tentativo non autorizzato',
                'type'      => 'support',
                'status'    => 'open',
                'priority'  => 'medium',
            ])
            ->assertNotFound();
    }

    // ── delete ─────────────────────────────────────────────────────────────────

    public function test_admin_can_delete_any_ticket(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('tickets.destroy', $this->ticket))
            ->assertRedirect(route('tickets.index'));

        $this->assertDatabaseMissing('tickets', ['id' => $this->ticket->id]);
    }

    public function test_administration_cannot_delete_ticket(): void
    {
        $this->actingAs($this->administration)
            ->delete(route('tickets.destroy', $this->ticket))
            ->assertForbidden();
    }

    public function test_operativo_cannot_delete_any_ticket(): void
    {
        // L'operativo non ha il permesso 'delete' sulla TicketPolicy
        $this->actingAs($this->operativo)
            ->delete(route('tickets.destroy', $this->assignedTicket))
            ->assertForbidden();
    }
}
