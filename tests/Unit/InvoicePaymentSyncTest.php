<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoicePaymentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentSyncTest extends TestCase
{
    use RefreshDatabase;

    private InvoicePaymentSyncService $service;
    private User $user;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InvoicePaymentSyncService();

        $this->user = User::factory()->create(['role' => 'admin']);

        $this->client = Client::create([
            'name'   => 'Cliente Test',
            'slug'   => 'cliente-test',
            'status' => 'active',
        ]);
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    private function makeInvoice(array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'client_id'  => $this->client->id,
            'created_by' => $this->user->id,
            'number'     => 'INV-' . uniqid(),
            'issue_date' => now()->toDateString(),
            'due_date'   => now()->addDays(30)->toDateString(),
            'status'     => 'issued',
            'currency'   => 'EUR',
            'subtotal'   => 100.00,
            'tax_amount' => 22.00,
            'total'      => 122.00,
            'paid_total' => 0.00,
        ], $overrides));
    }

    private function makePayment(Invoice $invoice, float $amount): Payment
    {
        return Payment::create([
            'invoice_id'   => $invoice->id,
            'client_id'    => $invoice->client_id,
            'created_by'   => $this->user->id,
            'payment_date' => now()->toDateString(),
            'amount'       => $amount,
            'method'       => 'bank_transfer',
        ]);
    }

    // ── test cases ─────────────────────────────────────────────────────────────

    /** Pagamento completo → status 'paid', paid_total = total */
    public function test_full_payment_marks_invoice_as_paid(): void
    {
        $invoice = $this->makeInvoice();
        $this->makePayment($invoice, 122.00);

        $this->service->sync($invoice);

        $invoice->refresh();

        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals('122.00', $invoice->paid_total);
    }

    /** Pagamento parziale → status 'partially_paid' */
    public function test_partial_payment_marks_invoice_as_partially_paid(): void
    {
        $invoice = $this->makeInvoice();
        $this->makePayment($invoice, 50.00);

        $this->service->sync($invoice);

        $invoice->refresh();

        $this->assertEquals('partially_paid', $invoice->status);
        $this->assertEquals('50.00', $invoice->paid_total);
    }

    /** Due pagamenti parziali che sommati coprono il totale → 'paid' */
    public function test_multiple_partial_payments_summing_to_total_mark_as_paid(): void
    {
        $invoice = $this->makeInvoice();
        $this->makePayment($invoice, 72.00);
        $this->makePayment($invoice, 50.00);

        $this->service->sync($invoice);

        $invoice->refresh();

        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals('122.00', $invoice->paid_total);
    }

    /** Nessun pagamento, fattura non scaduta → status rimane 'issued' */
    public function test_no_payment_not_expired_keeps_issued_status(): void
    {
        $invoice = $this->makeInvoice([
            'status'   => 'issued',
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->service->sync($invoice);

        $invoice->refresh();

        $this->assertEquals('issued', $invoice->status);
        $this->assertEquals('0.00', $invoice->paid_total);
    }

    /** Nessun pagamento, fattura scaduta → status 'overdue' */
    public function test_no_payment_expired_marks_invoice_as_overdue(): void
    {
        $invoice = $this->makeInvoice([
            'status'   => 'issued',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->service->sync($invoice);

        $invoice->refresh();

        $this->assertEquals('overdue', $invoice->status);
    }

    /** Fattura 'cancelled' non viene toccata dal sync */
    public function test_cancelled_invoice_is_never_changed(): void
    {
        $invoice = $this->makeInvoice(['status' => 'cancelled']);
        $this->makePayment($invoice, 122.00);

        $this->service->sync($invoice);

        $invoice->refresh();

        // paid_total viene aggiornato ma lo status rimane 'cancelled'
        $this->assertEquals('cancelled', $invoice->status);
        $this->assertEquals('122.00', $invoice->paid_total);
    }

    /** Fattura 'draft' senza pagamenti: lo status non scivola a 'issued' o 'overdue' */
    public function test_draft_invoice_without_payments_stays_draft(): void
    {
        $invoice = $this->makeInvoice([
            'status'   => 'draft',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->service->sync($invoice);

        $invoice->refresh();

        $this->assertEquals('draft', $invoice->status);
    }

    /** Cancellazione di un pagamento parziale → torna a 'issued' se non scaduta */
    public function test_deleting_payment_reverts_to_issued_when_not_expired(): void
    {
        $invoice = $this->makeInvoice([
            'due_date' => now()->addDays(10)->toDateString(),
        ]);
        $payment = $this->makePayment($invoice, 50.00);

        // Prima sync: partially_paid
        $this->service->sync($invoice);
        $invoice->refresh();
        $this->assertEquals('partially_paid', $invoice->status);

        // Cancello il pagamento e risincronizza
        $payment->delete();
        $invoice->unsetRelation('payments');
        $this->service->sync($invoice);

        $invoice->refresh();

        $this->assertEquals('issued', $invoice->status);
        $this->assertEquals('0.00', $invoice->paid_total);
    }

    /** Cancellazione di un pagamento parziale → torna a 'overdue' se scaduta */
    public function test_deleting_payment_reverts_to_overdue_when_expired(): void
    {
        $invoice = $this->makeInvoice([
            'due_date' => now()->subDay()->toDateString(),
        ]);
        $payment = $this->makePayment($invoice, 50.00);

        $this->service->sync($invoice);
        $invoice->refresh();
        $this->assertEquals('partially_paid', $invoice->status);

        $payment->delete();
        $invoice->unsetRelation('payments');
        $this->service->sync($invoice);

        $invoice->refresh();

        $this->assertEquals('overdue', $invoice->status);
    }

    /** Spostamento pagamento da fattura A a fattura B: entrambe aggiornate */
    public function test_moving_payment_between_invoices_updates_both(): void
    {
        $invoiceA = $this->makeInvoice(['number' => 'INV-A', 'due_date' => now()->addDays(30)->toDateString()]);
        $invoiceB = $this->makeInvoice(['number' => 'INV-B', 'due_date' => now()->addDays(30)->toDateString()]);

        $payment = $this->makePayment($invoiceA, 122.00);

        // Sync A → paid
        $this->service->sync($invoiceA);
        $invoiceA->refresh();
        $this->assertEquals('paid', $invoiceA->status);

        // Sposto il pagamento su B
        $payment->update(['invoice_id' => $invoiceB->id]);

        // Risincronizza entrambe
        $invoiceA->unsetRelation('payments');
        $invoiceB->unsetRelation('payments');
        $this->service->sync($invoiceA);
        $this->service->sync($invoiceB);

        $invoiceA->refresh();
        $invoiceB->refresh();

        $this->assertEquals('issued', $invoiceA->status);   // A: nessun pagamento, non scaduta
        $this->assertEquals('0.00', $invoiceA->paid_total);
        $this->assertEquals('paid', $invoiceB->status);     // B: pagamento completo
        $this->assertEquals('122.00', $invoiceB->paid_total);
    }

    /** Pagamento superiore al totale: paid_total > total, status 'paid' */
    public function test_overpayment_marks_as_paid_with_correct_paid_total(): void
    {
        $invoice = $this->makeInvoice();
        $this->makePayment($invoice, 150.00); // 150 > 122

        $this->service->sync($invoice);

        $invoice->refresh();

        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals('150.00', $invoice->paid_total);
    }

    /** Fattura con total = 0: sync non va in paid anche se paid_total >= 0 */
    public function test_zero_total_invoice_does_not_become_paid(): void
    {
        $invoice = $this->makeInvoice([
            'subtotal'   => 0,
            'tax_amount' => 0,
            'total'      => 0,
        ]);

        $this->service->sync($invoice);

        $invoice->refresh();

        // Il condition `$invoiceTotal > 0` impedisce che vada in 'paid'
        $this->assertNotEquals('paid', $invoice->status);
    }
}
