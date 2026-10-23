<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\AllocateCreditNote;
use App\Domain\Finance\Actions\RegisterPaymentAction;
use App\Domain\Finance\Actions\SaveExpenseCategory;
use App\Domain\Finance\Services\CashAmount;
use App\Domain\Finance\Services\CashFlowForecast;
use App\Livewire\Expenses\ExpenseForm;
use App\Livewire\Expenses\ExpensesIndex;
use App\Models\CashFlowSetting;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\ExpenseRecurrence;
use App\Models\Invoice;
use App\Models\ManualIncome;
use App\Models\User;
use App\Services\FinanceReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CashFlowReportAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 10, 22));
        $this->actingAs(User::factory()->create(['role' => 'admin', 'password_changed_at' => now()]));
        $this->client = Client::factory()->create(['country_code' => 'IT', 'vat_number' => '01879020517', 'tax_code' => null]);
        BillingProfile::create(['profile_key' => 'default', 'legal_name' => 'Agenzia dimostrativa', 'vat_country_code' => 'IT',
            'vat_number' => '01234567897', 'fiscal_regime' => 'RF01', 'address' => 'Via di prova 1',
            'postal_code' => '80100', 'city' => 'Napoli', 'province' => 'NA', 'country_code' => 'IT']);
    }

    private function invoice(float $subtotal, string $due = '2026-10-31', array $override = []): Invoice
    {
        return Invoice::create(array_replace(['client_id' => $this->client->id, 'created_by' => auth()->id(),
            'number' => 'FORECAST-'.(Invoice::count() + 1), 'issue_date' => '2026-10-01', 'due_date' => $due,
            'currency' => 'EUR', 'subtotal' => $subtotal, 'tax_amount' => round($subtotal * .22, 2),
            'total' => round($subtotal * 1.22, 2), 'paid_total' => 0, 'status' => 'issued'], $override));
    }

    private function credit(float $subtotal): CreditNote
    {
        $sequence = CreditNote::count() + 1;
        return CreditNote::create(['client_id' => $this->client->id, 'created_by' => auth()->id(), 'number' => 'NC-'.$sequence,
            'issue_date' => '2026-10-01', 'currency' => 'EUR', 'fiscal_document_type' => 'TD04',
            'subtotal' => $subtotal, 'tax_amount' => round($subtotal * .22, 2), 'total' => round($subtotal * 1.22, 2),
            'payable_total' => round($subtotal * 1.22, 2), 'import_fingerprint' => hash('sha256', 'forecast-credit-'.$sequence),
            'import_source' => 'file', 'import_metadata' => [
                'agency' => ['country_code' => 'IT', 'vat_number' => '01234567897'],
                'counterparty' => $this->client->only(['country_code', 'vat_number', 'tax_code'])]]);
    }

    public function test_forecast_matches_summary_with_paid_split_invoices_partial_receipts_and_unallocated_credits(): void
    {
        $split = $this->invoice(100, '2026-10-11', ['payable_total' => 100]);
        $ordinary = $this->invoice(200, '2026-11-15');
        $this->invoice(50, '2026-12-15');
        $this->credit(40);
        $note = $this->credit(20);
        app(AllocateCreditNote::class)->execute($note, $ordinary->id, '12.20');
        foreach ([[$split, 100], [$ordinary, 50]] as [$invoice, $amount]) {
            app(RegisterPaymentAction::class)->execute(['invoice_id' => $invoice->id, 'amount' => $amount,
                'payment_date' => '2026-10-11', 'method' => 'bank_transfer']);
        }
        $snapshot = fn () => collect(['invoices', 'payments', 'credit_notes', 'credit_note_allocations'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $forecast = app(CashFlowForecast::class)->calculate(6, null);
        $this->assertSame(24280, $forecast['totals']['expected']);
        $this->assertSame(CashAmount::cents(app(FinanceReportQuery::class)->totals()['actual_outstanding']), $forecast['totals']['expected']);
        $this->assertSame(0, $forecast['rows']['2026-10']['expected']);
        $this->assertSame(18180, $forecast['rows']['2026-11']['expected']);
        $this->assertSame(6100, $forecast['rows']['2026-12']['expected']);
        $this->assertSame($before, $snapshot());
        $this->get(route('expenses.forecast'))->assertOk()->assertDontSee('Note di credito ancora da collegare')
            ->assertSee('Entrate attese')->assertSee('Uscite manuali');
    }

    public function test_schedule_uses_actual_invoice_cents_and_excludes_unallocated_credits(): void
    {
        foreach (['2026-10-31', '2026-11-30', '2026-12-31'] as $due) { $this->invoice(.03, $due); }
        $forecast = app(CashFlowForecast::class)->calculate(6, null);
        $this->assertSame(12, $forecast['totals']['expected']);
        $this->assertSame([4, 4, 4], array_slice(array_column($forecast['rows'], 'expected'), 0, 3));
        $this->credit(100);
        $forecast = app(CashFlowForecast::class)->calculate(6, null);
        $this->assertSame(12, $forecast['totals']['expected']);
        $this->assertSame(12, CashAmount::cents(app(FinanceReportQuery::class)->totals()['total_outstanding']));
        $this->assertSame(CashAmount::cents(app(FinanceReportQuery::class)->totals()['actual_outstanding']), $forecast['totals']['expected']);
    }

    public function test_linked_credit_follows_the_invoice_deadline_and_forecast_horizon(): void
    {
        $future = $this->invoice(100, '2027-12-31');
        $note = $this->credit(20);
        app(AllocateCreditNote::class)->execute($note, $future->id, '24.40');
        $this->assertSame(0, app(CashFlowForecast::class)->calculate(6, null)['totals']['expected']);
        $this->assertSame(9760, app(CashFlowForecast::class)->calculate(24, null)['rows']['2027-12']['expected']);
        $future->update(['due_date' => '2026-11-30']);
        $this->assertSame(9760, app(CashFlowForecast::class)->calculate(6, null)['rows']['2026-11']['expected']);
    }

    public function test_legacy_manual_income_is_separate_from_invoice_receivables_but_still_affects_cash_balance(): void
    {
        $this->invoice(100);
        ManualIncome::create(['user_id' => auth()->id(), 'title' => 'Rimborso esistente', 'amount' => 10,
            'expected_on' => '2026-10-31', 'status' => 'expected']);
        $setting = CashFlowSetting::create(['id' => 1, 'opening_balance' => 50, 'balance_date' => '2026-10-22', 'updated_by' => auth()->id()]);
        $forecast = app(CashFlowForecast::class)->calculate(6, $setting);
        $this->assertSame(12200, $forecast['totals']['expected']);
        $this->assertSame(1000, $forecast['totals']['other_expected']);
        $this->assertSame(18200, $forecast['rows']['2026-10']['closing_balance']);
        $this->get(route('expenses.forecast'))->assertOk()->assertSee('Altre entrate attese');
    }

    public function test_manual_expense_tab_excludes_recurrences_and_keeps_filters_and_category_counts(): void
    {
        $category = app(SaveExpenseCategory::class)->execute(['name' => 'Categoria manuale', 'active' => true]);
        $manual = Expense::create(['user_id' => auth()->id(), 'title' => 'Uscita inserita a mano', 'amount' => 25,
            'status' => 'pending', 'expense_date' => '2026-10-22', 'category_id' => $category->id]);
        $recurrence = ExpenseRecurrence::create(['user_id' => auth()->id(), 'title' => 'Ricorrenza test', 'amount' => 35,
            'document_kind' => 'invoice', 'frequency' => 'monthly', 'starts_on' => '2026-10-22', 'active' => true]);
        Expense::create(['user_id' => auth()->id(), 'title' => 'Uscita generata da ricorrenza', 'amount' => 35,
            'status' => 'pending', 'expense_date' => '2026-10-22', 'category_id' => $category->id, 'expense_recurrence_id' => $recurrence->id]);
        $this->get(route('expenses.manual.index'))->assertOk()->assertSee('Uscita inserita a mano')->assertDontSee('Uscita generata da ricorrenza')
            ->assertSee('Categoria manuale (1)')->assertDontSee('Entrate manuali')->assertSee('Nuova uscita');
        Livewire::test(ExpensesIndex::class, ['manualOnly' => true])->call('selectCategory', (string) $category->id)
            ->assertViewHas('expenses', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $manual->id)
            ->assertSee('Categoria manuale (1)');
        $this->get(route('expenses.index'))->assertOk()->assertSee('Uscita inserita a mano')->assertSee('Uscita generata da ricorrenza');
        Livewire::test(ExpenseForm::class)->set('title', 'Nuova uscita di prova')->set('amount', '12.50')->call('save')->assertHasNoErrors();
        $expense = Expense::where('title', 'Nuova uscita di prova')->sole();
        $this->assertNull($expense->expense_recurrence_id);
        $forecast = app(CashFlowForecast::class)->calculate(6, null);
        $this->assertSame(7250, $forecast['totals']['pending']);
        $this->assertSame(0, $forecast['totals']['expected']);
        $this->assertSame(0, $forecast['totals']['other_expected']);
        $this->actingAs(User::factory()->create(['role' => 'developer', 'password_changed_at' => now()]));
        $this->get(route('expenses.manual.index'))->assertForbidden();
    }

    public function test_forecast_footer_sums_period_movements_and_uses_only_the_final_balance(): void
    {
        $this->invoice(100, '2026-11-30');
        Expense::create(['user_id' => auth()->id(), 'title' => 'Uscita finale', 'amount' => 200.25,
            'status' => 'pending', 'expense_date' => '2026-12-01', 'due_date' => '2026-12-31']);
        $setting = CashFlowSetting::create(['id' => 1, 'opening_balance' => 50.10, 'balance_date' => '2026-10-22', 'updated_by' => auth()->id()]);
        $this->assertSame(1, $setting->getKey());
        $forecast = app(CashFlowForecast::class)->calculate(12, $setting);
        $this->assertSame(-7825, $forecast['totals']['net']);
        $this->assertSame(-2815, $forecast['totals']['closing_balance']);
        $this->assertSame($forecast['rows']['2027-09']['closing_balance'], $forecast['totals']['closing_balance']);
        $this->assertNotEquals(array_sum(array_column($forecast['rows'], 'closing_balance')), $forecast['totals']['closing_balance']);
        $response = $this->get(route('expenses.forecast'))->assertOk();
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $cells = (new \DOMXPath($dom))->query('//tfoot/tr/td');
        $this->assertSame('-78,25', trim($cells->item($cells->length - 2)->textContent));
        $this->assertSame('-28,15', trim($cells->item($cells->length - 1)->textContent));
        $this->assertStringContainsString('finance-balance-negative', $cells->item($cells->length - 1)->getAttribute('class'));
        $setting->delete();
        $forecast = app(CashFlowForecast::class)->calculate(12, null);
        $this->assertSame(-7825, $forecast['totals']['net']);
        $this->assertNull($forecast['totals']['closing_balance']);
        $response = $this->get(route('expenses.forecast'))->assertOk();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $cells = (new \DOMXPath($dom))->query('//tfoot/tr/td');
        $this->assertSame('Non impostato', trim($cells->item($cells->length - 1)->textContent));
    }
}
