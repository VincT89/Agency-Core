<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Integrations\Nextcloud\NextcloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientRegistryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $commercial;
    private User $otherCommercial;
    private Client $client;
    private Client $foreignClient;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Notification::fake();
        $this->admin = $this->user(UserRole::Admin);
        $this->commercial = $this->user(UserRole::Commercial);
        $this->otherCommercial = $this->user(UserRole::Commercial);
        $this->actingAs($this->admin);
        $this->client = Client::factory()->create([
            'name' => 'Cliente proprio dimostrativo', 'commercial_user_id' => $this->commercial->id,
            'email' => 'proprio@example.test', 'vat_number' => 'DEMO-PROPRIO',
            'notes' => 'Nota interna riservata dimostrativa', 'status' => 'inactive',
            'nextcloud_folder_name' => 'cartella-interna-demo', 'activity_description' => 'Descrizione marketing demo',
        ]);
        $this->foreignClient = Client::factory()->create([
            'name' => 'Cliente altrui riservato', 'commercial_user_id' => $this->otherCommercial->id,
            'email' => 'altrui@example.test', 'vat_number' => 'DEMO-ALTRUI',
        ]);
        $this->mock(NextcloudService::class)->shouldNotReceive('ensureClientMediaDirectories');
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active', 'password_changed_at' => now()]);
    }

    public function test_commercial_registers_a_complete_client_without_a_ticket_or_document_folder(): void
    {
        $this->actingAs($this->commercial)->get(route('clients.create'))->assertOk()
            ->assertSee('Codice fiscale')->assertSee('Email fatturazione')->assertSee('Note commerciali')
            ->assertDontSee('name="commercial_user_id"', false)->assertDontSee('name="notes"', false)
            ->assertDontSee('name="nextcloud_folder_name"', false);

        $this->post(route('clients.store'), [
            'name' => 'Anagrafica autonoma dimostrativa', 'company_name' => 'Società dimostrativa',
            'reference_person' => 'Referente dimostrativo', 'email' => ' NUOVO@EXAMPLE.TEST ', 'phone' => '0000000000',
            'vat_number' => ' demo-nuovo ', 'tax_code' => ' demo-cf ', 'billing_email' => ' FATTURE@EXAMPLE.TEST ',
            'pec' => ' PEC@EXAMPLE.TEST ', 'sdi_code' => ' abc1234 ', 'address' => 'Via dimostrativa',
            'city' => 'Comune dimostrativo', 'postal_code' => '00000', 'province' => ' na ',
            'country_code' => ' it ', 'country' => 'Italia', 'commercial_notes' => "Prima nota\nSeconda nota",
        ])->assertSessionHasNoErrors()->assertRedirect();

        $created = Client::where('name', 'Anagrafica autonoma dimostrativa')->sole();
        $this->assertDatabaseHas('clients', [
            'id' => $created->id, 'commercial_user_id' => $this->commercial->id, 'status' => 'active',
            'email' => 'nuovo@example.test', 'vat_number' => 'DEMO-NUOVO', 'tax_code' => 'DEMO-CF',
            'billing_email' => 'fatture@example.test', 'pec' => 'pec@example.test', 'sdi_code' => 'ABC1234',
            'province' => 'NA', 'country_code' => 'IT', 'nextcloud_folder_name' => null,
            'commercial_notes' => "Prima nota\nSeconda nota",
        ]);
        foreach (['tickets', 'projects', 'quotes'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->get(route('clients.show', $created))->assertOk()->assertSee('DEMO-CF')->assertSee('ABC1234');
        $this->getJson(route('api.clients.search', ['q' => 'Società dimostrativa']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $created->id);
        $this->get(route('tickets.create', ['type' => 'quote', 'client_id' => $created->id]))
            ->assertOk()->assertSee($created->name)->assertSee('Servizi richiesti');
        foreach ([$this->admin, $this->user(UserRole::Administration)] as $reader) {
            $this->actingAs($reader)->get(route('clients.index'))->assertOk()->assertSee($created->name);
            $this->get(route('clients.show', $created))->assertOk()->assertSee('Prima nota')->assertSee('fatture@example.test');
        }
    }

    public function test_commercial_updates_only_the_registry_and_existing_internal_settings_are_preserved(): void
    {
        $this->actingAs($this->commercial)->get(route('clients.edit', $this->client))->assertOk()
            ->assertDontSee($this->client->notes)->assertDontSee($this->client->nextcloud_folder_name);
        $this->patch(route('clients.update', $this->client), [
            'name' => 'Nome aggiornato dimostrativo', 'email' => $this->client->email,
            'vat_number' => $this->client->vat_number, 'tax_code' => 'demo-modificato',
            'commercial_notes' => 'Informazioni commerciali aggiornate',
        ])->assertSessionHasNoErrors()->assertRedirect(route('clients.show', $this->client));

        $this->assertDatabaseHas('clients', [
            'id' => $this->client->id, 'name' => 'Nome aggiornato dimostrativo', 'tax_code' => 'DEMO-MODIFICATO',
            'commercial_user_id' => $this->commercial->id, 'status' => 'inactive', 'notes' => $this->client->notes,
            'nextcloud_folder_name' => 'cartella-interna-demo', 'activity_description' => 'Descrizione marketing demo',
        ]);
        $this->get(route('clients.show', $this->client))->assertOk()->assertSee('Informazioni commerciali aggiornate')
            ->assertDontSee($this->client->notes)->assertDontSee('cartella-interna-demo');
        $this->assertFalse($this->commercial->can('update', $this->client));
    }

    public function test_protected_fields_cannot_be_written_by_commercial_on_create_or_update(): void
    {
        $before = $this->client->fresh()->getAttributes();
        $this->actingAs($this->commercial);
        foreach ([
            'commercial_user_id' => $this->otherCommercial->id, 'notes' => 'Sovrascrittura interna',
            'status' => 'active', 'activity_description' => 'Sovrascrittura marketing',
            'nextcloud_folder_name' => 'altra-cartella', 'logo' => 'logo.png',
        ] as $field => $value) {
            $payload = ['name' => 'Tentativo non autorizzato', $field => $value];
            $this->postJson(route('clients.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->patchJson(route('clients.update', $this->client), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame($before, $this->client->fresh()->getAttributes());
        $this->assertDatabaseCount('clients', 2);
    }

    public function test_commercial_cannot_read_or_edit_foreign_or_unassigned_clients(): void
    {
        $unassigned = Client::factory()->create(['commercial_user_id' => null]);
        $this->actingAs($this->commercial);
        foreach ([$this->foreignClient, $unassigned] as $client) {
            $this->get(route('clients.show', $client))->assertForbidden();
            $this->get(route('clients.edit', $client))->assertForbidden();
            $this->patchJson(route('clients.update', $client), ['name' => 'Modifica vietata'])->assertForbidden();
        }
        $this->delete(route('clients.destroy', $this->client))->assertForbidden();
        $this->get(route('clients.index'))->assertOk()->assertSee($this->client->name)
            ->assertDontSee($this->foreignClient->name)->assertDontSee($unassigned->name);
    }

    public function test_email_and_vat_duplicates_are_rejected_on_both_creation_and_update_without_leaking_details(): void
    {
        $this->actingAs($this->commercial);
        foreach (['email' => $this->foreignClient->email, 'vat_number' => $this->foreignClient->vat_number] as $field => $value) {
            $payload = ['name' => $this->client->name, $field => " {$value} "];
            $this->postJson(route('clients.store'), $payload)->assertUnprocessable()
                ->assertJsonValidationErrors($field)->assertDontSee($this->foreignClient->name);
            $this->patchJson(route('clients.update', $this->client), $payload)->assertUnprocessable()
                ->assertJsonValidationErrors($field)->assertDontSee($this->foreignClient->name);
        }
        $this->from(route('clients.edit', $this->client))->patch(route('clients.update', $this->client), [
            'name' => 'Nome da conservare nel modulo', 'email' => $this->foreignClient->email,
        ])->assertRedirect(route('clients.edit', $this->client))->assertSessionHasErrors('email');
        $this->get(route('clients.edit', $this->client))->assertOk()->assertSee('Nome da conservare nel modulo');
        $this->assertSame($this->client->email, $this->client->fresh()->email);
        $this->assertDatabaseCount('clients', 2);
    }

    public function test_registry_and_autocomplete_searches_match_company_contacts_and_tax_details_within_ownership(): void
    {
        $fields = [
            'company_name' => 'SocietaRicercabile', 'reference_person' => 'ReferenteRicercabile',
            'phone' => '0001234567', 'tax_code' => 'CF-DEMO-RICERCA',
        ];
        $this->client->update($fields);
        $this->foreignClient->update($fields);
        $this->actingAs($this->commercial);
        foreach ($fields as $value) {
            $this->get(route('clients.index', ['search' => strtolower($value)]))->assertOk()
                ->assertSee($this->client->name)->assertDontSee($this->foreignClient->name);
            $this->getJson(route('api.clients.search', ['q' => strtolower($value)]))->assertOk()
                ->assertJsonCount(1)->assertJsonPath('0.id', $this->client->id);
        }
        $this->get(route('clients.index', ['search' => 'nessuna-corrispondenza-demo']))->assertOk()->assertSee('Nessun cliente trovato');
    }

    public function test_client_history_includes_closed_tasks_and_project_summaries_without_internal_or_foreign_data(): void
    {
        $project = Project::factory()->create(['client_id' => $this->client->id, 'description' => 'Descrizione progetto riservata']);
        $done = Task::factory()->create([
            'project_id' => $project->id, 'status' => 'done', 'completed_at' => now(),
            'title' => 'Lavorazione conclusa dimostrativa', 'notes' => 'Task interna riservata',
        ]);
        $ticket = Ticket::create([
            'client_id' => $this->client->id, 'created_by' => $this->admin->id,
            'title' => 'Ticket interno aperto da admin', 'status' => 'open', 'type' => 'support', 'priority' => 'medium',
        ]);
        $ticketTask = Task::factory()->create(['project_id' => null, 'ticket_id' => $ticket->id, 'title' => 'Task da ticket del cliente']);
        $foreignProject = Project::factory()->create(['client_id' => $this->foreignClient->id]);
        $foreignTask = Task::factory()->create(['project_id' => $foreignProject->id]);

        $this->actingAs($this->commercial)->get(route('clients.show', $this->client))->assertOk()
            ->assertSee($done->title)->assertSee('Completata')->assertSee($ticketTask->title)->assertSee($project->name)
            ->assertDontSee('Task interna riservata')->assertDontSee('Descrizione progetto riservata')
            ->assertDontSee($ticket->title)->assertDontSee($foreignTask->title)->assertDontSee($foreignProject->name)
            ->assertDontSee('href="'.route('projects.show', $project).'"', false);
        $this->get(route('tasks.show', $done))->assertOk();
        $this->get(route('projects.show', $project))->assertNotFound();
        $this->actingAs($this->admin)->get(route('clients.show', $this->client))->assertOk()
            ->assertSee($done->title)->assertSee($ticketTask->title);
    }

    public function test_admin_and_administration_create_without_nextcloud_and_keep_existing_edit_permissions(): void
    {
        $administration = $this->user(UserRole::Administration);
        foreach ([$this->admin, $administration] as $user) {
            $this->actingAs($user)->post(route('clients.store'), ['name' => 'Anagrafica autonoma '.$user->id])
                ->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->actingAs($administration)->get(route('clients.show', $this->client))->assertOk()
            ->assertDontSee('href="'.route('clients.edit', $this->client).'"', false);
        $this->get(route('clients.edit', $this->client))->assertForbidden();
        $this->patchJson(route('clients.update', $this->client), ['name' => 'Vietato', 'status' => 'active'])->assertForbidden();
        $this->actingAs($this->admin)->patch(route('clients.update', $this->client), [
            'name' => 'Modifica amministratore', 'status' => 'active', 'commercial_notes' => 'Nota condivisa con commerciale',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($this->commercial)->get(route('clients.show', $this->client))->assertOk()
            ->assertSee('Nota condivisa con commerciale');
    }

    public function test_malformed_registry_and_search_inputs_fail_validation_instead_of_causing_server_errors(): void
    {
        $this->actingAs($this->commercial)->postJson(route('clients.store'), [
            'name' => 'Dati malformati dimostrativi', 'email' => ['invalid'], 'vat_number' => ['invalid'],
            'country_code' => 'ITA', 'sdi_code' => 'A!',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'vat_number', 'country_code', 'sdi_code']);
        $this->getJson(route('clients.index', ['search' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->getJson(route('api.clients.search', ['q' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->assertDatabaseCount('clients', 2);
    }
}
