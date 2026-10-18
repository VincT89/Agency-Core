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

    private function credit(string $date, float $total): CreditNote
    {
        return CreditNote::create(['client_id' => $this->client->id, 'created_by' => $this->admin->id,
            'number' => 'NC-REPORT', 'issue_date' => $date, 'fiscal_document_type' => 'TD04', 'currency' => 'EUR',
            'subtotal' => $total, 'tax_amount' => 0, 'total' => $total, 'payable_total' => $total,
            'import_fingerprint' => hash('sha256', 'report-credit-test'), 'import_source' => 'file', 'import_metadata' => [
                'agency' => ['country_code' => 'IT', 'vat_number' => '01234567897'],
                'counterparty' => $this->client->only(['country_code', 'vat_number', 'tax_code'])]]);
    }

    public function test_chart_receipts_follow_payment_dates_and_agree_with_filtered_metrics(): void
    {
        $invoice = $this->invoice('JAN', '2026-01-15', 2000);
        $this->payment($invoice, '2026-02-10', 500);

        foreach ([['2026-01-01', '2026-01-31', 2000, 0, 1500], ['2026-02-01', '2026-02-28', 0, 500, 0]] as [$from, $to, $invoiced, $collected, $pending]) {
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
        $this->assertEquals([200], $partial['series'][2]['data']);
    }

    public function test_credit_only_period_is_negative_and_reports_only_unallocated_credit_as_pending_link(): void
    {
        $invoice = $this->invoice('OLDER', '2026-01-20', 1000);
        $note = $this->credit('2026-03-15', 122);
        app(AllocateCreditNote::class)->execute($note, $invoice->id, '30.00');
        $response = $this->get(route('economic-summary.index', ['from' => '2026-03-01', 'to' => '2026-03-31']))
            ->assertOk()->assertSee('Note di credito ancora da collegare')->assertSee('92,00');
        $chart = json_decode($response->viewData('sparklineData'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertEquals([-122], $chart['invoiced']);
        $this->assertEquals([0], $chart['collected']);
        $this->assertEquals([0], $chart['pending']);
        $this->assertEquals(92, $response->viewData('globalSummary')['unallocated_credit']);
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
        $service = app(FinancialSummaryService::class);
        $this->assertEquals([100, 200], $service->getYearlyDonutData()['series']);
        $monthly = $service->getMonthlyData();
        $this->assertCount(12, $monthly['labels']);
        $this->assertEquals(290, array_sum($monthly['series'][0]['data']));
        $this->assertEquals(100, array_sum($monthly['series'][1]['data']));
        $this->assertEquals(200, array_sum($service->getIncassatoVsDaIncassareMonthlyData()['series'][2]['data']));
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
}
