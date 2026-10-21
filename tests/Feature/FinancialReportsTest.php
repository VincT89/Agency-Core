<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\AllocateCreditNote;
use App\Domain\Finance\Actions\RegisterPaymentAction;
use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Services\EconomicSummaryService;
use App\Services\FinancialSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FinancialReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $client;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 10, 18));
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]);
        $this->actingAs($this->admin);
        $this->client = Client::factory()->create(['country_code' => 'IT', 'vat_number' => '01879020517', 'tax_code' => null]);
        BillingProfile::create(['profile_key' => 'default', 'legal_name' => 'Agenzia dimostrativa', 'vat_country_code' => 'IT',
            'vat_number' => '01234567897', 'fiscal_regime' => 'RF01', 'address' => 'Via di prova 1',
            'postal_code' => '80100', 'city' => 'Napoli', 'province' => 'NA', 'country_code' => 'IT']);
        $this->project = Project::create(['client_id' => $this->client->id, 'name' => 'Progetto dimostrativo report', 'slug' => 'report-test', 'status' => 'active']);
    }

    private function invoice(string $number, string $date, float $total, array $attributes = []): Invoice
    {
        return Invoice::create(array_replace(['client_id' => $this->client->id, 'project_id' => $this->project->id,
            'created_by' => $this->admin->id, 'number' => $number, 'issue_date' => $date,
            'currency' => 'EUR', 'subtotal' => $total, 'tax_amount' => 0, 'total' => $total,
            'paid_total' => 0, 'status' => 'issued'], $attributes));
    }

    private function payment(Invoice $invoice, string $date, float $amount): void
    {
        app(RegisterPaymentAction::class)->execute(['invoice_id' => $invoice->id,
            'amount' => $amount, 'method' => 'bank_transfer', 'payment_date' => $date]);
    }

    private function credit(string $date, float $total, array $attributes = []): CreditNote
    {
        $sequence = CreditNote::count() + 1;
        return CreditNote::create(array_replace(['client_id' => $this->client->id, 'created_by' => $this->admin->id,
            'number' => 'NC-REPORT-'.$sequence, 'issue_date' => $date, 'fiscal_document_type' => 'TD04', 'currency' => 'EUR',
            'subtotal' => $total, 'tax_amount' => 0, 'total' => $total, 'payable_total' => $total,
            'import_fingerprint' => hash('sha256', 'report-credit-test-'.$sequence), 'import_source' => 'file', 'import_metadata' => [
                'agency' => ['country_code' => 'IT', 'vat_number' => '01234567897'],
                'counterparty' => $this->client->only(['country_code', 'vat_number', 'tax_code'])]], $attributes));
    }

    public function test_chart_receipts_follow_payment_dates_and_agree_with_filtered_metrics(): void
    {
        $invoice = $this->invoice('JAN', '2026-01-15', 2000);
        $this->payment($invoice, '2026-02-10', 500);

        foreach ([['2026-01-01', '2026-01-31', 2000, 0, 2440], ['2026-02-01', '2026-02-28', 0, 500, -500]] as [$from, $to, $invoiced, $collected, $pending]) {
            $response = $this->get(route('economic-summary.index', compact('from', 'to')))->assertOk();
            $chart = json_decode($response->viewData('sparklineData'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertCount(1, $chart['labels']);
            $this->assertEquals([$invoiced], $chart['invoiced']);
            $this->assertEquals([$collected], $chart['collected']);
            $this->assertEquals([$pending], $chart['pending']);
            $this->assertEquals($invoiced, $response->viewData('globalSummary')['total_invoiced']);
            $this->assertEquals($collected, $response->viewData('globalSummary')['total_collected']);
            $this->assertEquals($pending, $response->viewData('globalSummary')['total_outstanding']);
        }
    }

    public function test_exact_days_one_sided_filters_and_multiple_years_use_the_same_period_in_every_summary(): void
    {
        $previous = $this->invoice('DEC', '2025-12-20', 120);
        $this->invoice('BEFORE', '2026-01-05', 100);
        $this->invoice('WITHIN', '2026-01-17', 200);
        $this->invoice('AFTER', '2026-01-20', 300);
        $this->credit('2026-01-15', 30);
        $this->payment($previous, '2026-01-16', 40);

        foreach ([['from' => '2026-01-10', 'to' => '2026-01-17'], ['to' => '2026-01-17'], ['from' => '2026-01-20'], []] as $filters) {
            $response = $this->get(route('economic-summary.index', $filters))->assertOk();
            $global = $response->viewData('globalSummary');
            $chart = json_decode($response->viewData('sparklineData'), true, flags: JSON_THROW_ON_ERROR);
            $clients = $response->viewData('summaryByClient');
            foreach (['invoiced' => 'total_invoiced', 'collected' => 'total_collected', 'pending' => 'total_outstanding'] as $series => $metric) {
                $this->assertEqualsWithDelta($global[$metric], array_sum($chart[$series]), 0.001);
                $this->assertEqualsWithDelta($global[$metric], $clients->sum($metric), 0.001);
            }
            if ($filters === []) {
                $this->assertCount(2, $chart['labels']);
                $this->assertStringContainsString('2025', $chart['labels'][0]);
                $this->assertStringContainsString('2026', $chart['labels'][1]);
                $this->assertEquals(690, $global['total_invoiced']);
            }
        }
        $partial = app(FinancialSummaryService::class)->getReportMonthlyData('2026-01-10', '2026-01-17');
        $this->assertEquals([170], $partial['series'][0]['data']);
        $this->assertEquals([40], $partial['series'][1]['data']);
        $this->assertEquals([167.4], $partial['series'][2]['data']);
    }

    public function test_credit_only_period_preserves_unallocated_credit_data_without_showing_a_summary_notice(): void
    {
        $invoice = $this->invoice('OLDER', '2026-01-20', 1000);
        $note = $this->credit('2026-03-15', 122);
        app(AllocateCreditNote::class)->execute($note, $invoice->id, '30.00');
        $response = $this->get(route('economic-summary.index', ['from' => '2026-03-01', 'to' => '2026-03-31']))
            ->assertOk()->assertDontSee('Note di credito ancora da collegare');
        $chart = json_decode($response->viewData('sparklineData'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertEquals([-122], $chart['invoiced']);
        $this->assertEquals([0], $chart['collected']);
        $this->assertEquals([-148.84], $chart['pending']);
        $this->assertEquals(92, $response->viewData('globalSummary')['unallocated_credit']);
        $this->assertEquals(-122, $response->viewData('globalSummary')['total_invoiced']);
        $this->assertEquals(-122, $response->viewData('summaryByClient')->sole()->total_invoiced);
        $this->assertEquals(-30, $response->viewData('summaryByProject')->sole()->total_invoiced);
        $forecast = app(\App\Domain\Finance\Services\CashFlowForecast::class)->calculate(12, null);
        $this->assertSame(9200, $forecast['unallocatedCredit']);
        $this->assertSame(97000, $forecast['totals']['expected']);
        $this->assertNull(collect($forecast['rows'])->first()['closing_balance']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cancelled_expenses_are_excluded_from_client_and_project_costs(): void
    {
        foreach ([[Project::class, $this->project->id, 'paid', 60], [Project::class, $this->project->id, 'pending', 40],
            [Project::class, $this->project->id, 'cancelled', 500], [Client::class, $this->client->id, 'pending', 25],
            [Client::class, $this->client->id, 'cancelled', 700]] as [$type, $id, $status, $amount]) {
            Expense::create(['user_id' => $this->admin->id, 'expenseable_type' => $type, 'expenseable_id' => $id,
                'title' => 'Spesa dimostrativa report', 'amount' => $amount, 'expense_date' => '2026-10-16', 'status' => $status]);
        }
        $response = $this->get(route('economic-summary.index'))->assertOk();
        $this->assertEquals(125, $response->viewData('summaryByClient')->sole()->total_expenses);
        $this->assertEquals(100, $response->viewData('summaryByProject')->sole()->total_expenses);
    }

    public function test_current_year_charts_exclude_future_year_drafts_and_cancelled_invoices(): void
    {
        $current = $this->invoice('CURRENT', '2026-10-15', 300);
        $this->payment($current, '2026-10-16', 100);
        $future = $this->invoice('FUTURE', '2027-01-15', 900);
        $this->payment($future, '2027-01-16', 900);
        $this->invoice('DRAFT', '2026-10-15', 999, ['status' => 'draft']);
        $this->invoice('CANCELLED', '2026-10-15', 888, ['status' => 'cancelled']);
        $this->credit('2026-10-15', 10);
        $this->credit('2027-01-15', 40);
        $service = app(FinancialSummaryService::class);
        $this->assertEquals([100, 200], $service->getYearlyDonutData()['series']);
        $monthly = $service->getMonthlyData();
        $this->assertCount(12, $monthly['labels']);
        $this->assertEquals(290, array_sum($monthly['series'][0]['data']));
        $this->assertEquals(100, array_sum($monthly['series'][1]['data']));
        $this->assertEquals(253.8, array_sum($service->getIncassatoVsDaIncassareMonthlyData()['series'][2]['data']));
    }

    public function test_empty_period_renders_zero_series_without_exposing_finance_to_unauthorized_users(): void
    {
        $response = $this->get(route('economic-summary.index', ['from' => '2025-12-01', 'to' => '2026-02-28']))->assertOk();
        $chart = json_decode($response->viewData('sparklineData'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(3, $chart['labels']);
        $this->assertEquals([0, 0, 0], $chart['invoiced']);
        $this->assertEquals(0, $response->viewData('globalSummary')['unallocated_credit']);
        $developer = User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]);
        $this->actingAs($developer)->get(route('economic-summary.index'))->assertForbidden();
        $this->assertEquals(0, app(EconomicSummaryService::class)->globalSummary($developer)['unallocated_credit']);
    }

    public function test_net_turnover_and_fixed_vat_report_do_not_rewrite_actual_invoice_balances(): void
    {
        $standard = $this->invoice('VAT22', '2026-10-15', 1220, ['subtotal' => 1000, 'tax_amount' => 220, 'due_date' => '2026-10-31']);
        $this->invoice('VAT10', '2026-10-15', 220, ['subtotal' => 200, 'tax_amount' => 20]);
        $this->invoice('VAT04', '2026-10-15', 104, ['subtotal' => 100, 'tax_amount' => 4]);
        $this->invoice('EXEMPT', '2026-10-15', 50.99);
        $this->invoice('MIXED', '2026-10-15', 342, ['subtotal' => 300, 'tax_amount' => 42]);
        $this->invoice('SPLIT', '2026-10-15', 610, ['subtotal' => 500, 'tax_amount' => 110, 'payable_total' => 500]);
        $this->invoice('STAMP-ROUNDING', '2026-10-15', 124.01, ['subtotal' => 100, 'tax_amount' => 22]);
        $this->invoice('DRAFT', '2026-10-15', 1220, ['subtotal' => 1000, 'tax_amount' => 220, 'status' => 'draft']);
        $this->invoice('CANCELLED', '2026-10-15', 1220, ['subtotal' => 1000, 'tax_amount' => 220, 'status' => 'cancelled']);
        $note = $this->credit('2026-10-15', 122);
        $note->update(['subtotal' => 100, 'tax_amount' => 22]);
        $this->payment($standard, '2026-10-19', 244);
        app(AllocateCreditNote::class)->execute($note, $standard->id, '122.00');

        $tables = ['invoices', 'credit_notes', 'credit_note_allocations', 'payments'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $response = $this->get(route('economic-summary.index', ['from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()->assertSee('Fatturato netto (IVA esclusa)')->assertDontSee('finance-note-panel', false)
            ->assertDontSee('Fatturato (IVA inclusa)');
        $global = $response->viewData('globalSummary');
        $this->assertEquals(2150.99, $global['total_invoiced']);
        $this->assertEquals(244, $global['total_collected']);
        $this->assertEquals(2380.21, $global['total_outstanding']);
        $this->assertSame(7, $global['invoices_count']);
        $this->assertEquals(0, $global['unallocated_credit']);
        foreach (['summaryByClient', 'summaryByProject'] as $group) {
            $row = $response->viewData($group)->sole();
            foreach (['total_invoiced', 'total_collected', 'total_outstanding', 'invoices_count'] as $metric) {
                $this->assertEquals($global[$metric], $row->{$metric}, $group.' '.$metric);
            }
        }
        $chart = json_decode($response->viewData('sparklineData'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertEquals([2150.99], $chart['invoiced']);
        $this->assertEquals([244], $chart['collected']);
        $this->assertEquals([2380.21], $chart['pending']);
        $dashboard = $this->get(route('dashboard'))->assertOk()->assertSee('Fatturato al netto di IVA e note di credito');
        $series = $dashboard->viewData('financialChartData')['series'];
        $this->assertSame('Fatturato netto (IVA esclusa)', $series[0]['name']);
        $this->assertEquals(2150.99, $series[0]['data'][9]);
        $this->assertEquals(244, $series[1]['data'][9]);
        $this->assertEquals([244, 2195], app(FinancialSummaryService::class)->getYearlyDonutData()['series']);
        $forecast = app(\App\Domain\Finance\Services\CashFlowForecast::class)->calculate(12, null);
        $this->assertSame(24400, $forecast['totals']['received']);
        $this->assertSame(219500, $forecast['totals']['expected']);
        $this->assertSame($before, $snapshot());
    }

    public function test_an_unallocated_credit_note_reduces_net_turnover_without_creating_a_payment_or_project_assignment(): void
    {
        $this->credit('2026-08-15', 122);
        $response = $this->get(route('economic-summary.index', ['from' => '2026-08-01', 'to' => '2026-08-31']))->assertOk();
        $global = $response->viewData('globalSummary');
        foreach (['total_collected', 'invoices_count'] as $metric) {
            $this->assertEquals(0, $global[$metric]);
        }
        $this->assertEquals(122, $global['unallocated_credit']);
        $this->assertEquals(-122, $global['total_invoiced']);
        $this->assertEquals(-148.84, $global['total_outstanding']);
        $this->assertEquals(-122, $response->viewData('summaryByClient')->sole()->total_invoiced);
        $this->assertEmpty($response->viewData('summaryByProject'));
        $this->assertEquals([-122], json_decode($response->viewData('sparklineData'), true)['invoiced']);
        $this->assertEquals(-122, array_sum(app(FinancialSummaryService::class)->getMonthlyData()['series'][0]['data']));
        $this->assertCount(1, app(FinancialSummaryService::class)->getReportMonthlyData()['labels']);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_receiving_vat_in_a_later_month_does_not_change_invoice_turnover(): void
    {
        $invoice = $this->invoice('VAT-JAN', '2026-01-15', 1220, ['subtotal' => 1000, 'tax_amount' => 220]);
        $this->payment($invoice, '2026-02-10', 1220);
        foreach ([['2026-01-01', '2026-01-31', 1000, 0, 1220], ['2026-02-01', '2026-02-28', 0, 1220, -1220]] as [$from, $to, $invoiced, $collected, $pending]) {
            $response = $this->get(route('economic-summary.index', compact('from', 'to')))->assertOk();
            $global = $response->viewData('globalSummary');
            $this->assertEquals($invoiced, $global['total_invoiced']);
            $this->assertEquals($collected, $global['total_collected']);
            $this->assertEquals($pending, $global['total_outstanding']);
            $chart = json_decode($response->viewData('sparklineData'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertEquals([$invoiced], $chart['invoiced']);
            $this->assertEquals([$collected], $chart['collected']);
            $this->assertEquals([$pending], $chart['pending']);
        }
    }

    public function test_credit_taxable_amounts_follow_their_own_vat_and_split_payment_values_without_double_deduction(): void
    {
        $invoice = $this->invoice('VAT10', '2026-10-15', 1100, ['subtotal' => 1000, 'tax_amount' => 100]);
        $split = $this->invoice('SPLIT', '2026-10-15', 610, ['subtotal' => 500, 'tax_amount' => 110, 'payable_total' => 500]);
        $note = $this->credit('2026-10-16', 220, ['subtotal' => 200, 'tax_amount' => 20]);
        $splitNote = $this->credit('2026-10-16', 122, ['subtotal' => 100, 'tax_amount' => 22, 'payable_total' => 100]);
        $service = app(EconomicSummaryService::class);
        $this->assertEquals(1200, $service->globalSummary($this->admin)['total_invoiced']);
        $this->assertEquals(1500, $service->summaryByProject($this->admin)->sole()->total_invoiced);

        app(AllocateCreditNote::class)->execute($note, $invoice->id, '110.00');
        app(AllocateCreditNote::class)->execute($splitNote, $split->id, '100.00');
        $this->assertEquals(1200, $service->globalSummary($this->admin)['total_invoiced']);
        $this->assertEquals(1200, $service->summaryByClient($this->admin)->sole()->total_invoiced);
        $this->assertEquals(1300, $service->summaryByProject($this->admin)->sole()->total_invoiced);
        $this->assertEquals(1464, $service->globalSummary($this->admin)['total_outstanding']);
        $this->assertEquals(1464, $service->summaryByClient($this->admin)->sole()->total_outstanding);
        $this->assertEquals(1586, $service->summaryByProject($this->admin)->sole()->total_outstanding);
        $this->assertEquals([1200], app(FinancialSummaryService::class)->getReportMonthlyData()['series'][0]['data']);
        $this->assertDatabaseCount('payments', 0);

        $developer = User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]);
        $this->actingAs($developer)->get(route('economic-summary.index'))->assertForbidden();
        $this->assertEquals(0, $service->globalSummary($developer)['total_invoiced']);
        $this->assertEmpty($service->summaryByClient($developer));
        $this->assertEmpty($service->summaryByProject($developer));
    }

    public function test_taxable_credit_distribution_preserves_cents_and_does_not_assign_unlinked_amounts_to_projects(): void
    {
        $note = $this->credit('2026-10-15', 0.03, ['subtotal' => 0.02, 'tax_amount' => 0.01]);
        foreach ([null, $this->project->id, $this->project->id] as $index => $projectId) {
            $invoice = $this->invoice('ROUND-'.$index, '2026-10-15', 1, ['project_id' => $projectId]);
            app(AllocateCreditNote::class)->execute($note, $invoice->id, '0.01');
        }
        $service = app(EconomicSummaryService::class);
        $this->assertEquals(2.98, $service->globalSummary($this->admin)['total_invoiced']);
        $this->assertEquals(2.98, $service->summaryByClient($this->admin)->sole()->total_invoiced);
        $this->assertEquals(1.99, $service->summaryByProject($this->admin)->sole()->total_invoiced);

        Invoice::where('number', 'ROUND-0')->update(['project_id' => $this->project->id]);
        $this->assertEquals(2.98, $service->summaryByProject($this->admin)->sole()->total_invoiced);
        $this->assertEquals(3.64, $service->globalSummary($this->admin)['total_outstanding']);
        $this->assertEquals(3.64, $service->summaryByProject($this->admin)->sole()->total_outstanding);
    }

    public function test_split_payment_project_uses_fixed_vat_in_the_report_without_changing_the_real_receivable(): void
    {
        $invoice = $this->invoice('SPLIT-REPORT', '2026-10-15', 1220,
            ['subtotal' => 1000, 'tax_amount' => 220, 'payable_total' => 1000]);
        $note = $this->credit('2026-10-15', 244, ['subtotal' => 200, 'tax_amount' => 44, 'payable_total' => 200]);
        app(AllocateCreditNote::class)->execute($note, $invoice->id, '200.00');
        $this->payment($invoice, '2026-10-16', 300);
        $response = $this->get(route('economic-summary.index'))->assertOk()->assertSee('Da incassare (IVA 22%)');
        $this->assertEquals(800, $response->viewData('globalSummary')['total_invoiced']);
        $this->assertEquals(676, $response->viewData('globalSummary')['total_outstanding']);
        $this->assertEquals(676, $response->viewData('summaryByClient')->sole()->total_outstanding);
        $this->assertEquals(676, $response->viewData('summaryByProject')->sole()->total_outstanding);
        $this->assertEquals([676], json_decode($response->viewData('sparklineData'), true)['pending']);
        $this->assertEquals(500, $invoice->fresh()->residual);
    }

    public function test_report_receivable_is_displayed_net_turnover_with_vat_even_before_credit_notes_are_linked(): void
    {
        $invoice = $this->invoice('REPORT-REGRESSION', '2026-10-15', 49766.24, ['subtotal' => 40792, 'tax_amount' => 8974.24]);
        $note = $this->credit('2026-10-16', 5856, ['subtotal' => 4800, 'tax_amount' => 1056]);
        $response = $this->get(route('economic-summary.index'))->assertOk()->assertSee('43.910,24');
        $summary = $response->viewData('globalSummary');
        $this->assertEquals(35992, $summary['total_invoiced']);
        $this->assertEquals(0, $summary['total_collected']);
        $this->assertEquals(43910.24, $summary['total_outstanding']);
        $this->assertEquals(43910.24, $response->viewData('summaryByClient')->sole()->total_outstanding);
        $this->assertEquals([43910.24], json_decode($response->viewData('sparklineData'), true)['pending']);
        $this->assertDatabaseCount('credit_note_allocations', 0);

        app(AllocateCreditNote::class)->execute($note, $invoice->id, '5856.00');
        $this->assertEquals(43910.24, app(EconomicSummaryService::class)->globalSummary($this->admin)['total_outstanding']);
        $project = app(EconomicSummaryService::class)->summaryByProject($this->admin)->sole();
        $this->assertEquals(35992, $project->total_invoiced);
        $this->assertEquals(43910.24, $project->total_outstanding);
        $this->payment($invoice, '2026-10-17', 1000);
        $this->assertEquals(42910.24, app(EconomicSummaryService::class)->globalSummary($this->admin)['total_outstanding']);
        $this->assertEquals(42910.24, app(EconomicSummaryService::class)->summaryByProject($this->admin)->sole()->total_outstanding);
    }
}
