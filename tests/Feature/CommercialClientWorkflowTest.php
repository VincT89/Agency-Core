<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommercialClientWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $commercial;
    private User $otherCommercial;
    private Client $ownClient;
    private Client $otherClient;
    private Client $unassignedClient;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Notification::fake();
        $this->admin = $this->user(UserRole::Admin);
        $this->commercial = $this->user(UserRole::Commercial);
        $this->otherCommercial = $this->user(UserRole::Commercial);
        $this->actingAs($this->admin);
        $this->ownClient = Client::factory()->create(['name' => 'Cliente proprio dimostrativo', 'commercial_user_id' => $this->commercial->id]);
        $this->otherClient = Client::factory()->create(['name' => 'Cliente altrui dimostrativo', 'commercial_user_id' => $this->otherCommercial->id, 'vat_number' => 'DEMO-OTHER']);
        $this->unassignedClient = Client::factory()->create(['name' => 'Cliente generale dimostrativo']);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active', 'password_changed_at' => now()]);
    }

    private function ticketPayload(int $clientId, array $attributes = []): array
    {
        return array_merge([
            'client_id' => $clientId, 'title' => 'Preventivo dimostrativo',
            'type' => 'quote', 'priority' => 'medium', 'project_id' => null,
        ], $attributes);
    }

    public function test_commercial_creates_a_client_inline_then_opens_a_ticket_and_admin_sees_the_same_client(): void
    {
        $response = $this->actingAs($this->commercial)->postJson(route('api.clients.quick-store'), [
            'name' => 'Nuovo cliente dimostrativo', 'company_name' => 'Azienda dimostrativa',
            'email' => 'nuovo-cliente@example.test', 'phone' => '0000000000',
        ])->assertCreated();
        $clientId = $response->json('id');
        $this->assertDatabaseHas('clients', [
            'id' => $clientId, 'commercial_user_id' => $this->commercial->id,
            'status' => 'active', 'nextcloud_folder_name' => null,
        ]);
        $this->post(route('tickets.store'), $this->ticketPayload($clientId))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->commercial->id, Ticket::query()->sole()->created_by);
        $this->assertSame($clientId, Ticket::query()->sole()->client_id);
        $this->getJson(route('api.clients.search', ['q' => 'Nuovo cliente']))->assertJsonCount(1)->assertJsonPath('0.id', $clientId);
        $this->actingAs($this->otherCommercial)->getJson(route('api.clients.search', ['q' => 'Nuovo cliente']))->assertExactJson([]);
        $this->actingAs($this->admin)->get(route('clients.index'))->assertOk()->assertSee('Nuovo cliente dimostrativo');
        $this->assertDatabaseCount('clients', 4);
    }

    public function test_search_only_returns_owned_clients_and_ticket_form_reuses_quick_creation(): void
    {
        $this->actingAs($this->commercial)->getJson(route('api.clients.search', ['q' => 'Cliente']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $this->ownClient->id);
        $this->get(route('tickets.create'))->assertOk()->assertSee('Crea nuovo cliente')
            ->assertDontSee($this->otherClient->name)->assertDontSee($this->unassignedClient->name);
    }

    public function test_foreign_clients_cannot_be_selected_or_have_their_projects_enumerated(): void
    {
        $ownProject = Project::factory()->create(['client_id' => $this->ownClient->id, 'status' => 'active']);
        $otherProject = Project::factory()->create(['client_id' => $this->otherClient->id, 'status' => 'active']);
        $otherProject->users()->attach($this->commercial->id, ['role' => 'member']);
        $this->actingAs($this->commercial)->getJson(route('tickets.client-projects', $this->ownClient))
            ->assertOk()->assertExactJson([['id' => $ownProject->id, 'name' => $ownProject->name]]);
        foreach ([$this->otherClient, $this->unassignedClient] as $client) {
            $this->getJson(route('tickets.client-projects', $client))->assertForbidden();
            $this->postJson(route('tickets.store'), $this->ticketPayload($client->id))
                ->assertUnprocessable()->assertJsonValidationErrors('client_id');
        }
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_duplicate_email_or_vat_is_rejected_without_assigning_someone_elses_client(): void
    {
        $this->actingAs($this->commercial);
        foreach (['email' => $this->otherClient->email, 'vat_number' => $this->otherClient->vat_number] as $field => $value) {
            $response = $this->postJson(route('api.clients.quick-store'), ['name' => 'Tentativo duplicato dimostrativo', $field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
            $response->assertDontSee($this->otherClient->name);
        }
        $this->assertSame($this->otherCommercial->id, $this->otherClient->fresh()->commercial_user_id);
        $this->assertDatabaseCount('clients', 3);
    }

    public function test_quick_creation_cannot_forge_ownership_and_does_not_grant_full_client_management(): void
    {
        $this->actingAs($this->commercial)->postJson(route('api.clients.quick-store'), [
            'name' => 'Cliente con proprietario falsificato', 'commercial_user_id' => $this->otherCommercial->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('commercial_user_id');
        $this->get(route('clients.index'))->assertForbidden();
        $this->get(route('clients.create'))->assertForbidden();
        $this->get(route('clients.show', $this->ownClient))->assertForbidden();
        $this->postJson(route('clients.store'), ['name' => 'Creazione completa vietata'])->assertForbidden();
        $this->patchJson(route('clients.update', $this->ownClient), ['name' => 'Modifica vietata'])->assertForbidden();
        $this->assertDatabaseCount('clients', 3);
    }

    public function test_admin_can_associate_an_existing_client_and_reassign_the_owner(): void
    {
        $this->patch(route('clients.update', $this->otherClient), [
            'name' => $this->otherClient->name, 'status' => 'active', 'commercial_user_id' => $this->commercial->id,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->commercial->id, $this->otherClient->fresh()->commercial_user_id);
        $this->actingAs($this->commercial)->getJson(route('api.clients.search', ['q' => $this->otherClient->name]))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $this->otherClient->id);
        $this->actingAs($this->otherCommercial)->getJson(route('api.clients.search', ['q' => $this->otherClient->name]))
            ->assertExactJson([]);
    }

    public function test_only_admin_can_change_ownership_and_can_only_choose_a_commercial(): void
    {
        $developer = $this->user(UserRole::Developer);
        $this->patchJson(route('clients.update', $this->ownClient), [
            'name' => $this->ownClient->name, 'status' => 'active', 'commercial_user_id' => $developer->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('commercial_user_id');
        $manager = $this->user(UserRole::OperationsManager);
        $this->actingAs($manager)->patchJson(route('clients.update', $this->ownClient), [
            'name' => $this->ownClient->name, 'status' => 'active', 'commercial_user_id' => $this->otherCommercial->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('commercial_user_id');
        $this->assertSame($this->commercial->id, $this->ownClient->fresh()->commercial_user_id);
    }

    public function test_owner_can_be_removed_only_by_admin_and_inactive_current_owner_is_preserved(): void
    {
        $this->commercial->update(['status' => 'inactive']);
        $this->get(route('clients.edit', $this->ownClient))->assertOk()->assertSee('Commerciale di riferimento');
        $this->patch(route('clients.update', $this->ownClient), [
            'name' => $this->ownClient->name, 'status' => 'active', 'commercial_user_id' => $this->commercial->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->commercial->id, $this->ownClient->fresh()->commercial_user_id);
        $this->actingAs($this->user(UserRole::OperationsManager))->patchJson(route('clients.update', $this->ownClient), [
            'name' => $this->ownClient->name, 'status' => 'active', 'commercial_user_id' => null,
        ])->assertUnprocessable()->assertJsonValidationErrors('commercial_user_id');
        $this->actingAs($this->admin)->patch(route('clients.update', $this->ownClient), [
            'name' => $this->ownClient->name, 'status' => 'active', 'commercial_user_id' => null,
        ])->assertSessionHasNoErrors();
        $this->assertNull($this->ownClient->fresh()->commercial_user_id);
        $this->assertDatabaseCount('clients', 3);
    }

    public function test_existing_admin_quick_creation_works_with_the_fields_in_the_shared_component(): void
    {
        $response = $this->postJson(route('api.clients.quick-store'), ['name' => 'Cliente rapido Admin dimostrativo'])->assertCreated();
        $this->assertDatabaseHas('clients', ['id' => $response->json('id'), 'commercial_user_id' => null, 'nextcloud_folder_name' => null]);
        $this->actingAs($this->user(UserRole::Developer))->postJson(route('api.clients.quick-store'), ['name' => 'Creazione non autorizzata'])
            ->assertForbidden();
    }
}
