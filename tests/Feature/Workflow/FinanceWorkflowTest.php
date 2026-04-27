<?php

namespace Tests\Feature\Workflow;

use App\Models\{Client, Project, Invoice, Payment, User};
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_module_is_segregated(): void
    {
        $administration = User::factory()->create(['role' => UserRole::Administration]);
        $developer = User::factory()->create(['role' => UserRole::Developer]);

        $client = Client::create(['name' => 'Finance Corp', 'slug' => 'finance-corp', 'status' => 'active']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Big Setup', 'slug' => 'big-setup', 'status' => 'active']);

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'created_by' => $administration->id,
            'number' => 'INV-E2E',
            'issue_date' => today(),
            'due_date' => today()->addDays(5),
            'status' => 'issued',
            'currency' => 'EUR',
            'subtotal' => 1000,
            'tax_amount' => 220,
            'total' => 1220,
            'paid_total' => 0,
        ]);

        Payment::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'invoice_id' => $invoice->id,
            'created_by' => $administration->id,
            'amount' => 1220,
            'payment_date' => today(),
            'payment_method' => 'bank_transfer',
        ]);

        // Accesso Administration a Invoice
        $responseAdmin = $this->actingAs($administration)->get(route('invoices.show', $invoice));
        $responseAdmin->assertOk();
        $responseAdmin->assertSee('INV-E2E');

        // Accesso Developer a Invoice
        $responseDev = $this->actingAs($developer)->get(route('invoices.show', $invoice));
        $responseDev->assertForbidden();

        // Accesso Developer alla dashboard Finance
        $responseEco = $this->actingAs($developer)->get(route('economic-summary.index'));
        $responseEco->assertForbidden();
    }
}
