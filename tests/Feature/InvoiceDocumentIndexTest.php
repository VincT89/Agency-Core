<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\AllocateCreditNote;
use App\Domain\Finance\Actions\ChangeInvoiceStatus;
use App\Domain\Finance\Actions\RegisterPaymentAction;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class InvoiceDocumentIndexTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Bus::fake();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]));
        $this->client = Client::factory()->create(['name' => 'Cliente dimostrativo', 'company_name' => null,
            'country_code' => 'IT', 'vat_number' => '01879020517', 'tax_code' => null]);
    }

    private function document(string $number, bool $credit = false, string $date = '2026-10-16', ?Client $client = null): Invoice|CreditNote
    {
        $data = ['client_id' => ($client ?? $this->client)->id, 'created_by' => auth()->id(), 'issue_date' => $date,
            'currency' => 'EUR', 'subtotal' => '100.00', 'tax_amount' => '22.00', 'total' => '122.00', 'payable_total' => '122.00',
            'import_source' => 'file', 'import_fingerprint' => hash('sha256', $number), 'import_metadata' => ['number' => $number,
                'agency' => ['country_code' => 'IT', 'vat_number' => '01234567897'],
                'counterparty' => ($client ?? $this->client)->only(['country_code', 'vat_number', 'tax_code'])]];

        return $credit
            ? CreditNote::create($data + ['number' => $number, 'fiscal_document_type' => 'TD04'])
            : Invoice::create($data + ['number' => 'IMP-'.$number, 'fiscal_document_type' => 'TD01',
                'fiscal_status' => InvoiceFiscalStatus::Imported, 'due_date' => '2026-11-15', 'status' => 'issued', 'paid_total' => '0.00']);
    }

    public function test_invoices_and_credit_notes_share_one_date_ordered_list_and_keep_distinct_detail_links(): void
    {
        $invoice = $this->document('FPR 1/26');
        $note = $this->document('FPR 17/26', true, '2026-10-17');
        $older = $this->document('FPA 2/26', false, '2026-10-15');
        $response = $this->get(route('invoices.index'))->assertOk();
        $documents = $response->viewData('documents');
        $this->assertSame(3, $documents->total());
        $this->assertSame([CreditNote::class, Invoice::class, Invoice::class], $documents->getCollection()->map(fn ($row) => $row::class)->all());
        $this->assertSame([$note->id, $invoice->id, $older->id], $documents->pluck('id')->all());
        $response->assertSeeInOrder(['FPR 17/26', 'FPR 1/26', 'FPA 2/26'])
            ->assertSee(route('credit-notes.show', $note))->assertSee(route('invoices.show', $invoice))
            ->assertSee('Apri nota di credito FPR 17/26')->assertSee('Apri fattura FPR 1/26');
    }

    public function test_type_number_client_and_date_filters_apply_to_both_document_types(): void
    {
        $invoice = $this->document('FPR 1/26', date: '2026-10-15');
        $note = $this->document('FPR 17/26', true);
        $other = Client::factory()->create(['name' => 'Cliente diverso', 'company_name' => null]);
        $this->document('FPR 18/26', true, client: $other);
        $this->get(route('invoices.index', ['document_type' => 'TD01']))->assertOk()->assertSee('FPR 1/26')->assertDontSee('FPR 17/26');
        $this->get(route('invoices.index', ['document_type' => 'TD04']))->assertOk()->assertSee('FPR 17/26')->assertDontSee('FPR 1/26');
        $this->get(route('invoices.index', ['search' => 'fpr 17/26']))->assertOk()->assertSee('FPR 17/26')->assertDontSee('FPR 1/26');
        $response = $this->get(route('invoices.index', ['search' => 'cliente dimostrativo']))->assertOk()->assertDontSee('FPR 18/26');
        $this->assertSame(2, $response->viewData('documents')->total());
        $response = $this->get(route('invoices.index', ['client_id' => $this->client->id, 'start_date' => '2026-10-16', 'end_date' => '2026-10-16']))->assertOk();
        $this->assertTrue($note->is($response->viewData('documents')->sole()));
        $response = $this->get(route('invoices.index', ['end_date' => '2026-10-15']))->assertOk();
        $this->assertTrue($invoice->is($response->viewData('documents')->sole()));
    }

    public function test_td01_filter_does_not_include_other_invoice_document_types(): void
    {
        $standard = $this->document('FPR 1/26');
        $this->document('FPR 2/26')->update(['fiscal_document_type' => 'TD02']);
        $response = $this->get(route('invoices.index', ['document_type' => 'TD01']))->assertOk();
        $this->assertTrue($standard->is($response->viewData('documents')->sole()));
    }

    public function test_overdue_filter_and_badge_use_the_deadline_and_residual_without_rewriting_imported_status(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(12, 0));
        $late = $this->document('FPR 1/26');
        $late->update(['due_date' => '2026-10-17']);
        $partial = $this->document('FPR 2/26');
        $partial->update(['status' => 'partially_paid', 'paid_total' => '20.00', 'due_date' => '2026-10-17']);
        foreach ([['due_date' => '2026-10-18'], ['due_date' => null], ['status' => 'overdue', 'due_date' => '2026-10-20'],
            ['paid_total' => '122.00'], ['payable_total' => '100.00', 'paid_total' => '100.00'], ['credited_total' => '122.00'],
            ['status' => 'draft'], ['status' => 'paid'], ['status' => 'credited'], ['status' => 'cancelled']] as $index => $attributes) {
            $invoice = $this->document('FPR '.($index + 3).'/26');
            $invoice->update($attributes + ['due_date' => '2026-10-17']);
            $this->assertFalse($invoice->is_overdue);
        }
        $this->document('FPR 17/26', true);
        $original = $late->fresh()->getAttributes();
        $response = $this->get(route('invoices.index', ['status' => 'overdue']))->assertOk();
        $this->assertSame(2, $response->viewData('overdueCount'));
        $this->assertEqualsCanonicalizing([$late->id, $partial->id], $response->viewData('documents')->pluck('id')->all());
        $response->assertSee('class="badge br">Scaduta', false)->assertSee('Pagamento parziale');
        $this->assertSame($original, $late->fresh()->getAttributes());
        $this->assertSame('issued', $late->fresh()->status);
        $this->assertTrue($late->is_overdue);
        $late->update(['credited_total' => '122.00', 'status' => 'credited']);
        $response = $this->get(route('invoices.index', ['status' => 'overdue']))->assertOk();
        $this->assertSame(1, $response->viewData('overdueCount'));
        $this->assertTrue($partial->is($response->viewData('documents')->sole()));
    }

    public function test_an_invoice_due_today_becomes_overdue_on_the_next_day_without_a_scheduler_write(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(23, 59));
        $invoice = $this->document('FPR 1/26');
        $invoice->update(['due_date' => '2026-10-18']);
        $this->assertSame(0, $this->get(route('invoices.index'))->assertOk()->viewData('overdueCount'));
        $this->assertFalse($invoice->is_overdue);
        $this->travelTo(now()->addMinutes(2));
        $this->assertSame(1, $this->get(route('invoices.index'))->assertOk()->viewData('overdueCount'));
        $this->assertTrue($invoice->is_overdue);
        $this->assertSame('issued', $invoice->fresh()->status);
    }

    public function test_collection_filters_exclude_credit_notes_and_fiscal_filter_preserves_imported_notes(): void
    {
        $this->document('FPR 1/26');
        $this->document('FPR 17/26', true);
        $response = $this->get(route('invoices.index', ['status' => 'issued']))->assertOk()->assertDontSee('FPR 17/26');
        $this->assertSame(1, $response->viewData('documents')->total());
        $this->assertEquals(122, $response->viewData('unpaidTotal'));
        $response = $this->get(route('invoices.index', ['fiscal_status' => 'imported']))->assertOk();
        $this->assertSame(2, $response->viewData('documents')->total());
        $response = $this->get(route('invoices.index', ['fiscal_status' => 'ready']))->assertOk();
        $this->assertSame(0, $response->viewData('documents')->total());
    }

    public function test_dedicated_number_filter_matches_visible_numbers_and_combines_with_dates_and_type(): void
    {
        $invoice = $this->document('FPR 10/26', date: '2026-10-15');
        $note = $this->document('FPR 10/26', true, '2026-10-16');
        $other = Client::factory()->create(['name' => 'FPR 10/26', 'company_name' => null]);
        $this->document('FPR 11/26', client: $other);
        $response = $this->get(route('invoices.index', ['document_number' => 'fpr 10/26']))->assertOk();
        $this->assertSame(2, $response->viewData('documents')->total());
        $response = $this->get(route('invoices.index', ['document_number' => '10/26', 'start_date' => '2026-10-16', 'end_date' => '2026-10-16', 'document_type' => 'TD04']))->assertOk();
        $this->assertTrue($note->is($response->viewData('documents')->sole()));
        $response->assertSee('value="10/26"', false)->assertSee('id="invoice-start-date"', false)->assertSee('id="invoice-end-date"', false);
        $this->assertSame(0, $this->get(route('invoices.index', ['document_number' => 'IMP-']))->assertOk()->viewData('documents')->total());
        $invoice->update(['import_metadata' => null, 'fiscal_number' => 'FPR 900/26']);
        $response = $this->get(route('invoices.index', ['document_number' => '900/26', 'end_date' => '2026-10-15']))->assertOk();
        $this->assertTrue($invoice->is($response->viewData('documents')->sole()));
    }

    public function test_number_filter_treats_wildcards_as_literal_characters_and_rejects_invalid_filters(): void
    {
        $literal = $this->document('FPR %_10/26');
        $this->document('FPR 210/26', true);
        $response = $this->get(route('invoices.index', ['document_number' => '%_']))->assertOk();
        $this->assertTrue($literal->is($response->viewData('documents')->sole()));
        $this->getJson(route('invoices.index', ['start_date' => '2026-10-16', 'end_date' => '2026-10-15']))
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->getJson(route('invoices.index', ['document_number' => ['invalid'], 'start_date' => 'not-a-date']))
            ->assertUnprocessable()->assertJsonValidationErrors(['document_number', 'start_date']);
        $this->getJson(route('invoices.index', ['document_number' => str_repeat('1', 101)]))
            ->assertUnprocessable()->assertJsonValidationErrors('document_number');
    }

    public function test_linked_notes_show_available_credit_and_invoice_links_without_fabricating_receipts(): void
    {
        $invoice = $this->document('FPR 1/26');
        $note = $this->document('FPR 17/26', true);
        app(AllocateCreditNote::class)->execute($note, $invoice->id, '22.00');
        $response = $this->get(route('invoices.index', ['document_type' => 'TD04']))->assertOk()
            ->assertSee('Collegata a')->assertSee(route('invoices.show', $invoice))->assertSee('Collegata parzialmente')
            ->assertSee('Credito da collegare')->assertSee('100,00');
        $this->assertEquals(100, $response->viewData('unpaidTotal'));
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('122.00', $invoice->fresh()->total);
        $this->assertSame('122.00', $note->fresh()->total);
    }

    public function test_outstanding_amount_matches_the_summary_with_split_payment_receipts_and_unallocated_credits(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(12, 0));
        $invoice = $this->document('FPR 1/26');
        $split = $this->document('FPA 2/26');
        $split->update(['payable_total' => '100.00']);
        $this->document('FPR 3/26')->update(['status' => 'cancelled']);
        $this->document('FPR 4/26')->update(['status' => 'draft']);
        $note = $this->document('FPR 17/26', true);
        app(RegisterPaymentAction::class)->execute(['invoice_id' => $invoice->id, 'amount' => '30.00',
            'payment_date' => '2026-10-20', 'method' => 'bank_transfer']);
        $original = $split->fresh()->getAttributes();

        $summary = $this->get(route('economic-summary.index'))->assertOk()->viewData('globalSummary');
        $this->assertEquals(192, $summary['total_outstanding']);
        $this->assertEquals(192, $summary['actual_outstanding']);
        foreach ([[], ['status' => 'issued'], ['document_type' => 'TD04'], ['search' => 'nessun-documento']] as $filters) {
            $response = $this->get(route('invoices.index', $filters))->assertOk()
                ->assertSee('Da incassare')->assertDontSee('Residuo effettivo delle fatture');
            $this->assertSame($summary['actual_outstanding'], $response->viewData('unpaidTotal'));
            $this->assertSame(2, $response->viewData('outstandingCount'));
        }
        $this->assertDatabaseCount('credit_note_allocations', 0);
        $this->assertSame($original, $split->fresh()->getAttributes());
        $this->assertSame(100.0, $split->fresh()->residual);
        app(AllocateCreditNote::class)->execute($note, $split->id, '22.00');
        $this->assertEquals(170, $this->get(route('invoices.index'))->assertOk()->viewData('unpaidTotal'));
        $this->assertSame(78.0, $split->fresh()->residual);
    }

    public function test_outstanding_summary_amount_updates_after_receipts_and_cancellation(): void
    {
        $invoice = $this->document('FPR 1/26');
        $cancelled = $this->document('FPR 2/26');
        foreach ([[null, null, null, 244, 2], [$invoice, 'partially_paid', '30.00', 214, 2],
            [$invoice, 'paid', null, 122, 1], [$cancelled, 'cancelled', null, 0, 0]] as [$target, $status, $amount, $expected, $count]) {
            if ($target) {
                $this->patch(route('invoices.status.update', $target), [
                    'status' => $status, 'amount' => $amount, 'payment_date' => '2026-10-21', 'method' => 'bank_transfer',
                    'version' => ChangeInvoiceStatus::version($target->fresh()),
                ])->assertRedirect(route('invoices.show', $target))->assertSessionHasNoErrors();
            }
            $summary = $this->get(route('economic-summary.index'))->assertOk()->viewData('globalSummary');
            $response = $this->get(route('invoices.index'))->assertOk();
            $this->assertEquals($expected, $response->viewData('unpaidTotal'));
            $this->assertSame($summary['actual_outstanding'], $response->viewData('unpaidTotal'));
            $this->assertSame($count, $response->viewData('outstandingCount'));
        }
    }

    public function test_credit_only_balance_is_separate_from_zero_actual_receivables(): void
    {
        $this->document('FPR 17/26', true);
        $summary = $this->get(route('economic-summary.index'))->assertOk()->viewData('globalSummary');
        $response = $this->get(route('invoices.index'))->assertOk()->assertSee('EUR 0,00');
        $this->assertEquals(0, $response->viewData('unpaidTotal'));
        $this->assertEquals(0, $summary['total_outstanding']);
        $this->assertSame($summary['actual_outstanding'], $response->viewData('unpaidTotal'));
        $this->assertSame(0, $response->viewData('outstandingCount'));
    }

    public function test_pagination_orders_the_combined_set_without_missing_rows_or_colliding_ids(): void
    {
        $expected = [];
        $expectedDates = [];
        for ($i = 1; $i <= 32; $i++) {
            $date = '2026-10-'.str_pad((string) (($i % 20) + 1), 2, '0', STR_PAD_LEFT);
            $row = $this->document('FPR '.$i.'/26', $i % 2 === 0, $date);
            $expected[] = $row::class.':'.$row->id;
            $expectedDates[] = $date;
        }
        $actual = [];
        $dates = [];
        foreach ([1 => 15, 2 => 15, 3 => 2] as $page => $count) {
            $documents = $this->get(route('invoices.index', ['page' => $page]))->assertOk()->viewData('documents');
            $this->assertSame(32, $documents->total());
            $this->assertCount($count, $documents);
            foreach ($documents as $row) {
                $actual[] = $row::class.':'.$row->id;
                $dates[] = $row->issue_date->format('Y-m-d');
            }
        }
        $this->assertEqualsCanonicalizing($expected, $actual);
        $this->assertCount(32, array_unique($actual));
        rsort($expectedDates);
        $this->assertSame($expectedDates, $dates);
        $filtered = $this->get(route('invoices.index', ['document_type' => 'TD04', 'document_number' => 'FPR', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']))->assertOk()->viewData('documents');
        $this->assertSame(16, $filtered->total());
        $this->assertStringContainsString('document_type=TD04', $filtered->nextPageUrl());
        $this->assertStringContainsString('document_number=FPR', $filtered->nextPageUrl());
        $this->assertStringContainsString('start_date=2026-10-01', $filtered->nextPageUrl());
        $this->assertStringContainsString('end_date=2026-10-31', $filtered->nextPageUrl());
    }

    public function test_filters_are_validated_and_document_names_are_escaped(): void
    {
        $number = 'FPR <script>alert(1)</script>';
        $this->document($number, true);
        $this->get(route('invoices.index'))->assertOk()->assertSee($number)->assertDontSee($number, false);
        $this->getJson(route('invoices.index', ['search' => ['bad'], 'document_type' => 'invalid', 'page' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors(['search', 'document_type', 'page']);
    }

    public function test_the_shared_list_keeps_finance_permissions(): void
    {
        $this->document('FPR 17/26', true);
        $this->actingAs(User::factory()->create(['role' => UserRole::Administration, 'password_changed_at' => now()]));
        $this->get(route('invoices.index'))->assertOk()->assertSee('FPR 17/26');
        $this->actingAs(User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]));
        $this->get(route('invoices.index'))->assertForbidden();
        $this->get(route('invoices.index', ['document_type' => 'TD04']))->assertForbidden();
    }
}
