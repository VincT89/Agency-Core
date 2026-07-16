<?php

namespace Tests\Feature\E2E;

use App\Models\User;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAndPaymentCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_invoice_and_register_payment()
    {
        $this->withoutExceptionHandling();
        
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $client = Client::factory()->create();
        $project = \App\Models\Project::factory()->create(['client_id' => $client->id]);

        // 1. Create Invoice
        $responseInvoice = $this->actingAs($admin)->post('/invoices', [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'number' => 'INV-2024-001',
            'issue_date' => now()->format('Y-m-d'),
            'status' => 'issued',
            'currency' => 'EUR',
            'subtotal' => 1000.00,
            'tax_amount' => 220.00,
            'paid_total' => 0.00,
            'items' => [
                [
                    'description' => 'Servizio fotografico',
                    'quantity' => 1,
                    'unit_price' => 1000.00,
                ]
            ]
        ]);

        $responseInvoice->assertRedirect();
        
        $invoice = Invoice::where('number', 'INV-2024-001')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(1220.00, $invoice->total);

        // 2. Register Payment
        $responsePayment = $this->actingAs($admin)->post('/payments', [
            'invoice_id' => $invoice->id,
            'payment_date' => now()->format('Y-m-d'),
            'amount' => 1220.00,
            'method' => 'bank_transfer',
            'reference' => 'CRO123456789',
        ]);

        $responsePayment->assertRedirect();

        $payment = Payment::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(1220.00, $payment->amount);
    }
}
