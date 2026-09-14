<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Notifications\NotificationDropdown;
use App\Livewire\Shared\AttachmentManager;
use App\Livewire\Tasks\TaskComments;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Shooting\Shoot;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\QuoteUpdatedNotification;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TicketUnassignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialSalesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $administration;

    private User $commercial;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Notification::fake();
        Http::preventStrayRequests();
        $this->admin = $this->user(UserRole::Admin);
        $this->administration = $this->user(UserRole::Administration);
        $this->commercial = $this->user(UserRole::Commercial);
        $this->actingAs($this->admin);
        $this->client = Client::factory()->create(['name' => 'Cliente commerciale dimostrativo', 'commercial_user_id' => $this->commercial->id]);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active', 'password_changed_at' => now()]);
    }

    private function quotePayload(): array
    {
        return ['client_id' => $this->client->id, 'title' => 'Offerta dimostrativa', 'notes' => 'Condizioni dimostrative', 'items' => [
            ['name' => 'Servizio dimostrativo A', 'description' => 'Descrizione A', 'quantity' => '3', 'unit_price' => '12.34'],
            ['name' => 'Servizio dimostrativo B', 'quantity' => '0.5', 'unit_price' => '1.01'],
        ]];
    }

    private function createQuote(): Quote
    {
        $this->actingAs($this->admin)->post(route('quotes.store'), $this->quotePayload())->assertSessionHasNoErrors()->assertRedirect();

        return Quote::latest('id')->firstOrFail();
    }

    public function test_commercial_reads_all_client_tasks_including_closed_work_and_campaign_shooting(): void
    {
        $project = Project::factory()->create(['client_id' => $this->client->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'title' => 'Lavoro del cliente completato', 'status' => 'done', 'completed_at' => now(), 'notes' => 'Nota interna riservata']);
        $foreign = Task::factory()->create(['title' => 'Lavoro cliente altrui riservato']);
        $campaign = MarketingCampaign::factory()->create(['client_id' => $this->client->id]);
        $shootTask = Task::factory()->create(['project_id' => null, 'title' => 'Shooting cliente senza progetto', 'description' => 'Contiene note interne riservate']);
        Shoot::factory()->create(['project_id' => null, 'marketing_campaign_id' => $campaign->id, 'task_id' => $shootTask->id, 'client_notes' => 'Indicazioni pubbliche dimostrative']);
        $this->actingAs($this->commercial)->get(route('tasks.index'))->assertOk()->assertSee($task->title)->assertSee($shootTask->title)->assertDontSee($foreign->title);
        $this->get(route('tasks.index', ['client_id' => $this->client->id, 'status' => 'done']))->assertOk()->assertSee($task->title)->assertDontSee($shootTask->title);
        $this->get(route('tasks.show', $task))->assertOk()->assertSee('Completata')->assertDontSee('Nota interna riservata');
        $this->get(route('tasks.show', $shootTask))->assertOk()->assertSee($this->client->name)->assertSee('Indicazioni pubbliche dimostrative')->assertDontSee('Contiene note interne riservate');
        $this->get(route('tasks.show', $foreign))->assertNotFound();
        $this->patch(route('tasks.update-status', $task), ['status' => 'todo'])->assertForbidden();
        Livewire::test(TaskComments::class, ['task' => $task])->assertForbidden();
        Livewire::test(AttachmentManager::class, ['model' => $task])->assertForbidden();
        $this->get(route('projects.show', $project))->assertNotFound();
    }

    public function test_direct_assignment_is_visible_and_notifies_commercial_without_opening_the_project(): void
    {
        $project = Project::factory()->create();
        $this->post(route('tasks.store'), [
            'project_id' => $project->id, 'assigned_to' => $this->commercial->id,
            'title' => 'Task assegnata direttamente al commerciale', 'status' => 'todo', 'priority' => 'medium',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $task = Task::withoutGlobalScopes()->where('title', 'Task assegnata direttamente al commerciale')->sole();
        Notification::assertSentTo($this->commercial, TaskAssignedNotification::class);
        $this->actingAs($this->commercial)->get(route('dashboard'))->assertOk()->assertSee($task->title);
        $this->get(route('tasks.show', $task))->assertOk();
        $this->get(route('projects.show', $project))->assertNotFound();
    }

    public function test_client_assignment_change_updates_task_and_client_history_access(): void
    {
        $project = Project::factory()->create(['client_id' => $this->client->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $other = $this->user(UserRole::Commercial);
        $this->client->update(['commercial_user_id' => $other->id]);
        $this->actingAs($this->commercial)->get(route('tasks.show', $task))->assertNotFound();
        $this->get(route('clients.show', $this->client))->assertForbidden();
        $this->actingAs($other)->get(route('tasks.show', $task))->assertOk();
        $this->get(route('clients.show', $this->client))->assertOk();
    }

    public function test_quote_request_saves_client_details_and_multiple_services_and_notifies_intake_roles(): void
    {
        $this->actingAs($this->commercial);
        $this->get(route('tickets.create', ['type' => 'quote']))->assertOk()->assertSee('Servizi richiesti')->assertSee('Codice fiscale');
        $created = $this->postJson(route('api.clients.quick-store'), [
            'name' => 'Nuova anagrafica dimostrativa', 'reference_person' => 'Referente dimostrativo',
            'city' => 'Comune dimostrativo', 'postal_code' => '00000', 'tax_code' => 'DEMO-CF',
        ])->assertCreated();
        $payload = ['client_id' => $created->json('id'), 'type' => 'quote', 'priority' => 'medium', 'title' => 'Richiesta dimostrativa senza progetto'];
        $this->post(route('tickets.store'), $payload)->assertSessionHasErrors('requested_services');
        $payload['requested_services'] = [['name' => 'Servizio A', 'description' => 'Dettagli A'], ['name' => 'Servizio B']];
        $this->post(route('tickets.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $ticket = Ticket::query()->sole();
        $this->assertNull($ticket->project_id);
        $this->assertSame(2, $ticket->requestedServices()->count());
        $this->assertDatabaseHas('clients', ['id' => $ticket->client_id, 'commercial_user_id' => $this->commercial->id, 'tax_code' => 'DEMO-CF']);
        Notification::assertSentTo([$this->admin, $this->administration], TicketUnassignedNotification::class);
        $this->actingAs($this->administration)->get(route('tickets.show', $ticket))->assertOk()->assertSee('Servizio A')->assertSee('Prepara offerta');
        $this->get(route('quotes.create', ['ticket_id' => $ticket->id]))->assertOk()->assertSee('Servizio A');
    }

    public function test_offer_totals_presentation_snapshot_and_readonly_commercial_access(): void
    {
        $quote = $this->createQuote();
        $this->assertSame('37.53', $quote->total);
        $this->actingAs($this->commercial)->get(route('quotes.show', $quote))->assertForbidden();
        $this->post(route('quotes.present', $quote))->assertForbidden();
        $this->actingAs($this->administration)->post(route('quotes.present', $quote))->assertRedirect();
        Notification::assertSentTo($this->commercial, QuoteUpdatedNotification::class);
        $this->client->update(['name' => 'Nome successivo dimostrativo']);
        $this->actingAs($this->commercial)->get(route('quotes.show', $quote))->assertOk()->assertSee('Cliente commerciale dimostrativo')->assertSee('37,53');
        $this->get(route('clients.show', $this->client))->assertOk()->assertSee($quote->title);
        $this->put(route('quotes.update', $quote), ['title' => 'Modifica vietata'])->assertForbidden();
        $this->post(route('quotes.accept', $quote))->assertForbidden();
        $this->actingAs($this->admin)->put(route('quotes.update', $quote), ['title' => 'Modifica storica vietata'])->assertForbidden();
        $other = $this->user(UserRole::Commercial);
        $this->actingAs($other)->get(route('quotes.show', $quote))->assertForbidden();
        $this->get(route('quotes.index'))->assertOk()->assertDontSee($quote->title);
    }

    public function test_revision_keeps_presented_offer_immutable_and_cannot_be_created_twice(): void
    {
        $quote = $this->createQuote();
        $this->post(route('quotes.present', $quote))->assertRedirect();
        $this->post(route('quotes.revise', $quote))->assertRedirect();
        $revision = Quote::where('previous_quote_id', $quote->id)->sole();
        $this->post(route('quotes.revise', $quote))->assertRedirect(route('quotes.edit', $revision));
        $this->assertDatabaseCount('quotes', 2);
        $data = $this->quotePayload();
        unset($data['client_id']);
        $data['items'][0]['unit_price'] = '20';
        $this->put(route('quotes.update', $revision), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('37.53', $quote->fresh()->total);
        $this->assertSame('60.51', $revision->fresh()->total);
        $this->post(route('quotes.accept', $quote))->assertForbidden();
        $this->post(route('quotes.present', $revision))->assertRedirect();
        $this->actingAs($this->administration)->post(route('quotes.accept', $revision))->assertRedirect();
        $this->assertSame('accepted', $revision->fresh()->status);
    }

    public function test_only_accepted_offer_creates_one_project_with_selected_team_and_client(): void
    {
        $quote = $this->createQuote();
        $this->get(route('quotes.project.create', $quote))->assertForbidden();
        $this->post(route('quotes.present', $quote))->assertRedirect();
        $this->post(route('quotes.accept', $quote))->assertRedirect();
        $member = $this->user(UserRole::Developer);
        $data = ['name' => 'Progetto da offerta dimostrativa', 'code' => 'DEMO-OFFERTA', 'client_id' => $this->client->id, 'status' => 'active', 'members' => [$member->id], 'description' => 'Servizi accettati'];
        $this->actingAs($this->administration)->get(route('quotes.project.create', $quote))->assertOk()->assertSee('Team di commessa');
        $this->post(route('quotes.project.store', $quote), array_replace($data, ['members' => []]))->assertSessionHasErrors('members');
        $this->post(route('quotes.project.store', $quote), $data)->assertSessionHasNoErrors()->assertRedirect();
        $project = $quote->fresh()->project;
        $this->assertSame($this->client->id, $project->client_id);
        $this->assertTrue($project->users()->whereKey($member->id)->exists());
        $this->post(route('quotes.project.store', $quote), $data)->assertRedirect(route('projects.show', $project));
        $this->assertSame(1, Project::where('name', $data['name'])->count());
        $this->actingAs($this->commercial)->post(route('quotes.project.store', $quote), $data)->assertForbidden();
        $this->actingAs($this->admin);
        $ticket = Ticket::create(['client_id' => $this->client->id, 'project_id' => $project->id, 'title' => 'Ticket dimostrativo da conservare',
            'created_by' => $this->admin->id, 'type' => 'request', 'status' => 'open', 'priority' => 'medium']);
        $this->delete(route('projects.destroy', $project))->assertSessionHasErrors('project');
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
    }

    public function test_quote_fields_and_history_cannot_be_forged_or_deleted_with_client(): void
    {
        $this->post(route('quotes.store'), $this->quotePayload() + ['status' => 'accepted', 'total' => '0'])->assertSessionHasErrors(['status', 'total']);
        $data = $this->quotePayload();
        $data['items'][0]['unit_price'] = '12.345';
        $this->post(route('quotes.store'), $data)->assertSessionHasErrors('items.0.unit_price');
        $quote = $this->createQuote();
        $this->delete(route('clients.destroy', $this->client))->assertSessionHasErrors('client');
        $this->assertDatabaseHas('quotes', ['id' => $quote->id]);
        $this->assertDatabaseHas('clients', ['id' => $this->client->id]);
    }

    public function test_real_task_and_offer_notifications_are_readable_only_while_their_records_are_visible(): void
    {
        Notification::swap(new ChannelManager($this->app));
        $project = Project::factory()->create(['client_id' => $this->client->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $this->admin->id, 'title' => 'Task notificata dimostrativa']);
        $this->patch(route('tasks.update', $task), [
            'project_id' => $project->id, 'assigned_to' => $this->commercial->id,
            'title' => $task->title, 'status' => 'todo', 'priority' => 'medium',
        ])->assertSessionHasNoErrors();
        $quote = $this->createQuote();
        $this->post(route('quotes.present', $quote))->assertRedirect();

        $this->actingAs($this->commercial);
        $taskNotice = $this->commercial->visibleNotifications()->where('type', TaskAssignedNotification::class)->sole();
        $quoteNotice = $this->commercial->visibleNotifications()->where('type', QuoteUpdatedNotification::class)->sole();
        Livewire::test(NotificationDropdown::class)->assertSee($task->title)->assertSee($quote->title);
        $this->post(route('notifications.read', $taskNotice->id))->assertRedirect(route('tasks.show', $task));
        $this->post(route('notifications.read', $quoteNotice->id))->assertRedirect(route('quotes.show', $quote));
        $this->assertNotNull($taskNotice->fresh()->read_at);
        $this->assertNotNull($quoteNotice->fresh()->read_at);

        $this->actingAs($this->admin);
        $task->refresh()->update(['assigned_to' => $this->admin->id]);
        $this->client->update(['commercial_user_id' => $this->user(UserRole::Commercial)->id]);
        $this->actingAs($this->commercial);
        $this->assertSame(0, $this->commercial->visibleNotifications()->count());
        $this->post(route('notifications.read', $taskNotice->id))->assertNotFound();
        $this->post(route('notifications.read', $quoteNotice->id))->assertNotFound();
    }

    public function test_offer_source_must_be_a_quote_request_for_the_selected_client(): void
    {
        $foreignClient = Client::factory()->create();
        $ticket = Ticket::create(['client_id' => $foreignClient->id, 'project_id' => null, 'created_by' => $this->admin->id,
            'title' => 'Richiesta dimostrativa di altro cliente', 'type' => 'quote', 'status' => 'open', 'priority' => 'medium']);
        $this->post(route('quotes.store'), $this->quotePayload() + ['ticket_id' => $ticket->id])->assertSessionHasErrors('ticket_id');
        $ticket->update(['client_id' => $this->client->id, 'type' => 'bug']);
        $this->post(route('quotes.store'), $this->quotePayload() + ['ticket_id' => $ticket->id])->assertSessionHasErrors('ticket_id');
        $this->assertDatabaseCount('quotes', 0);
    }
}
