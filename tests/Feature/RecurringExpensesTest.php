<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\GenerateRecurringExpenses;
use App\Domain\Finance\Actions\RegisterPaymentAction;
use App\Domain\Finance\Actions\SaveExpense;
use App\Domain\Finance\Actions\StoreExpenseDocument;
use App\Domain\Finance\Services\CashAmount;
use App\Domain\Finance\Services\CashFlowForecast;
use App\Enums\UserRole;
use App\Livewire\Expenses\ExpenseForm;
use App\Livewire\Expenses\ExpenseShow;
use App\Models\CashFlowSetting;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Models\ExpenseRecurrence;
use App\Models\Invoice;
use App\Models\ManualIncome;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecurringExpensesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-17 12:00:00'));
        Storage::fake('attachments');
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active', 'password_changed_at' => now()]);
        $this->actingAs($this->admin);
    }

    public static function calendarCases(): array
    {
        return [
            ['monthly', '2026-01-31', 1, '2026-02-28'],
            ['monthly', '2026-01-31', 2, '2026-03-31'],
            ['monthly', '2024-01-31', 1, '2024-02-29'],
            ['yearly', '2024-02-29', 1, '2025-02-28'],
            ['yearly', '2024-02-29', 4, '2028-02-29'],
            ['quarterly', '2026-01-31', 1, '2026-04-30'],
            ['semiannual', '2026-08-31', 1, '2027-02-28'],
            ['weekly', '2026-12-28', 1, '2027-01-04'],
        ];
    }

    #[DataProvider('calendarCases')]
    public function test_calendar_keeps_the_original_day_without_monthly_drift(string $frequency, string $start, int $index, string $expected): void
    {
        $recurrence = new ExpenseRecurrence(['frequency' => $frequency, 'starts_on' => $start]);
        $this->assertSame($expected, $recurrence->occurrence($index)->toDateString());
    }

    public function test_creating_a_recurrence_generates_pending_expenses_once_and_respects_end_date(): void
    {
        $this->post(route('expenses.recurrences.store'), $this->recurrenceData(['starts_on' => '2026-09-30', 'ends_on' => '2026-11-30']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $recurrence = ExpenseRecurrence::sole();
        $this->assertSame(['2026-09-30', '2026-10-30', '2026-11-30'], $recurrence->expenses()->orderBy('recurrence_date')->get()->map(fn ($expense) => $expense->recurrence_date->toDateString())->all());
        $this->assertSame(3, $recurrence->expenses()->where('status', 'pending')->whereNull('paid_at')->whereNull('expense_document_id')->count());
        $this->artisan('expenses:generate-recurring')->assertSuccessful();
        $this->artisan('expenses:generate-recurring')->assertSuccessful();
        $this->assertDatabaseCount('expenses', 3);
        $this->assertSame('29.99', $recurrence->expenses()->first()->amount);
    }

    public function test_daily_generation_extends_the_horizon_without_duplicating_existing_rows(): void
    {
        $recurrence = $this->recurrence(['starts_on' => '2026-09-17']);
        $this->artisan('expenses:generate-recurring')->assertSuccessful();
        $this->assertSame(24, $recurrence->expenses()->count());
        $this->travelTo(CarbonImmutable::parse('2026-10-17'));
        $this->artisan('expenses:generate-recurring')->assertSuccessful();
        $this->assertSame(25, $recurrence->expenses()->count());
        $this->assertSame('2028-09-17', $recurrence->expenses()->orderByDesc('recurrence_date')->first()->recurrence_date->toDateString());
    }

    public function test_pause_resume_and_price_changes_preserve_paid_documented_past_and_manual_occurrences(): void
    {
        $recurrence = $this->recurrence(['starts_on' => '2026-08-17', 'ends_on' => '2027-02-17']);
        app(GenerateRecurringExpenses::class)->execute($recurrence);
        $expenses = $recurrence->expenses()->orderBy('recurrence_date')->get();
        $document = $this->document(['amount' => '100.00']);
        $expenses[2]->update(['status' => 'paid', 'paid_at' => '2026-09-17', 'expense_document_id' => $document->id]);
        $expenses[3]->update(['expense_document_id' => $document->id]);
        app(SaveExpense::class)->execute(['amount' => '25.00'], $expenses[4]);
        app(SaveExpense::class)->execute(['status' => 'cancelled'], $expenses[5]);

        $payload = $this->recurrenceData(['starts_on' => '2026-08-17', 'ends_on' => '2027-02-17', 'active' => false, 'amount' => '35.50']);
        $this->put(route('expenses.recurrences.update', $recurrence), $payload)->assertSessionHasNoErrors();
        $this->assertSame('pending', $expenses[0]->fresh()->status);
        $this->assertSame('cancelled', $expenses[1]->fresh()->status);
        $this->assertSame('paid', $expenses[2]->fresh()->status);
        $this->assertSame('pending', $expenses[3]->fresh()->status);
        $this->assertSame('25.00', $expenses[4]->fresh()->amount);
        $this->assertSame('cancelled', $expenses[6]->fresh()->status);
        $this->artisan('expenses:generate-recurring')->assertSuccessful();
        $this->assertSame(7, $recurrence->expenses()->count());

        $payload['active'] = true;
        $this->put(route('expenses.recurrences.update', $recurrence), $payload)->assertSessionHasNoErrors();
        $this->assertSame('35.50', $expenses[1]->fresh()->amount);
        $this->assertSame('pending', $expenses[1]->fresh()->status);
        $this->assertSame('29.99', $expenses[0]->fresh()->amount);
        $this->assertSame('29.99', $expenses[2]->fresh()->amount);
        $this->assertSame('29.99', $expenses[3]->fresh()->amount);
        $this->assertSame('25.00', $expenses[4]->fresh()->amount);
        $this->assertSame('cancelled', $expenses[5]->fresh()->status);

        $payload['ends_on'] = '2027-01-17';
        $this->put(route('expenses.recurrences.update', $recurrence), $payload)->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $expenses[6]->fresh()->status);
    }

    public function test_recurrence_validation_prevents_calendar_rewrites_invalid_money_and_end_before_start(): void
    {
        $recurrence = $this->recurrence();
        $this->put(route('expenses.recurrences.update', $recurrence), $this->recurrenceData(['frequency' => 'weekly', 'starts_on' => '2026-10-01']))
            ->assertSessionHasErrors(['frequency', 'starts_on']);
        $this->post(route('expenses.recurrences.store'), $this->recurrenceData(['amount' => '10.999', 'ends_on' => '2026-09-01']))
            ->assertSessionHasErrors(['amount', 'ends_on']);
        $this->assertDatabaseCount('expense_recurrences', 1);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_resuming_a_long_pause_does_not_create_unexpected_arrears(): void
    {
        $recurrence = $this->recurrence(['starts_on' => '2024-01-17', 'active' => false]);
        $this->put(route('expenses.recurrences.update', $recurrence), $this->recurrenceData(['starts_on' => '2024-01-17', 'active' => true]))->assertSessionHasNoErrors();
        $this->assertSame(0, $recurrence->expenses()->whereDate('recurrence_date', '<', '2026-09-17')->where('status', 'pending')->count());
        $this->assertSame(24, $recurrence->expenses()->where('status', 'pending')->count());
        $this->artisan('expenses:generate-recurring')->assertSuccessful();
        $this->assertSame(24, $recurrence->expenses()->where('status', 'pending')->count());
    }

    public function test_payment_requires_an_available_document_of_the_right_kind(): void
    {
        $expense = $this->expense(['document_kind' => 'payslip']);
        Livewire::test(ExpenseShow::class, ['expense' => $expense])->call('markAsPaid')->assertHasErrors('expense_document_id');
        $this->assertSame('pending', $expense->fresh()->status);
        $invoice = $this->document();
        Livewire::test(ExpenseForm::class, ['expense' => $expense])->set('expense_document_id', $invoice->id)->set('status', 'paid')->call('save')->assertHasErrors('expense_document_id');
        $payslip = $this->document(['kind' => 'payslip', 'number' => 'CED-09']);
        Livewire::test(ExpenseForm::class, ['expense' => $expense])->set('expense_document_id', $payslip->id)->set('status', 'paid')->set('paid_on', '2026-09-16')->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertSame('paid', $expense->fresh()->status);
        $this->assertSame('2026-09-16', $expense->fresh()->paid_at->toDateString());
        Livewire::test(ExpenseShow::class, ['expense' => $expense->fresh()])->call('markAsPending')->assertHasNoErrors();
        $this->assertNull($expense->fresh()->paid_at);
        Storage::disk('attachments')->delete($payslip->path);
        Livewire::test(ExpenseShow::class, ['expense' => $expense->fresh()])->call('markAsPaid')->assertHasErrors('expense_document_id');
        $this->assertSame('pending', $expense->fresh()->status);
    }

    public function test_instalments_cannot_exceed_the_document_and_cancellation_frees_the_amount(): void
    {
        $document = $this->document(['amount' => '100.00']);
        $first = app(SaveExpense::class)->execute($this->expenseData(['amount' => '60.00', 'expense_document_id' => $document->id]));
        $second = app(SaveExpense::class)->execute($this->expenseData(['amount' => '40.00', 'expense_document_id' => $document->id]));
        try {
            app(SaveExpense::class)->execute(['amount' => '40.01'], $second);
            $this->fail('Over-allocation was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expense_document_id', $exception->errors());
        }
        $this->assertSame('40.00', $second->fresh()->amount);
        app(SaveExpense::class)->execute(['status' => 'cancelled'], $first);
        app(SaveExpense::class)->execute(['amount' => '100.00', 'status' => 'paid'], $second);
        $this->assertSame('100.00', $second->fresh()->amount);
        $this->assertDatabaseCount('expenses', 2);
    }

    public function test_manual_income_moves_from_expected_to_received_once_and_can_be_cancelled(): void
    {
        $data = ['title' => 'Rimborso di prova', 'amount' => '50.25', 'expected_on' => '2026-09-20', 'status' => 'expected'];
        $this->post(route('expenses.incomes.store'), $data)->assertSessionHasNoErrors();
        $income = ManualIncome::sole();
        $this->assertSame(5025, app(CashFlowForecast::class)->calculate(6, null)['totals']['expected']);
        $data['status'] = 'received';
        $this->put(route('expenses.incomes.update', $income), $data)->assertSessionHasErrors('received_on');
        $data['received_on'] = '2026-09-18';
        $this->put(route('expenses.incomes.update', $income), $data)->assertSessionHasErrors('received_on');
        $data['received_on'] = '2026-09-17';
        $this->put(route('expenses.incomes.update', $income), $data)->assertSessionHasNoErrors();
        $totals = app(CashFlowForecast::class)->calculate(6, null)['totals'];
        $this->assertSame(5025, $totals['received']);
        $this->assertSame(0, $totals['expected']);
        $data['status'] = 'cancelled';
        $this->put(route('expenses.incomes.update', $income), $data)->assertSessionHasNoErrors();
        $this->assertNull($income->fresh()->received_on);
        $this->assertSame(0, app(CashFlowForecast::class)->calculate(6, null)['totals']['received']);
    }

    public function test_forecast_combines_residual_invoices_actual_movements_overdue_and_opening_balance_without_duplicates(): void
    {
        $invoice = $this->invoice(['total' => '100.30', 'due_date' => '2026-10-10']);
        app(RegisterPaymentAction::class)->execute(['invoice_id' => $invoice->id, 'amount' => '40.10', 'payment_date' => '2026-09-17', 'method' => 'bank_transfer']);
        $this->assertSame('40.10', $invoice->fresh()->paid_total);
        $this->invoice(['number' => 'OLD', 'total' => '15.20', 'due_date' => '2026-08-31']);
        $this->invoice(['number' => 'NO-DUE', 'total' => '10.00', 'due_date' => null]);
        $this->invoice(['number' => 'DRAFT', 'total' => '999.00', 'status' => 'draft']);
        $this->invoice(['number' => 'CANCEL', 'total' => '999.00', 'status' => 'cancelled']);
        $this->invoice(['number' => 'LATER', 'total' => '999.00', 'due_date' => '2028-01-01']);
        $foreign = $this->invoice(['number' => 'USD', 'total' => '500.00', 'currency' => 'USD']);
        app(RegisterPaymentAction::class)->execute(['invoice_id' => $foreign->id, 'amount' => '100', 'payment_date' => '2026-09-17', 'method' => 'cash']);
        $old = $this->invoice(['number' => 'BEFORE', 'total' => '80.00']);
        app(RegisterPaymentAction::class)->execute(['invoice_id' => $old->id, 'amount' => '80', 'payment_date' => '2026-09-16', 'method' => 'cash']);
        $this->expense(['amount' => '20.10', 'due_date' => '2026-08-31']);
        $this->expense(['amount' => '30.20', 'status' => 'paid', 'paid_at' => '2026-09-17']);
        $this->expense(['amount' => '999.00', 'status' => 'paid', 'paid_at' => '2026-09-16']);
        $this->expense(['amount' => '999.00', 'status' => 'cancelled']);
        ManualIncome::create(['user_id' => $this->admin->id, 'title' => 'Rimborso', 'amount' => '7.05', 'expected_on' => '2026-10-01', 'status' => 'expected']);
        $setting = CashFlowSetting::create(['id' => 1, 'opening_balance' => '100.00', 'balance_date' => '2026-09-17', 'updated_by' => $this->admin->id]);
        $forecast = app(CashFlowForecast::class)->calculate(6, $setting);
        $this->assertSame(['received' => 4010, 'expected' => 9245, 'paid' => 3020, 'pending' => 2010], $forecast['totals']);
        $this->assertSame(['incoming' => 1520, 'outgoing' => 2010], $forecast['overdue']);
        $this->assertSame(11500, $forecast['rows']['2026-09']['closing_balance']);
        $this->assertSame(18225, $forecast['rows']['2026-10']['closing_balance']);
        $this->assertSame(1, $forecast['missingDueDates']);
        $this->assertSame(1, $forecast['excludedCurrencies']);
        $this->assertCount(6, $forecast['rows']);
        $this->assertNull(app(CashFlowForecast::class)->calculate(6, null)['rows']['2026-09']['closing_balance']);
        $this->get(route('expenses.forecast'))->assertOk()->assertSee('valuta diversa')->assertSee('Saldo previsto');
        $this->assertSame(-123, CashAmount::cents('-1.23'));
        $this->assertSame(123, CashAmount::cents('+1.23'));
        $this->assertSame(50, CashAmount::cents('.50'));
        $this->assertSame(100, CashAmount::cents('1.'));
    }

    public function test_document_upload_is_private_deduplicated_and_has_a_real_download(): void
    {
        $data = $this->documentData();
        $data['file'] = UploadedFile::fake()->createWithContent('fattura.xml', '<fattura>Prova</fattura>');
        $this->post(route('expenses.documents.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $document = ExpenseDocument::sole();
        $this->post(route('expenses.documents.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('expense_documents', 1);
        $this->assertCount(1, Storage::disk('attachments')->allFiles('expense-documents'));
        $this->get(route('expenses.documents.download', $document))->assertOk()->assertDownload('fattura.xml');
        $data['amount'] = '101.00';
        $this->post(route('expenses.documents.store'), $data)->assertSessionHasErrors('document');
        $this->assertSame('100.00', $document->fresh()->amount);
        $this->assertCount(1, Storage::disk('attachments')->allFiles('expense-documents'));
    }

    public function test_finance_pages_render_and_administration_sees_the_shared_expenses(): void
    {
        $expense = $this->expense(['title' => 'Spesa condivisa riservata']);
        $recurrence = $this->recurrence();
        $document = $this->document();
        $income = ManualIncome::create(['user_id' => $this->admin->id, 'title' => 'Entrata test', 'amount' => '1.00', 'expected_on' => '2026-09-17', 'status' => 'expected']);
        $administration = User::factory()->create(['role' => UserRole::Administration]);
        foreach ([$this->admin, $administration] as $user) {
            $this->actingAs($user);
            foreach (['expenses.index', 'expenses.create', 'expenses.recurrences.index', 'expenses.recurrences.create', 'expenses.incomes.index', 'expenses.incomes.create', 'expenses.documents.index', 'expenses.documents.create', 'expenses.documents.aruba', 'expenses.forecast'] as $route) {
                $this->get(route($route))->assertOk();
            }
            foreach (['expenses.show' => $expense, 'expenses.edit' => $expense, 'expenses.recurrences.edit' => $recurrence, 'expenses.documents.show' => $document, 'expenses.incomes.edit' => $income] as $route => $model) {
                $this->get(route($route, $model))->assertOk();
            }
            $this->get(route('expenses.index'))->assertSee('Spesa condivisa riservata');
            $this->get(route('expenses.edit', ['expense' => $expense, 'document_id' => $document->id]))->assertOk();
            $this->get(route('expenses.create', ['document_id' => $document->id]))->assertOk();
        }
        $this->put(route('expenses.forecast.balance'), ['opening_balance' => '-200.25', 'balance_date' => '2026-09-17'])->assertSessionHasNoErrors();
        $this->assertSame('-200.25', CashFlowSetting::find(1)->opening_balance);
    }

    public function test_documents_distinguish_issuer_countries_and_a_missing_file_can_be_reloaded(): void
    {
        $document = $this->document();
        $foreign = $this->document(['issuer_country' => 'DE']);
        $this->assertNotSame($document->id, $foreign->id);
        $this->assertDatabaseCount('expense_documents', 2);
        Storage::disk('attachments')->delete($document->path);
        $reloaded = $this->document();
        $this->assertSame($document->id, $reloaded->id);
        $this->assertNotSame($document->path, $reloaded->path);
        Storage::disk('attachments')->assertExists($reloaded->path);
        $this->assertCount(2, Storage::disk('attachments')->allFiles('expense-documents'));
    }

    public function test_migration_preserves_legacy_expenses_in_both_directions(): void
    {
        $expense = $this->expense(['amount' => '87.35', 'status' => 'paid', 'paid_at' => '2026-09-10']);
        $migration = require database_path('migrations/2026_09_17_100000_add_recurring_expenses_and_cash_flow.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('expense_recurrences'));
        $this->assertFalse(Schema::hasColumn('expenses', 'expense_document_id'));
        $this->assertSame('87.35', $expense->fresh()->amount);
        $this->assertSame('paid', $expense->fresh()->status);
        $migration->up();
        $this->assertTrue(Schema::hasTable('expense_recurrences'));
        $this->assertTrue(Schema::hasColumn('expenses', 'expense_document_id'));
        $this->assertNull($expense->fresh()->expense_document_id);
        $this->assertSame('2026-09-10', $expense->fresh()->paid_at->toDateString());
    }

    public function test_country_upgrade_preserves_documents_from_the_initial_expense_schema(): void
    {
        $document = $this->document()->fresh();
        $before = $document->getRawOriginal();
        unset($before['issuer_country']);
        Schema::table('expense_documents', fn ($table) => $table->dropColumn('issuer_country'));
        $migration = require database_path('migrations/2026_09_19_110000_add_issuer_country_to_expense_documents.php');
        $migration->up();
        $migration->up();

        $after = $document->fresh()->getRawOriginal();
        $this->assertNull($after['issuer_country']);
        unset($after['issuer_country']);
        $this->assertEquals($before, $after);
        Storage::disk($document->disk)->assertExists($document->path);

        $document->update(['issuer_country' => 'DE']);
        $migration->up();
        $migration->down();
        $this->assertSame('DE', $document->fresh()->issuer_country);
    }

    public function test_non_finance_roles_cannot_read_documents_or_mutate_finance(): void
    {
        $document = $this->document();
        $recurrence = $this->recurrence();
        foreach (UserRole::cases() as $role) {
            if (in_array($role, [UserRole::Admin, UserRole::Administration])) {
                continue;
            }
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['expenses.index', 'expenses.recurrences.index', 'expenses.incomes.index', 'expenses.documents.index', 'expenses.forecast', 'expenses.documents.aruba'] as $route) {
                $this->get(route($route))->assertForbidden();
            }
            $this->get(route('expenses.documents.download', $document))->assertForbidden();
            $this->get(route('expenses.documents.show', $document))->assertForbidden();
            $this->post(route('expenses.recurrences.store'), $this->recurrenceData())->assertForbidden();
            $this->put(route('expenses.recurrences.update', $recurrence), $this->recurrenceData())->assertForbidden();
            $this->post(route('expenses.documents.store'), [])->assertForbidden();
            $this->post(route('expenses.documents.aruba.import'), [])->assertForbidden();
            $this->post(route('expenses.incomes.store'), [])->assertForbidden();
            $this->put(route('expenses.forecast.balance'), [])->assertForbidden();
        }
        $this->assertDatabaseCount('manual_incomes', 0);
        $this->assertDatabaseCount('cash_flow_settings', 0);
    }

    public function test_user_deletion_keeps_financial_history_and_returns_a_clear_message(): void
    {
        $expense = $this->expense();
        $this->recurrence();
        $otherAdmin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active', 'password_changed_at' => now()]);
        $this->actingAs($otherAdmin)->from(route('users.index'))->delete(route('users.destroy', $this->admin))->assertRedirect(route('users.index'))->assertSessionHas('error');
        $this->assertModelExists($this->admin);
        $this->assertModelExists($expense);
        $this->actingAs($this->admin)->from(route('profile.edit'))->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('profile.edit'))->assertSessionHas('error');
        $this->assertAuthenticatedAs($this->admin);
        $this->assertModelExists($expense);
    }

    public function test_livewire_actions_recheck_permissions_after_a_role_change(): void
    {
        $expense = $this->expense();
        $component = Livewire::test(ExpenseShow::class, ['expense' => $expense]);
        $this->admin->update(['role' => UserRole::Commercial]);
        $component->call('markAsCancelled')->assertForbidden();
        $this->assertSame('pending', $expense->fresh()->status);
    }

    private function recurrenceData(array $overrides = []): array
    {
        return array_replace(['title' => 'Abbonamento di prova', 'amount' => '29.99', 'supplier' => 'Fornitore test', 'document_kind' => 'invoice', 'frequency' => 'monthly', 'starts_on' => '2026-09-17', 'ends_on' => null, 'active' => true], $overrides);
    }

    private function recurrence(array $overrides = []): ExpenseRecurrence
    {
        return ExpenseRecurrence::create($this->recurrenceData($overrides) + ['user_id' => $this->admin->id]);
    }

    private function expenseData(array $overrides = []): array
    {
        return array_replace(['title' => 'Spesa di prova', 'amount' => '50.00', 'expense_date' => '2026-09-17', 'due_date' => '2026-09-30', 'status' => 'pending', 'document_kind' => 'invoice'], $overrides);
    }

    private function expense(array $overrides = []): Expense
    {
        return Expense::create($this->expenseData($overrides) + ['user_id' => $this->admin->id]);
    }

    private function documentData(array $overrides = []): array
    {
        return array_replace(['kind' => 'invoice', 'issuer' => 'Fornitore di prova', 'issuer_identifier' => '00000000000', 'issuer_country' => 'IT', 'number' => 'TEST-01', 'document_date' => '2026-09-17', 'amount' => '100.00'], $overrides);
    }

    private function document(array $overrides = []): ExpenseDocument
    {
        return app(StoreExpenseDocument::class)->execute($this->documentData($overrides), '<fattura>Prova</fattura>', 'prova.xml');
    }

    private function invoice(array $overrides = []): Invoice
    {
        return Invoice::create(array_replace(['client_id' => Client::factory()->create()->id, 'created_by' => $this->admin->id, 'number' => 'INV-TEST', 'status' => 'issued', 'issue_date' => '2026-09-01', 'due_date' => '2026-09-30', 'currency' => 'EUR', 'total' => '100.00', 'paid_total' => '0.00'], $overrides));
    }
}
