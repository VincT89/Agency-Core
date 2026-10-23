<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\CorrectBiessediliziaSettlement;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SettlementCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active', 'password_changed_at' => now()]));
        $client = Client::factory()->create(['name' => 'BIESSEDILIZIA S.R.L.', 'company_name' => 'BIESSEDILIZIA S.R.L.']);
        $metadata = ['agency' => ['country_code' => 'IT', 'vat_number' => '01234567897'],
            'counterparty' => ['country_code' => 'IT', 'vat_number' => '01879020517']];
        foreach ([[153, 'FPR 6/26', 1098, 900, '2026-01-20'], [152, 'FPR 18/26', 4758, 3900, '2026-02-18']] as [$id, $number, $total, $net, $date]) {
            $invoice = new Invoice(['client_id' => $client->id, 'created_by' => auth()->id(), 'number' => $number, 'issue_date' => $date,
                'due_date' => '2026-02-28', 'total' => $total, 'payable_total' => $total, 'subtotal' => $net, 'tax_amount' => $total - $net,
                'currency' => 'EUR', 'status' => 'paid', 'paid_total' => $total, 'fiscal_status' => InvoiceFiscalStatus::Imported, 'import_metadata' => $metadata]);
            $invoice->id = $id;
            $invoice->save();
            $payment = new Payment(['invoice_id' => $id, 'client_id' => $client->id, 'created_by' => auth()->id(), 'amount' => $total,
                'method' => 'bank_transfer', 'payment_date' => '2026-02-28', 'reference' => 'SALDO-SCADENZA-00000000-0000-4000-8000-000000000001',
                'notes' => 'Saldo registrato in blocco: bonifico, data coincidente con la scadenza.']);
            $payment->id = $id === 153 ? 18 : 17;
            $payment->save();
        }
        $note = new CreditNote(['client_id' => $client->id, 'created_by' => auth()->id(), 'number' => 'FPR 17/26', 'issue_date' => '2026-02-18',
            'fiscal_document_type' => 'TD04', 'currency' => 'EUR', 'subtotal' => 4800, 'tax_amount' => 1056, 'total' => 5856, 'payable_total' => 5856,
            'import_fingerprint' => hash('sha256', 'correction-demonstration'), 'import_source' => 'file', 'import_metadata' => $metadata]);
        $note->id = 1;
        $note->save();
    }

    private function snapshot(): array
    {
        return collect(['invoices', 'payments', 'credit_notes', 'credit_note_allocations', 'audit_logs'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_preview_changes_nothing_and_apply_corrects_only_the_confirmed_payment_once(): void
    {
        $before = $this->snapshot();
        $action = app(CorrectBiessediliziaSettlement::class);
        $this->assertSame('preview', $action->execute()['status']);
        $this->assertSame($before, $this->snapshot());
        $otherInvoice = Invoice::findOrFail(152)->getAttributes();
        $otherPayment = Payment::findOrFail(17)->getAttributes();
        $this->assertSame('corrected', $action->execute(true)['status']);
        $invoice = Invoice::findOrFail(153);
        $this->assertSame('credited', $invoice->status);
        $this->assertSame('0.00', $invoice->paid_total);
        $this->assertSame('1098.00', $invoice->credited_total);
        $this->assertSame(0.0, $invoice->residual);
        $this->assertNull(Payment::find(18));
        $this->assertEquals(4758, CreditNote::findOrFail(1)->payable_total - DB::table('credit_note_allocations')->sum('amount'));
        $this->assertSame($otherInvoice, Invoice::findOrFail(152)->getAttributes());
        $this->assertSame($otherPayment, Payment::findOrFail(17)->getAttributes());
        $after = $this->snapshot();
        $this->assertSame('already_corrected', $action->execute(true)['status']);
        $this->assertSame($after, $this->snapshot());
    }

    public function test_changed_payment_reference_amount_or_document_stops_without_mutations(): void
    {
        foreach ([['reference' => 'Bonifico reale'], ['amount' => '1097.00'], ['notes' => 'Pagamento verificato']] as $change) {
            $payment = Payment::findOrFail(18);
            $original = $payment->getAttributes();
            $payment->update($change);
            $before = $this->snapshot();
            try { app(CorrectBiessediliziaSettlement::class)->execute(true); $this->fail('Correzione non bloccata.'); }
            catch (ValidationException $exception) { $this->assertSame($before, $this->snapshot()); }
            $payment->setRawAttributes($original)->save();
        }
    }

    public function test_command_requires_an_authorized_actor_and_defaults_to_preview(): void
    {
        $actor = auth()->id();
        $before = $this->snapshot();
        $this->artisan('finance:correct-biessedilizia-settlement')->assertFailed();
        $this->artisan('finance:correct-biessedilizia-settlement', ['--actor' => $actor])->assertSuccessful();
        $this->assertSame($before, $this->snapshot());
    }
}
