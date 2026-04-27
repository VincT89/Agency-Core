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

class EconomicSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $operativo;

    protected Client $client;
    protected Project $managerProject;
    protected Project $otherProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]);
        $this->manager = User::factory()->create(['role' => UserRole::Administration, 'password_changed_at' => now()]);
        $this->operativo = User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]);

        $this->client = Client::create([
            'name' => 'Acme Corporation',
            'slug' => 'acme-corp',
            'status' => 'active'
        ]);

        $this->managerProject = Project::create([
            'client_id' => $this->client->id,
            'name' => 'Manager Project',
            'slug' => 'manager-project',
            'status' => 'active'
        ]);
        $this->manager->projects()->attach($this->managerProject->id, ['role' => 'manager']);

        $this->otherProject = Project::create([
            'client_id' => $this->client->id,
            'name' => 'Other Project',
            'slug' => 'other-project',
            'status' => 'active'
        ]);
    }

    public function test_developer_cannot_access_summary(): void
    {
        $this->actingAs($this->operativo)
            ->get(route('economic-summary.index'))
            ->assertForbidden();
    }

    public function test_administration_sees_all_metrics(): void
    {
        // Manager's invoice: 1000 total
        Invoice::create([
            'client_id' => $this->client->id,
            'project_id' => $this->managerProject->id,
            'created_by' => $this->admin->id,
            'number' => 'FAT-M1',
            'issue_date' => now()->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 1000,
            'tax_amount' => 0,
            'total' => 1000,
            'paid_total' => 0,
            'status' => 'issued',
        ]);

        // Other's invoice: 500 total
        Invoice::create([
            'client_id' => $this->client->id,
            'project_id' => $this->otherProject->id,
            'created_by' => $this->admin->id,
            'number' => 'FAT-O1',
            'issue_date' => now()->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 500,
            'tax_amount' => 0,
            'total' => 500,
            'paid_total' => 0,
            'status' => 'issued',
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('economic-summary.index'));
            
        $response->assertOk();

        // 1500 in European comma format because Administration sees EVERYTHING
        $response->assertSee('1.500,00'); 
        
        $adminResponse = $this->actingAs($this->admin)
            ->get(route('economic-summary.index'));
        $adminResponse->assertSee('1.500,00');
    }

    public function test_temporal_filter_disconnects_invoice_from_payment_correctly(): void
    {
        // 1. Fattura emessa a Gennaio
        $janInvoice = Invoice::create([
            'client_id' => $this->client->id,
            'project_id' => $this->managerProject->id,
            'created_by' => $this->admin->id,
            'number' => 'FAT-JAN',
            'issue_date' => '2026-01-15',
            'currency' => 'EUR',
            'subtotal' => 2000,
            'tax_amount' => 0,
            'total' => 2000,
            'paid_total' => 500,
            'status' => 'partially_paid',
        ]);

        // 2. Pagamento incassato a Febbraio (per la fattura di Gennaio)
        Payment::create([
            'invoice_id' => $janInvoice->id,
            'client_id' => $this->client->id,
            'project_id' => $this->managerProject->id,
            'created_by' => $this->admin->id,
            'amount' => 500,
            'method' => 'bank_transfer',
            'payment_date' => '2026-02-10',
        ]);

        // CASO A: Filtro GENNAIO 
        // Mi aspetto: Fatturato = 2000, Incassato = 0, Da Incassare = 1500
        $responseJan = $this->actingAs($this->admin)->get(route('economic-summary.index', [
            'from' => '2026-01-01',
            'to' => '2026-01-31'
        ]));
        
        $responseJan->assertOk();
        // Fatturato: '2.000,00'
        $responseJan->assertSee('2.000,00');
        // Incassato: '0,00'
        $responseJan->assertSee('0,00');
        // Residuo Da Incassare: '1.500,00'
        $responseJan->assertSee('1.500,00');

        // CASO B: Filtro FEBBRAIO
        // Mi aspetto: Fatturato = 0, Incassato = 500, Da Incassare = 0 (nessuna fattura emessa a feb)
        $responseFeb = $this->actingAs($this->admin)->get(route('economic-summary.index', [
            'from' => '2026-02-01',
            'to' => '2026-02-28'
        ]));
        
        $responseFeb->assertOk();
        $responseFeb->assertSee('500,00'); // Incasso
        $responseFeb->assertSee('0,00');   // Fatturato
    }
}
