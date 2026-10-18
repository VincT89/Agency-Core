<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\ChangeInvoiceStatus;
use App\Domain\Finance\Actions\ImportIssuedInvoice;
use App\Domain\Finance\Actions\RegisterPaymentAction;
use App\Domain\Finance\Services\CashFlowForecast;
use App\Domain\Finance\Services\InvoiceImportReader;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Services\EconomicSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\InvoiceImportXml;
use Tests\TestCase;

class InvoiceDueDateTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(12, 0));
        Bus::fake();
        Notification::fake();
        Http::preventStrayRequests();
        Storage::fake('attachments');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]));
        $this->client = Client::factory()->create(['country_code' => 'IT', 'vat_number' => '01879020517', 'tax_code' => null]);
        BillingProfile::create(['profile_key' => 'default', 'legal_name' => 'Agenzia dimostrativa', 'vat_country_code' => 'IT',
            'vat_number' => '01234567897', 'fiscal_regime' => 'RF01', 'address' => 'Via di prova 1', 'postal_code' => '80100',
            'city' => 'Napoli', 'province' => 'NA', 'country_code' => 'IT']);
    }

    private function import(): Invoice
    {
        $xml = InvoiceImportXml::make();
        $document = app(InvoiceImportReader::class)->read($xml, 'out')[0];

        return app(ImportIssuedInvoice::class)->execute($document, ['format' => 'xml', 'source' => 'file',
            'filename' => 'documento.xml', 'content' => $xml], $this->client->id, false, $document['due_date']);
    }

    private function data(Invoice $invoice, string $date): array
    {
        return ['due_date' => $date, 'version' => ChangeInvoiceStatus::version($invoice->fresh())];
    }

    public function test_changing_the_deadline_preserves_originals_and_repeat_import_and_moves_only_the_forecast_month(): void
    {
        $invoice = $this->import();
        $metadata = $invoice->import_metadata;
        $attachment = $invoice->attachments()->sole();
        $xml = Storage::disk('attachments')->get($attachment->path);
        $summary = app(EconomicSummaryService::class)->globalSummary(auth()->user());
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('Modifica scadenza');
        $this->get(route('invoices.due-date.edit', $invoice))->assertOk()->assertSee('Nuova scadenza gestionale');
        $this->patch(route('invoices.due-date.update', $invoice), $this->data($invoice, '2026-12-15') + ['total' => '1', 'paid_total' => '122', 'number' => 'CAMBIATO'])
            ->assertRedirect(route('invoices.show', $invoice))->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame('2026-12-15', $invoice->due_date->toDateString());
        $this->assertSame('122.00', $invoice->total);
        $this->assertSame('0.00', $invoice->paid_total);
        $this->assertSame('FPR 1/26', $invoice->display_number);
        $this->assertSame($metadata, $invoice->import_metadata);
        $this->assertSame($xml, Storage::disk('attachments')->get($attachment->path));
        $this->assertTrue($invoice->auditLogs()->get()->contains(fn ($log) => array_key_exists('due_date', $log->new_values ?? [])));
        $this->assertSame($summary, app(EconomicSummaryService::class)->globalSummary(auth()->user()));
        $forecast = app(CashFlowForecast::class)->calculate(4, null);
        $this->assertSame(0, $forecast['rows']['2026-11']['expected']);
        $this->assertSame(12200, $forecast['rows']['2026-12']['expected']);
        $this->assertSame('2026-12-15', $this->import()->due_date->toDateString());
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_deadline_changes_update_overdue_counts_without_losing_partial_payment_status(): void
    {
        $invoice = $this->import();
        $this->patch(route('invoices.due-date.update', $invoice), $this->data($invoice, '2026-10-17'))->assertSessionHasNoErrors();
        $this->assertTrue($invoice->fresh()->is_overdue);
        $this->assertSame(1, $this->get(route('invoices.index'))->assertOk()->viewData('overdueCount'));
        $this->patch(route('invoices.due-date.update', $invoice), $this->data($invoice, '2026-10-18'))->assertSessionHasNoErrors();
        $this->assertSame('issued', $invoice->fresh()->status);
        $this->assertFalse($invoice->fresh()->is_overdue);
        app(RegisterPaymentAction::class)->execute(['invoice_id' => $invoice->id, 'amount' => '30.00', 'method' => 'bank_transfer', 'payment_date' => '2026-10-18']);
        $this->patch(route('invoices.due-date.update', $invoice), $this->data($invoice, '2026-10-17'))->assertSessionHasNoErrors();
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertTrue($invoice->fresh()->is_overdue);
        $this->assertSame('30.00', $invoice->fresh()->paid_total);
        $this->assertDatabaseCount('payments', 1);
        $this->patch(route('invoices.due-date.update', $invoice), $this->data($invoice, '2026-12-15'))->assertSessionHasNoErrors();
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertSame(0, $this->get(route('invoices.index'))->assertOk()->viewData('overdueCount'));
    }

    public function test_invalid_dates_and_stale_versions_do_not_overwrite_the_deadline(): void
    {
        $invoice = $this->import();
        foreach (['', '2026-02-30', '2026-10-14'] as $date) {
            $this->patch(route('invoices.due-date.update', $invoice), $this->data($invoice, $date))->assertSessionHasErrors('due_date');
            $this->assertSame('2026-11-15', $invoice->fresh()->due_date->toDateString());
        }
        $stale = $this->data($invoice, '2026-11-20');
        $this->patch(route('invoices.due-date.update', $invoice), $this->data($invoice, '2026-12-15'))->assertSessionHasNoErrors();
        $this->patch(route('invoices.due-date.update', $invoice), $stale)->assertSessionHasErrors('version');
        $this->assertSame('2026-12-15', $invoice->fresh()->due_date->toDateString());
        $this->patch(route('invoices.due-date.update', $invoice), ['due_date' => '2026-12-20'])->assertSessionHasErrors('version');
    }

    public function test_only_authorized_finance_roles_can_change_the_operational_deadline(): void
    {
        $invoice = $this->import();
        foreach ([UserRole::Developer, UserRole::Commercial, UserRole::Photographer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'password_changed_at' => now()]));
            $this->get(route('invoices.due-date.edit', $invoice))->assertForbidden();
            $this->patch(route('invoices.due-date.update', $invoice), $this->data($invoice, '2026-12-15'))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => UserRole::Administration, 'password_changed_at' => now()]));
        $this->patch(route('invoices.due-date.update', $invoice), $this->data($invoice, '2026-12-15'))->assertSessionHasNoErrors();
        $invoice->update(['fiscal_status' => InvoiceFiscalStatus::Ready]);
        $this->patch(route('invoices.due-date.update', $invoice), $this->data($invoice, '2026-12-20'))->assertForbidden();
    }
}
