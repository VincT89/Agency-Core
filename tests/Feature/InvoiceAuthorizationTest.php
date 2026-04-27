<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // ── fixtures ───────────────────────────────────────────────────────────────

    private User $admin;
    private User $manager;
    private User $otherManager;
    private User $operativo;
    private Client $client;
    private Project $project;
    private Project $otherProject;
    private Invoice $invoice;        // fattura sul progetto per amministrazione
    private Invoice $draftInvoice;   // fattura draft (cancellabile)
    private Invoice $foreignInvoice; // fattura su progetto per l'altra amministrazione

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin        = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]);
        $this->manager      = User::factory()->create(['role' => UserRole::Administration, 'password_changed_at' => now()]);
        $this->otherManager = User::factory()->create(['role' => UserRole::Administration, 'password_changed_at' => now()]);
        $this->operativo    = User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]);

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

        // Manager assegnato solo a $project
        $this->project->users()->attach($this->manager->id, [
            'role'              => 'lead',
            'assignment_status' => 'active',
            'assigned_at'       => now(),
        ]);

        $this->invoice = Invoice::create([
            'client_id'  => $this->client->id,
            'project_id' => $this->project->id,
            'created_by' => $this->manager->id,
            'number'     => 'FAT-001',
            'issue_date' => now()->toDateString(),
            'due_date'   => now()->addDays(30)->toDateString(),
            'status'     => 'issued',
            'currency'   => 'EUR',
            'subtotal'   => 1000.00,
            'tax_amount' => 220.00,
            'total'      => 1220.00,
            'paid_total' => 0.00,
        ]);

        $this->draftInvoice = Invoice::create([
            'client_id'  => $this->client->id,
            'project_id' => $this->project->id,
            'created_by' => $this->manager->id,
            'number'     => 'FAT-002',
            'issue_date' => now()->toDateString(),
            'status'     => 'draft',
            'currency'   => 'EUR',
            'subtotal'   => 500.00,
            'tax_amount' => 110.00,
            'total'      => 610.00,
            'paid_total' => 0.00,
        ]);

        $this->foreignInvoice = Invoice::create([
            'client_id'  => $this->client->id,
            'project_id' => $this->otherProject->id,
            'created_by' => $this->otherManager->id,
            'number'     => 'FAT-003',
            'issue_date' => now()->toDateString(),
            'status'     => 'issued',
            'currency'   => 'EUR',
            'subtotal'   => 800.00,
            'tax_amount' => 176.00,
            'total'      => 976.00,
            'paid_total' => 0.00,
        ]);
    }

    // ── index ──────────────────────────────────────────────────────────────────

    public function test_admin_can_see_invoice_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('invoices.index'))
            ->assertOk();
    }

    public function test_administration_can_see_invoice_index(): void
    {
        $this->actingAs($this->manager)
            ->get(route('invoices.index'))
            ->assertOk();
    }

    public function test_developer_cannot_see_invoice_index(): void
    {
        $this->actingAs($this->operativo)
            ->get(route('invoices.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_invoice_index(): void
    {
        $this->get(route('invoices.index'))
            ->assertRedirect(route('login'));
    }

    // ── show ───────────────────────────────────────────────────────────────────

    public function test_admin_can_view_any_invoice(): void
    {
        $this->actingAs($this->admin)
            ->get(route('invoices.show', $this->invoice))
            ->assertOk();
    }

    public function test_administration_can_view_invoice_in_own_project(): void
    {
        $this->actingAs($this->manager)
            ->get(route('invoices.show', $this->invoice))
            ->assertOk();
    }

    public function test_administration_can_view_invoice_outside_own_projects(): void
    {
        $this->actingAs($this->manager)
            ->get(route('invoices.show', $this->foreignInvoice))
            ->assertOk();
    }

    public function test_developer_cannot_view_invoice_not_created_by_self(): void
    {
        $this->actingAs($this->operativo)
            ->get(route('invoices.show', $this->invoice))
            ->assertForbidden();
    }

    public function test_developer_cannot_view_invoice_created_by_self(): void
    {
        $ownInvoice = Invoice::create([
            'client_id'  => $this->client->id,
            'project_id' => $this->project->id,
            'created_by' => $this->operativo->id,
            'number'     => 'FAT-OWN',
            'issue_date' => now()->toDateString(),
            'status'     => 'draft',
            'currency'   => 'EUR',
            'subtotal'   => 100.00,
            'tax_amount' => 22.00,
            'total'      => 122.00,
            'paid_total' => 0.00,
        ]);

        $this->actingAs($this->operativo)
            ->get(route('invoices.show', $ownInvoice))
            ->assertForbidden();
    }

    // ── create ─────────────────────────────────────────────────────────────────

    public function test_admin_can_access_invoice_create_form(): void
    {
        $this->actingAs($this->admin)
            ->get(route('invoices.create'))
            ->assertOk();
    }

    public function test_administration_can_access_invoice_create_form(): void
    {
        $this->actingAs($this->manager)
            ->get(route('invoices.create'))
            ->assertOk();
    }

    public function test_developer_cannot_access_invoice_create_form(): void
    {
        $this->actingAs($this->operativo)
            ->get(route('invoices.create'))
            ->assertForbidden();
    }

    // ── store ──────────────────────────────────────────────────────────────────

    public function test_developer_cannot_create_invoice(): void
    {
        $this->actingAs($this->operativo)
            ->post(route('invoices.store'), [
                'client_id'  => $this->client->id,
                'number'     => 'FAT-NEW',
                'issue_date' => now()->toDateString(),
                'status'     => 'draft',
                'currency'   => 'EUR',
                'subtotal'   => 100,
                'tax_amount' => 22,
            ])
            ->assertForbidden();
    }

    public function test_administration_can_create_invoice(): void
    {
        $this->actingAs($this->manager)
            ->post(route('invoices.store'), [
                'client_id'  => $this->client->id,
                'project_id' => $this->project->id,
                'number'     => 'FAT-NEW-M',
                'issue_date' => now()->toDateString(),
                'status'     => 'draft',
                'currency'   => 'EUR',
                'subtotal'   => 500,
                'tax_amount' => 110,
                'paid_total' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', ['number' => 'FAT-NEW-M']);
    }

    public function test_administration_can_create_invoice_outside_own_project(): void
    {
        $this->actingAs($this->manager)
            ->post(route('invoices.store'), [
                'client_id'  => $this->client->id,
                'project_id' => $this->otherProject->id, // Fuori perimetro ma finance fa passare
                'number'     => 'FAT-NEW-M-OUT',
                'issue_date' => now()->toDateString(),
                'status'     => 'draft',
                'currency'   => 'EUR',
                'subtotal'   => 500,
                'tax_amount' => 110,
                'paid_total' => 0,
            ])
            ->assertRedirect();
            
        $this->assertDatabaseHas('invoices', ['number' => 'FAT-NEW-M-OUT']);
    }

    public function test_requires_project_id_for_invoice_creation(): void
    {
        $this->actingAs($this->admin)
            ->post(route('invoices.store'), [
                'client_id'  => $this->client->id,
                'number'     => 'FAT-NO-PROJ',
                'issue_date' => now()->toDateString(),
                'status'     => 'draft',
                'subtotal'   => 500,
                'tax_amount' => 100,
                'currency'   => 'EUR',
            ])
            ->assertSessionHasErrors('project_id'); // Fallirà validazione
    }

    public function test_denies_invoice_if_project_does_not_belong_to_client(): void
    {
        $otherClient = \App\Models\Client::create(['name' => 'Altro', 'slug' => 'altro', 'status' => 'active']);
        
        $this->actingAs($this->admin)
            ->post(route('invoices.store'), [
                'client_id'  => $otherClient->id,
                'project_id' => $this->project->id, // questo project_id è legato a $this->client
                'number'     => 'FAT-MISMATCH',
                'issue_date' => now()->toDateString(),
                'status'     => 'draft',
                'subtotal'   => 500,
                'tax_amount' => 100,
                'currency'   => 'EUR',
            ])
            ->assertSessionHasErrors('project_id');
    }

    // ── update ─────────────────────────────────────────────────────────────────

    public function test_admin_can_update_any_invoice(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('invoices.update', $this->invoice), [
                'client_id'  => $this->client->id,
                'project_id' => $this->project->id,
                'number'     => 'FAT-001',
                'issue_date' => now()->toDateString(),
                'status'     => 'issued',
                'currency'   => 'EUR',
                'subtotal'   => 1000,
                'tax_amount' => 220,
                'paid_total' => 0,
            ])
            ->assertRedirect(route('invoices.show', $this->invoice));
    }

    public function test_cannot_remove_project_id_on_update(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('invoices.update', $this->invoice), [
                'client_id'  => $this->client->id,
                'project_id' => null, // Tentativo di rimozione
                'number'     => 'FAT-001-MOD',
                'issue_date' => now()->toDateString(),
                'status'     => 'issued',
                'currency'   => 'EUR',
                'subtotal'   => 1000,
                'tax_amount' => 220,
                'paid_total' => 0,
            ])
            ->assertSessionHasErrors('project_id');
    }

    public function test_residual_accessor_is_consistent(): void
    {
        $this->invoice->update([
            'subtotal'   => 1000,
            'tax_amount' => 220,
            'total'      => 1220,
            'paid_total' => 500
        ]);
        $this->assertEquals(720, $this->invoice->residual);

        $this->invoice->update(['paid_total' => 1220]);
        $this->assertEquals(0, $this->invoice->residual);
        
        $this->invoice->update(['paid_total' => 1500]);
        $this->assertEquals(0, $this->invoice->residual);
    }

    public function test_administration_can_update_invoice_in_own_project(): void
    {
        $this->actingAs($this->manager)
            ->patch(route('invoices.update', $this->invoice), [
                'client_id'  => $this->client->id,
                'project_id' => $this->project->id,
                'number'     => 'FAT-001',
                'issue_date' => now()->toDateString(),
                'status'     => 'issued',
                'currency'   => 'EUR',
                'subtotal'   => 1100,
                'tax_amount' => 242,
                'paid_total' => 0,
            ])
            ->assertRedirect(route('invoices.show', $this->invoice));
    }

    public function test_administration_can_update_invoice_outside_own_projects(): void
    {
        $this->actingAs($this->manager)
            ->patch(route('invoices.update', $this->foreignInvoice), [
                'client_id'  => $this->client->id,
                'project_id' => $this->otherProject->id,
                'number'     => 'FAT-003',
                'issue_date' => now()->toDateString(),
                'status'     => 'issued',
                'currency'   => 'EUR',
                'subtotal'   => 800,
                'tax_amount' => 176,
                'paid_total' => 0,
            ])
            ->assertRedirect(route('invoices.show', $this->foreignInvoice));
    }

    public function test_developer_cannot_update_any_invoice(): void
    {
        $this->actingAs($this->operativo)
            ->patch(route('invoices.update', $this->invoice), [
                'client_id'  => $this->client->id,
                'project_id' => $this->project->id,
                'number'     => 'FAT-001',
                'issue_date' => now()->toDateString(),
                'status'     => 'issued',
                'currency'   => 'EUR',
                'subtotal'   => 1000,
                'tax_amount' => 220,
                'paid_total' => 0,
            ])
            ->assertForbidden();
    }

    // ── delete ─────────────────────────────────────────────────────────────────

    public function test_admin_can_delete_draft_invoice(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('invoices.destroy', $this->draftInvoice))
            ->assertRedirect(route('invoices.index'));

        $this->assertDatabaseMissing('invoices', ['id' => $this->draftInvoice->id]);
    }

    public function test_administration_can_delete_draft_invoice_in_own_project(): void
    {
        $this->actingAs($this->manager)
            ->delete(route('invoices.destroy', $this->draftInvoice))
            ->assertRedirect(route('invoices.index'));

        $this->assertDatabaseMissing('invoices', ['id' => $this->draftInvoice->id]);
    }

    public function test_administration_cannot_delete_issued_invoice(): void
    {
        // La policy blocca la cancellazione di fatture non in stato 'draft'
        $this->actingAs($this->manager)
            ->delete(route('invoices.destroy', $this->invoice)) // status: issued
            ->assertForbidden();
    }

    public function test_administration_can_delete_draft_invoice_outside_own_projects(): void
    {
        $foreignDraft = Invoice::create([
            'client_id'  => $this->client->id,
            'project_id' => $this->otherProject->id,
            'created_by' => $this->otherManager->id,
            'number'     => 'FAT-FOREIGN-DRAFT',
            'issue_date' => now()->toDateString(),
            'status'     => 'draft',
            'currency'   => 'EUR',
            'subtotal'   => 200.00,
            'tax_amount' => 44.00,
            'total'      => 244.00,
            'paid_total' => 0.00,
        ]);

        $this->actingAs($this->manager)
            ->delete(route('invoices.destroy', $foreignDraft))
            ->assertRedirect(route('invoices.index'));

        $this->assertDatabaseMissing('invoices', ['id' => $foreignDraft->id]);
    }

    public function test_developer_cannot_delete_any_invoice(): void
    {
        $this->actingAs($this->operativo)
            ->delete(route('invoices.destroy', $this->draftInvoice))
            ->assertForbidden();
    }
}
