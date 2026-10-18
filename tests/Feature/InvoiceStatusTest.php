<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\AllocateCreditNote;
use App\Domain\Finance\Actions\ChangeInvoiceStatus;
use App\Domain\Finance\Actions\ImportIssuedInvoice;
use App\Domain\Finance\Services\InvoiceImportReader;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\InvoiceImportXml;
use Tests\TestCase;

class InvoiceStatusTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Bus::fake();
        Storage::fake('attachments');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]));
        $this->client = Client::factory()->create(['name' => 'Cliente dimostrativo', 'vat_number' => '01879020517', 'tax_code' => null, 'country_code' => 'IT']);
        BillingProfile::create(['profile_key' => 'default', 'legal_name' => 'Agenzia dimostrativa', 'vat_country_code' => 'IT',
            'vat_number' => '01234567897', 'fiscal_regime' => 'RF01', 'address' => 'Via di prova 1',
            'postal_code' => '80100', 'city' => 'Napoli', 'province' => 'NA', 'country_code' => 'IT']);
    }

    private function import(?string $xml = null)
    {
        $xml ??= InvoiceImportXml::make('FPA 1/26', split: true);
        $document = app(InvoiceImportReader::class)->read($xml, 'out')[0];

        return app(ImportIssuedInvoice::class)->execute($document,
            ['format' => 'xml', 'source' => 'file', 'filename' => 'documento.xml', 'content' => $xml],
            $this->client->id, false, $document['due_date']);
    }

    private function data(Invoice $invoice, string $status, array $extra = []): array
    {
        return $extra + ['status' => $status, 'version' => ChangeInvoiceStatus::version($invoice->fresh()),
            'payment_date' => '2026-10-16', 'method' => 'bank_transfer'];
    }

    public function test_marking_paid_records_only_the_remaining_net_and_preserves_the_original_invoice(): void
    {
        $invoice = $this->import();
        $metadata = $invoice->import_metadata;
        $attachment = $invoice->attachments()->sole();
        $xml = Storage::disk('attachments')->get($attachment->path);
        $this->get(route('invoices.status.edit', $invoice))->assertOk()->assertSee('Modifica stato')->assertSee('100,00 €');
        $this->patch(route('invoices.status.update', $invoice), $this->data($invoice, 'paid', ['total' => '1', 'number' => 'SOSTITUITO']))
            ->assertRedirect(route('invoices.show', $invoice))->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('100.00', $invoice->paid_total);
        $this->assertSame(0.0, $invoice->residual);
        $this->assertSame('122.00', $invoice->total);
        $this->assertSame('FPA 1/26', $invoice->display_number);
        $this->assertSame($metadata, $invoice->import_metadata);
        $this->assertSame(InvoiceFiscalStatus::Imported, $invoice->fiscal_status);
        $this->assertSame($xml, Storage::disk('attachments')->get($attachment->path));
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'amount' => 100, 'method' => 'bank_transfer']);
        $this->assertTrue($invoice->auditLogs()->where('action', 'status_changed')->exists());
        Http::assertNothingSent();
    }

    public function test_partial_receipt_then_paid_adds_only_the_remaining_balance(): void
    {
        $invoice = $this->import();
        $this->patch(route('invoices.status.update', $invoice), $this->data($invoice, 'partially_paid', ['amount' => '30.00']))->assertSessionHasNoErrors();
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertSame(70.0, $invoice->fresh()->residual);
        $this->patch(route('invoices.status.update', $invoice), $this->data($invoice, 'paid'))->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['30.00', '70.00'], Payment::pluck('amount')->all());
        $this->assertSame('100.00', $invoice->fresh()->paid_total);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('paid', $this->import()->status);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_stale_or_repeated_status_form_cannot_add_another_payment(): void
    {
        $invoice = $this->import();
        $data = $this->data($invoice, 'partially_paid', ['amount' => '20.00']);
        $this->patch(route('invoices.status.update', $invoice), $data)->assertSessionHasNoErrors();
        $this->patch(route('invoices.status.update', $invoice), $data)->assertSessionHasErrors('status');
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('20.00', $invoice->fresh()->paid_total);
    }

    public function test_unpaid_invoice_can_be_cancelled_and_reopened_without_external_calls_or_overwriting_on_import(): void
    {
        $invoice = $this->import();
        $metadata = $invoice->import_metadata;
        $this->patch(route('invoices.status.update', $invoice), $this->data($invoice, 'cancelled'))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $invoice->fresh()->status);
        $this->assertEquals(0, $invoice->fresh()->residual);
        $this->assertSame('cancelled', $this->import()->status);
        $this->post(route('payments.store'), ['invoice_id' => $invoice->id, 'amount' => '10.00', 'payment_date' => '2026-10-16', 'method' => 'cash'])->assertSessionHasErrors('invoice_id');
        $this->patch(route('invoices.status.update', $invoice), $this->data($invoice, 'issued'))->assertSessionHasNoErrors();
        $this->assertSame('issued', $invoice->fresh()->status);
        $this->assertSame(100.0, $invoice->fresh()->residual);
        $this->assertSame($metadata, $invoice->fresh()->import_metadata);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_cancellation_or_unpaid_status_does_not_delete_existing_receipts(): void
    {
        $invoice = $this->import();
        $this->patch(route('invoices.status.update', $invoice), $this->data($invoice, 'paid'))->assertSessionHasNoErrors();
        foreach (['cancelled', 'issued', 'overdue'] as $status) {
            $this->patch(route('invoices.status.update', $invoice), $this->data($invoice, $status))->assertSessionHasErrors('status');
            $this->assertSame('paid', $invoice->fresh()->status);
            $this->assertSame('100.00', $invoice->fresh()->paid_total);
        }
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_credit_allocations_prevent_cancellation_and_are_not_deleted(): void
    {
        $invoice = $this->import();
        $note = $this->import(InvoiceImportXml::make('FPR 17/26', 'TD04'));
        app(AllocateCreditNote::class)->execute($note, $invoice->id, '20.00');
        $before = $invoice->fresh()->status;
        $this->patch(route('invoices.status.update', $invoice), $this->data($invoice, 'cancelled'))->assertSessionHasErrors('status');
        $this->assertSame($before, $invoice->fresh()->status);
        $this->assertDatabaseCount('credit_note_allocations', 1);
    }

    public function test_payment_data_and_partial_amounts_are_validated(): void
    {
        $invoice = $this->import();
        $this->patch(route('invoices.status.update', $invoice), ['status' => 'paid', 'version' => ChangeInvoiceStatus::version($invoice->fresh())])
            ->assertSessionHasErrors(['payment_date', 'method']);
        foreach (['-1.00', '0.00', '100.00', '100.01', '1.001'] as $amount) {
            $this->patch(route('invoices.status.update', $invoice), $this->data($invoice, 'partially_paid', ['amount' => $amount]))->assertSessionHasErrors('amount');
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('issued', $invoice->fresh()->status);
    }

    public function test_only_past_due_invoices_can_be_marked_overdue(): void
    {
        $invoice = $this->import();
        $invoice->update(['due_date' => today()->addDay()]);
        $this->patch(route('invoices.status.update', $invoice), $this->data($invoice, 'overdue'))->assertSessionHasErrors('status');
        $invoice->update(['due_date' => today()->subDay()]);
        $this->patch(route('invoices.status.update', $invoice), $this->data($invoice, 'overdue'))->assertSessionHasNoErrors();
        $this->assertSame('overdue', $invoice->fresh()->status);
    }

    public function test_non_finance_users_cannot_view_or_change_invoice_status(): void
    {
        $invoice = $this->import();
        $data = $this->data($invoice, 'paid');
        $this->actingAs(User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]));
        $this->get(route('invoices.status.edit', $invoice))->assertForbidden();
        $this->patch(route('invoices.status.update', $invoice), $data)->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('issued', $invoice->fresh()->status);
    }
}
