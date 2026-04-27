<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $operativo;
    protected User $staff;

    protected Client $client;
    protected Project $project;
    protected Project $otherProject;

    protected Invoice $invoice;
    protected Invoice $otherInvoice;
    protected Payment $payment;
    
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Utenti
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]);
        $this->manager = User::factory()->create(['role' => UserRole::Administration, 'password_changed_at' => now()]);
        $this->operativo = User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]);

        // 2. Clienti e Progetti
        $this->client = Client::create([
            'name' => 'Test Client', 
            'slug' => 'test-client', 
            'status' => 'active'
        ]);
        
        $this->project = Project::create([
            'client_id' => $this->client->id, 
            'name' => 'Project Alpha', 
            'slug' => 'project-alpha', 
            'status' => 'active'
        ]);
        $this->otherProject = Project::create([
            'client_id' => $this->client->id, 
            'name' => 'Project Beta', 
            'slug' => 'project-beta', 
            'status' => 'active'
        ]);

        // Associa il manager solo al primo progetto
        $this->project->users()->attach($this->manager->id, ['role' => 'manager']);

        // 3. Fatture Associate
        $this->invoice = Invoice::create([
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'created_by' => $this->admin->id,
            'number' => 'FAT-001',
            'issue_date' => now()->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 1000,
            'tax_amount' => 0,
            'total' => 1000,
            'paid_total' => 0,
            'status' => 'issued',
        ]);

        $this->otherInvoice = Invoice::create([
            'client_id' => $this->client->id,
            'project_id' => $this->otherProject->id,
            'number' => 'FAT-002',
            'issue_date' => now()->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 500,
            'tax_amount' => 0,
            'created_by' => $this->admin->id,
            'total' => 500,
            'paid_total' => 0,
            'status' => 'issued',
        ]);

        // 4. Pagamento Base
        $this->payment = Payment::create([
            'invoice_id' => $this->invoice->id,
            'client_id' => $this->invoice->client_id,
            'project_id' => $this->invoice->project_id,
            'created_by' => $this->admin->id,
            'amount' => 100,
            'method' => 'bank_transfer',
            'payment_date' => now()->toDateString(),
        ]);
        
        // Sincronizza totale pagato finto per test update/delete 
        $this->invoice->update(['paid_total' => 100]);
    }

    // ── INDEX / SHOW ──────────────────────────────────────────────────────────

    public function test_admin_can_view_any_payment(): void
    {
        $this->actingAs($this->admin)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('100,00');
    }

    public function test_administration_sees_all_payments_regardless_of_project(): void
    {
        $otherPayment = Payment::create([
            'invoice_id' => $this->otherInvoice->id,
            'client_id' => $this->client->id,
            'project_id' => $this->otherProject->id,
            'amount' => 50,
            'created_by' => $this->admin->id,
            'method' => 'bank_transfer',
            'payment_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('payments.index'))
            ->assertOk();

        // Dovrebbe vederli tutti essendo Finance
        $response->assertSee('100,00'); 
        $response->assertSee('50,00'); 
    }

    public function test_developer_cannot_view_payments(): void
    {
        $this->actingAs($this->operativo)
            ->get(route('payments.index'))
            ->assertForbidden();

        $this->actingAs($this->operativo)
            ->get(route('payments.show', $this->payment))
            ->assertForbidden();
    }

    // ── CREATE / STORE ────────────────────────────────────────────────────────

    public function test_developer_cannot_access_create(): void
    {
        $this->actingAs($this->operativo)
            ->get(route('payments.create'))
            ->assertForbidden();
    }

    public function test_administration_can_create_payment_for_invoice_outside_perimeter(): void
    {
        $this->actingAs($this->manager)
            ->post(route('payments.store'), [
                'invoice_id' => $this->otherInvoice->id,
                'amount' => 10,
                'payment_date' => now()->toDateString(),
                'method' => 'bank_transfer',
            ])
            ->assertRedirect();
    }

    public function test_administration_can_create_payment_for_own_invoice(): void
    {
        $this->actingAs($this->manager)
            ->post(route('payments.store'), [
                'invoice_id' => $this->invoice->id,
                'amount' => 200,
                'payment_date' => now()->toDateString(),
                'method' => 'bank_transfer',
                'notes' => 'Test payment',
            ])
            ->assertRedirect(route('invoices.show', $this->invoice));

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $this->invoice->id,
            'client_id' => $this->client->id,  // Derivato automaticamente
            'project_id' => $this->project->id, // Derivato automaticamente
            'amount' => 200,
        ]);
        
        $this->assertEquals(300.00, $this->invoice->fresh()->paid_total);
    }

    public function test_cannot_overpay_invoice(): void
    {
        // Totale è 1000. Pagato è 100. Residuo 900.
        $this->actingAs($this->admin)
            ->post(route('payments.store'), [
                'invoice_id' => $this->invoice->id,
                'amount' => 901, // Supera
                'payment_date' => now()->toDateString(),
                'method' => 'bank_transfer',
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_ignores_injected_client_or_project(): void
    {
        $fakeClient = Client::create(['name' => 'Fake', 'slug' => 'fake', 'status' => 'active']);
        $fakeProject = Project::create(['client_id' => $fakeClient->id, 'name' => 'Fake P', 'slug' => 'fake-p', 'status' => 'active']);

        $this->actingAs($this->admin)
            ->post(route('payments.store'), [
                'invoice_id' => $this->invoice->id,
                'client_id' => $fakeClient->id, // Injected fake
                'project_id' => $fakeProject->id, // Injected fake
                'amount' => 100,
                'payment_date' => now()->toDateString(),
                'method' => 'bank_transfer',
            ])
            ->assertRedirect(route('invoices.show', $this->invoice)); // Ignora gli extra fields

        // Il record deve usare i dati corretti deritavi dalla fattura, fregandosene dei fakes
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $this->invoice->id,
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'amount' => 100,
        ]);
    }

    // ── EDIT / UPDATE ─────────────────────────────────────────────────────────

    public function test_administration_can_update_payment_outside_parimeter(): void
    {
        $otherPayment = Payment::create([
            'invoice_id' => $this->otherInvoice->id,
            'client_id' => $this->client->id,
            'project_id' => $this->otherProject->id,
            'amount' => 50,
            'created_by' => $this->admin->id,
            'method' => 'bank_transfer',
            'payment_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->manager)
            ->patch(route('payments.update', $otherPayment), [
                'amount' => 100,
                'payment_date' => now()->toDateString(),
                'method' => 'bank_transfer',
            ])
            ->assertRedirect();
    }

    public function test_cannot_change_invoice_id_on_update(): void
    {
        // Se si invia un diverso invoice_id in edit, viene semplicemente ignorato dalle rules
        $this->actingAs($this->admin)
            ->patch(route('payments.update', $this->payment), [
                'invoice_id' => $this->otherInvoice->id, // Proviamo a spostarla
                'amount' => 150,
                'payment_date' => now()->toDateString(),
                'method' => 'bank_transfer',
            ]);

        $this->payment->refresh();
        $this->assertEquals($this->invoice->id, $this->payment->invoice_id); // Non è cambiato
        $this->assertEquals(150, $this->payment->amount); // Ma l'importo è passato
    }

    public function test_cannot_overpay_on_update_excluding_current(): void
    {
        // Totale è 1000. Pagato è 100 (questo payment). 
        // Proviamo ad aggiornare questo payment a 1001, superando il residuo.
        $this->actingAs($this->admin)
            ->patch(route('payments.update', $this->payment), [
                'amount' => 1001,
                'payment_date' => now()->toDateString(),
                'method' => 'bank_transfer',
            ])
            ->assertSessionHasErrors('amount');
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    public function test_admin_can_delete_payment_and_syncs_invoice(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('payments.destroy', $this->payment))
            ->assertRedirect(route('invoices.show', $this->invoice));

        $this->assertDatabaseMissing('payments', ['id' => $this->payment->id]);
        
        $this->assertEquals(0, $this->invoice->fresh()->paid_total);
    }
}
