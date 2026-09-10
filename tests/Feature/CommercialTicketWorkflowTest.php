<?php

namespace Tests\Feature;

use App\Domain\Core\Actions\CreateTicketAction;
use App\Enums\UserRole;
use App\Livewire\Shared\AttachmentManager;
use App\Livewire\Notifications\NotificationDropdown;
use App\Livewire\Tickets\TicketComments;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketUnassignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialTicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $commercial;
    private Client $client;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Notification::fake();
        $this->admin = $this->user(UserRole::Admin);
        $this->commercial = $this->user(UserRole::Commercial);
        $this->actingAs($this->admin);
        $this->client = Client::factory()->create(['commercial_user_id' => $this->commercial->id]);
        $this->project = Project::factory()->create(['client_id' => $this->client->id, 'status' => 'active']);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active', 'password_changed_at' => now()]);
    }

    private function ticket(User $creator, array $attributes = []): Ticket
    {
        return Ticket::create(array_merge([
            'client_id' => $this->client->id, 'project_id' => $this->project->id,
            'created_by' => $creator->id, 'title' => 'Richiesta dimostrativa',
            'type' => 'request', 'status' => 'open', 'priority' => 'medium',
        ], $attributes));
    }

    private function payload(array $attributes = []): array
    {
        return array_merge([
            'client_id' => $this->client->id, 'project_id' => $this->project->id,
            'title' => 'Richiesta dimostrativa', 'type' => 'request', 'priority' => 'medium',
            'description' => 'Dettagli dimostrativi del cliente.', 'requested_department' => 'marketing',
        ], $attributes);
    }

    public function test_commercial_role_is_available_to_admin_and_has_a_dedicated_workspace(): void
    {
        $this->get(route('users.create'))->assertOk()->assertSee('Commerciale');
        $own = $this->ticket($this->commercial, ['title' => 'Richiesta propria in attesa', 'status' => 'waiting']);
        $this->ticket($this->admin, ['title' => 'Richiesta riservata altrui']);
        $this->actingAs($this->commercial)->get(route('dashboard'))->assertOk()
            ->assertSee($own->title)->assertDontSee('Richiesta riservata altrui')
            ->assertSee('Apri ticket')->assertSee('I miei ticket')->assertSee('Le mie disponibilità')
            ->assertSee('Blocco Note')->assertSee('Calendario')
            ->assertDontSee('Altri Task in Lavorazione')->assertDontSee('Fatture')->assertDontSee('Sodano Drive');
    }

    public function test_commercial_can_select_existing_clients_and_only_receive_project_names(): void
    {
        $withoutProject = Client::factory()->create(['name' => 'Cliente dimostrativo senza progetto', 'commercial_user_id' => $this->commercial->id]);
        $this->actingAs($this->commercial)->get(route('tickets.create'))->assertOk()
            ->assertSee('Crea nuovo cliente')->assertDontSee('name="assigned_to"', false)->assertDontSee('name="status"', false);
        $this->getJson(route('api.clients.search', ['q' => $withoutProject->name]))->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.id', $withoutProject->id);
        $this->get(route('tickets.client-projects', $this->client))->assertOk()
            ->assertExactJson([['id' => $this->project->id, 'name' => $this->project->name]]);
        $this->get(route('tickets.client-projects', $withoutProject))->assertExactJson([]);
        $this->actingAs($this->user(UserRole::Marketing))->get(route('tickets.client-projects', $this->client))->assertForbidden();
    }

    public function test_quote_can_be_opened_without_project_and_notifies_active_admins(): void
    {
        $inactiveAdmin = $this->user(UserRole::Admin);
        $inactiveAdmin->update(['status' => 'inactive']);
        $this->actingAs($this->commercial)->post(route('tickets.store'), $this->payload([
            'type' => 'quote', 'project_id' => null, 'created_by' => $this->admin->id,
            'source' => 'forged', 'external_id' => 'forged',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $ticket = Ticket::query()->sole();
        $this->assertSame($this->commercial->id, $ticket->created_by);
        $this->assertNull($ticket->project_id);
        $this->assertNull($ticket->assigned_to);
        $this->assertNull($ticket->external_id);
        $this->assertSame('open', $ticket->status);
        $this->assertSame('marketing', $ticket->requested_department);
        $this->assertNotEmpty($ticket->code);
        Notification::assertSentTo($this->admin, TicketUnassignedNotification::class);
        Notification::assertNotSentTo($inactiveAdmin, TicketUnassignedNotification::class);
        $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('Richiesta di preventivo');
    }

    public function test_request_and_assignment_notifications_are_saved_and_visible_to_their_recipients(): void
    {
        Notification::swap(new ChannelManager($this->app));
        $inactiveAdmin = $this->user(UserRole::Admin);
        $inactiveAdmin->update(['status' => 'inactive']);
        $adminDropdown = Livewire::test(NotificationDropdown::class);

        $this->actingAs($this->commercial)->post(route('tickets.store'), $this->payload([
            'type' => 'quote', 'project_id' => null,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $ticket = Ticket::query()->sole();
        $notification = $this->admin->visibleNotifications()->sole();
        $this->assertSame('ticket_unassigned', $notification->data['type']);
        $this->assertSame($ticket->id, $notification->data['ticket_id']);
        $this->assertSame(route('tickets.show', $ticket), $notification->data['url']);
        $this->assertNull($notification->read_at);
        $this->assertSame(0, $inactiveAdmin->notifications()->count());
        $this->assertSame(0, $this->commercial->notifications()->count());

        $this->actingAs($this->admin);
        $adminDropdown->call('$refresh')->assertSee('Nuovo ticket non assegnato')->assertSee($ticket->title);
        $recipient = $this->user(UserRole::Developer);
        $assignment = $this->payload([
            'type' => 'quote', 'project_id' => null, 'status' => 'in_progress',
            'assigned_to' => $recipient->id, 'assignment_department' => 'developer',
        ]);
        $this->patch(route('tickets.update', $ticket), $assignment)->assertSessionHasNoErrors()->assertRedirect();
        $assignedNotification = $recipient->visibleNotifications()->sole();
        $this->assertSame('ticket_assigned', $assignedNotification->data['type']);
        $this->assertSame($ticket->id, $assignedNotification->data['ticket_id']);
        $this->assertNull($assignedNotification->read_at);

        $this->patch(route('tickets.update', $ticket), $assignment)->assertSessionHasNoErrors();
        $this->assertSame(1, $recipient->notifications()->count());
        $this->actingAs($recipient);
        Livewire::test(NotificationDropdown::class)->assertSee('Nuovo ticket assegnato')->assertSee($ticket->title);
        $this->post(route('notifications.read', $assignedNotification->id))->assertRedirect(route('tickets.show', $ticket));
        $this->assertNotNull($assignedNotification->fresh()->read_at);
        $this->get(route('tickets.show', $ticket))->assertOk()->assertSee($ticket->title);
    }

    public function test_linked_work_notifies_the_department_member_and_updates_commercial_ticket_progress(): void
    {
        Notification::swap(new ChannelManager($this->app));
        $ticket = $this->ticket($this->commercial);
        $marketing = $this->user(UserRole::Marketing);
        $this->project->users()->attach($marketing->id, ['role' => 'marketing', 'assignment_status' => 'active']);
        $this->post(route('tasks.store'), [
            'project_id' => $this->project->id, 'ticket_id' => $ticket->id,
            'assigned_to' => $marketing->id, 'assignment_department' => 'marketing',
            'title' => 'Lavoro Marketing dimostrativo notificato', 'status' => 'todo', 'priority' => 'medium',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $task = Task::query()->sole();
        $notification = $marketing->visibleNotifications()->sole();
        $this->assertSame('task_assigned', $notification->data['type']);
        $this->assertSame(route('tasks.show', $task), $notification->data['url']);
        $this->actingAs($marketing)->get(route('dashboard'))->assertOk()->assertSee($task->title);
        Livewire::test(NotificationDropdown::class)->assertSee($task->title);
        $this->actingAs($this->commercial)->get(route('tickets.show', $ticket))
            ->assertOk()->assertSee('In lavorazione')->assertDontSee($task->title);
    }

    public function test_commercial_follows_status_and_resolution_of_own_ticket(): void
    {
        $ticket = $this->ticket($this->commercial);
        $foreign = $this->ticket($this->admin, ['title' => 'Ticket riservato ad altri']);

        foreach (['in_progress' => 'In lavorazione', 'waiting' => 'In attesa', 'resolved' => 'Risolto', 'closed' => 'Chiuso'] as $status => $label) {
            $this->actingAs($this->admin)->patch(route('tickets.update-status', $ticket), ['status' => $status])
                ->assertSessionHasNoErrors()->assertRedirect();
            $this->actingAs($this->commercial)->get(route('tickets.index'))
                ->assertOk()->assertSee($ticket->title)->assertSee($label)->assertDontSee($foreign->title);
            $this->get(route('tickets.show', $ticket))->assertOk()->assertSee($label);
        }

        $this->actingAs($this->admin)->patch(route('tickets.update', $ticket), $this->payload([
            'status' => 'closed', 'resolution_notes' => 'Richiesta dimostrativa completata e verificata.',
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($this->commercial)->get(route('tickets.show', $ticket))
            ->assertOk()->assertSee('Richiesta dimostrativa completata e verificata.');
        $this->get(route('tickets.show', $foreign))->assertNotFound();
        $this->patch(route('tickets.update-status', $ticket), ['status' => 'open'])->assertForbidden();
    }

    public function test_intervention_requires_a_project_belonging_to_the_selected_client(): void
    {
        $this->actingAs($this->commercial)->post(route('tickets.store'), $this->payload(['project_id' => null]))
            ->assertSessionHasErrors('project_id');
        $otherClient = Client::factory()->create(['commercial_user_id' => $this->commercial->id]);
        $this->post(route('tickets.store'), $this->payload(['client_id' => $otherClient->id]))
            ->assertSessionHasErrors('project_id');
        $this->post(route('tickets.store'), $this->payload(['requested_department' => 'unknown']))
            ->assertSessionHasErrors('requested_department');
        $this->post(route('tickets.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_commercial_cannot_force_operational_fields_on_creation(): void
    {
        $this->actingAs($this->commercial);
        $this->assertSame(1, Client::visibleTo($this->commercial)->count());
        foreach (['assigned_to' => $this->admin->id, 'status' => 'closed', 'assignment_department' => 'admin',
            'due_date' => '2026-09-20', 'opened_at' => '2020-01-01', 'notes' => 'nota', 'resolution_notes' => 'risolto'] as $field => $value) {
            $this->post(route('tickets.store'), $this->payload([$field => $value]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('tickets', 0);
        $ticket = app(CreateTicketAction::class)->execute($this->payload(['status' => 'closed', 'assigned_to' => $this->admin->id]));
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->assigned_to);
    }

    public function test_only_own_tickets_are_visible_even_with_project_membership_or_direct_assignment(): void
    {
        $this->project->users()->attach($this->commercial->id, ['role' => 'member']);
        $own = $this->ticket($this->commercial, ['title' => 'Ticket personale visibile']);
        $foreign = $this->ticket($this->admin, ['title' => 'Ticket estraneo nascosto', 'assigned_to' => $this->commercial->id]);
        $this->actingAs($this->commercial)->get(route('tickets.index'))->assertOk()->assertSee($own->title)->assertDontSee($foreign->title);
        $this->get(route('tickets.index', ['search' => $foreign->title]))->assertOk()
            ->assertViewHas('tickets', fn ($tickets) => $tickets->total() === 0);
        $this->get(route('tickets.show', $foreign))->assertNotFound();
        $this->get(route('tickets.show', $own))->assertOk()->assertSee($this->project->name)
            ->assertDontSee('Aggiornamento rapido')->assertDontSee('Checklist')->assertDontSee('Invia al cliente con Sody');
    }

    public function test_commercial_cannot_modify_close_delete_or_manage_ticket_checklists(): void
    {
        $ticket = $this->ticket($this->commercial);
        $this->actingAs($this->commercial)->get(route('tickets.edit', $ticket))->assertForbidden();
        $this->patch(route('tickets.update', $ticket), $this->payload(['status' => 'closed']))->assertForbidden();
        $this->patch(route('tickets.update-status', $ticket), ['status' => 'closed'])->assertForbidden();
        $this->delete(route('tickets.destroy', $ticket))->assertForbidden();
        $this->post(route('tickets.checklist-items.store', $ticket), ['title' => 'Operazione non consentita'])->assertForbidden();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'open']);
    }

    public function test_commercial_does_not_gain_other_module_permissions_through_membership(): void
    {
        $this->project->users()->attach($this->commercial->id, ['role' => 'member']);
        $task = Task::create(['project_id' => $this->project->id, 'created_by' => $this->admin->id, 'title' => 'Task riservato', 'status' => 'todo']);
        $this->actingAs($this->commercial);
        foreach (['clients.index', 'clients.show', 'projects.index', 'tasks.index', 'tasks.create', 'teams.index',
            'invoices.index', 'payments.index', 'hosting-services.index', 'users.index', 'admin.availability.index'] as $route) {
            $this->get(route($route, $route === 'clients.show' ? $this->client : []))->assertForbidden();
        }
        $this->get(route('projects.show', $this->project))->assertNotFound();
        $this->get(route('tasks.show', $task))->assertNotFound();
        $this->get(route('availability.index'))->assertOk();
    }

    public function test_commercial_can_comment_but_cannot_send_to_sody_or_open_foreign_comments(): void
    {
        $own = $this->ticket($this->commercial);
        $foreign = $this->ticket($this->admin);
        Livewire::actingAs($this->commercial)->test(TicketComments::class, ['ticket' => $own])
            ->set('body', 'Chiarimento dimostrativo')->call('addComment')->assertHasNoErrors()->assertSee('Chiarimento dimostrativo');
        Livewire::test(TicketComments::class, ['ticket' => $own])->set('body', 'Invio non autorizzato')
            ->set('delivery_mode', 'send_to_client_via_sody')->call('addComment')->assertForbidden();
        Livewire::test(TicketComments::class, ['ticket' => $foreign])->assertForbidden();
        $this->assertDatabaseCount('ticket_comments', 1);
    }

    public function test_commercial_can_upload_and_download_only_own_ticket_attachments(): void
    {
        Storage::fake('attachments');
        $own = $this->ticket($this->commercial);
        $foreign = $this->ticket($this->admin);
        $this->actingAs($this->commercial)->post(route('attachments.store'), [
            'attachable_type' => 'ticket', 'attachable_id' => $own->id, 'type' => 'document',
            'file' => UploadedFile::fake()->create('richiesta-dimostrativa.txt', 1, 'text/plain'),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $attachment = $own->attachments()->sole();
        $this->get(route('attachments.download', $attachment))->assertOk();
        Livewire::test(AttachmentManager::class, ['model' => $own])
            ->set('file', UploadedFile::fake()->create('dettagli-dimostrativi.txt', 1, 'text/plain'))->assertHasNoErrors();
        $this->post(route('attachments.store'), [
            'attachable_type' => 'ticket', 'attachable_id' => $foreign->id, 'type' => 'document',
            'file' => UploadedFile::fake()->create('vietato.txt', 1, 'text/plain'),
        ])->assertForbidden();
        Livewire::test(AttachmentManager::class, ['model' => $foreign])->assertForbidden();
        $this->assertDatabaseCount('attachments', 2);
    }

    public function test_admin_assigns_a_quote_without_project_to_a_ticket_enabled_referent(): void
    {
        $ticket = $this->ticket($this->commercial, ['type' => 'quote', 'project_id' => null]);
        $developer = $this->user(UserRole::Developer);
        $this->patch(route('tickets.update', $ticket), $this->payload([
            'type' => 'quote', 'project_id' => null, 'status' => 'in_progress',
            'assignment_department' => 'developer', 'assigned_to' => $developer->id,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($developer)->get(route('tickets.show', $ticket))->assertOk();
        $this->assertSame(1, Ticket::visibleTo($developer)->count());
        $this->actingAs($this->commercial)->get(route('tickets.show', $ticket))->assertOk()->assertSee($developer->name);
    }

    public function test_admin_cannot_assign_ticket_to_wrong_department_or_inaccessible_project(): void
    {
        $ticket = $this->ticket($this->commercial);
        $developer = $this->user(UserRole::Developer);
        $this->patch(route('tickets.update', $ticket), $this->payload(['status' => 'open', 'assigned_to' => $developer->id]))
            ->assertSessionHasErrors('assigned_to');
        $this->project->users()->attach($developer->id, ['role' => 'developer']);
        $this->patch(route('tickets.update', $ticket), $this->payload([
            'status' => 'open', 'assigned_to' => $developer->id, 'assignment_department' => 'marketing',
        ]))->assertSessionHasErrors('assigned_to');
        $marketing = $this->user(UserRole::Marketing);
        $this->project->users()->attach($marketing->id, ['role' => 'marketing']);
        $this->patch(route('tickets.update', $ticket), $this->payload(['status' => 'open', 'assigned_to' => $marketing->id]))
            ->assertSessionHasErrors('assigned_to');
    }

    public function test_admin_can_assign_linked_work_to_marketing_and_commercial_can_follow_ticket_status(): void
    {
        $ticket = $this->ticket($this->commercial, ['requested_department' => 'marketing']);
        $marketing = $this->user(UserRole::Marketing);
        $this->project->users()->attach($marketing->id, ['role' => 'marketing']);
        $this->get(route('tasks.create', ['ticket_id' => $ticket->id]))->assertOk()->assertSee('Reparto del referente');
        $this->post(route('tasks.store'), [
            'project_id' => $this->project->id, 'ticket_id' => $ticket->id, 'assigned_to' => $marketing->id,
            'assignment_department' => 'marketing', 'title' => 'Attività Marketing dimostrativa', 'status' => 'todo', 'priority' => 'medium',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $task = Task::query()->sole();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'in_progress']);
        $this->actingAs($marketing)->get(route('tasks.show', $task))->assertOk();
        $this->get(route('tickets.show', $ticket))->assertForbidden();
        $this->actingAs($this->commercial)->get(route('tickets.show', $ticket))->assertOk()->assertSee('In lavorazione')->assertDontSee($task->title);
    }

    public function test_commercial_cannot_be_assigned_operational_tasks(): void
    {
        $payload = [
            'project_id' => $this->project->id, 'assigned_to' => $this->commercial->id,
            'title' => 'Attività dimostrativa', 'status' => 'todo', 'priority' => 'medium',
        ];
        $this->post(route('tasks.store'), $payload)->assertSessionHasErrors('assigned_to');
        $task = Task::create(['project_id' => $this->project->id, 'created_by' => $this->admin->id, 'title' => 'Task dimostrativo', 'status' => 'todo']);
        $this->put(route('tasks.update', $task), $payload)->assertSessionHasErrors('assigned_to');
        $this->get(route('tasks.create'))->assertViewHas('users', fn ($users) => !$users->contains('id', $this->commercial->id));
        $this->assertNull($task->fresh()->assigned_to);
    }

    public function test_notifications_do_not_reveal_tickets_or_tasks_from_a_previous_role(): void
    {
        $own = $this->ticket($this->commercial);
        $foreign = $this->ticket($this->admin);
        $visible = null;
        $hidden = null;
        foreach ([['ticket_assigned', $own->id, 'Notifica propria'], ['ticket_assigned', $foreign->id, 'Ticket altrui nascosto'], ['task_assigned', null, 'Task altrui nascosto']] as [$type, $ticketId, $message]) {
            $notification = $this->commercial->notifications()->create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => \App\Notifications\TicketAssignedNotification::class,
                'data' => ['type' => $type, 'ticket_id' => $ticketId, 'message' => $message, 'url' => route('tickets.show', $ticketId ?? $foreign->id)],
            ]);
            if ($ticketId === $own->id) {
                $visible = $notification;
            } else {
                $hidden = $notification;
            }
        }
        $this->actingAs($this->commercial)->get(route('dashboard'))->assertOk()->assertSee('Notifica propria')
            ->assertDontSee('Ticket altrui nascosto')->assertDontSee('Task altrui nascosto');
        Livewire::test(\App\Livewire\Notifications\NotificationDropdown::class)->call('markAllAsRead');
        $this->assertNotNull($visible->fresh()->read_at);
        $this->assertNull($hidden->fresh()->read_at);
        $this->post(route('notifications.read', $hidden->id))->assertNotFound();
        $this->delete(route('notifications.destroy', $hidden->id))->assertNotFound();
    }
}
