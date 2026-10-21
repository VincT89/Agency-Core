<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\SaveExpense;
use App\Domain\Finance\Actions\SaveExpenseCategory;
use App\Domain\Finance\Actions\RegisterPaymentAction;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Enums\UserRole;
use App\Livewire\Expenses\ExpenseCategories;
use App\Livewire\Expenses\ExpenseForm;
use App\Livewire\Expenses\ExpensesIndex;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HostingService;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Services\EconomicSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 10, 20));
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]));
        $this->client = Client::factory()->create();
        $this->project = Project::create(['client_id' => $this->client->id, 'name' => 'Progetto prova', 'slug' => 'progetto-prova', 'status' => 'active']);
    }

    private function invoice(array $data = []): Invoice
    {
        return Invoice::create(array_replace([
            'number' => 'FPR '.(Invoice::count() + 1).'/26', 'client_id' => $this->client->id,
            'project_id' => $this->project->id, 'created_by' => auth()->id(), 'issue_date' => '2026-10-01',
            'status' => 'issued', 'currency' => 'EUR', 'subtotal' => 100, 'tax_amount' => 22, 'total' => 122,
            'paid_total' => 0, 'fiscal_document_type' => 'TD01',
        ], $data));
    }

    private function service(array $data = []): HostingService
    {
        return HostingService::create(array_replace([
            'client_id' => $this->client->id, 'name' => 'Hosting di prova', 'type' => 'hosting', 'status' => 'active',
        ], $data));
    }

    public function test_categories_can_be_created_inline_and_subcategories_must_match_the_parent(): void
    {
        $form = Livewire::test(ExpenseForm::class)->set('newCategory', 'Servizi operativi')->call('createCategory')->assertHasNoErrors();
        $category = ExpenseCategory::sole();
        $form->assertSet('category_id', $category->id)->set('newSubcategory', 'Licenze')->call('createCategory', true)->assertHasNoErrors();
        $subcategory = ExpenseCategory::whereNotNull('parent_id')->sole();
        $form->set('title', 'Spesa di prova')->set('amount', 85)->set('expense_date', '2026-10-01')->call('save')->assertHasNoErrors();
        $expense = Expense::sole();
        $this->assertEquals($category->id, $expense->category_id);
        $this->assertEquals($subcategory->id, $expense->subcategory_id);
        $other = app(SaveExpenseCategory::class)->execute(['name' => 'Altra categoria']);
        Livewire::test(ExpenseForm::class, ['expense' => $expense])->set('category_id', $other->id)
            ->set('subcategory_id', $subcategory->id)->call('save')->assertHasErrors('subcategory_id');
        $this->assertEquals($category->id, $expense->fresh()->category_id);
    }

    public function test_category_rename_archive_duplicate_and_legacy_compatibility(): void
    {
        $expense = app(SaveExpense::class)->execute(['title' => 'Spesa esistente', 'amount' => 10, 'expense_date' => '2026-10-01',
            'status' => 'pending', 'category' => 'Software', 'document_kind' => 'invoice']);
        $category = $expense->categoryGroup;
        Livewire::test(ExpenseCategories::class)->call('edit', $category->id)->set('name', 'Licenze software')->set('active', false)
            ->call('save')->assertHasNoErrors();
        $this->assertEquals('Licenze software', $expense->fresh()->category);
        $this->assertFalse($category->fresh()->active);
        Livewire::test(ExpenseCategories::class)->set('name', '  licenze  software ')->call('save')->assertHasErrors('name');
        Livewire::test(ExpenseForm::class)->set('title', 'Nuova')->set('amount', 20)->set('category_id', $category->id)
            ->call('save')->assertHasErrors('category_id');
        Livewire::test(ExpenseForm::class, ['expense' => $expense])->set('title', 'Titolo aggiornato')->call('save')->assertHasNoErrors();
    }

    public function test_expense_groups_filters_and_pagination_use_the_entire_filtered_dataset(): void
    {
        $category = app(SaveExpenseCategory::class)->execute(['name' => 'Servizi']);
        for ($i = 0; $i < 27; $i++) {
            Expense::create(['user_id' => auth()->id(), 'title' => 'Spesa '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'amount' => 10, 'category_id' => $category->id, 'expense_date' => '2026-10-10', 'status' => 'pending']);
        }
        $view = Livewire::test(ExpensesIndex::class)->assertSee('27 spese')->assertSee('270,00')
            ->assertViewHas('expenses', fn ($expenses) => $expenses->count() === 20 && $expenses->total() === 27);
        $view->set('from', '2026-10-11')->assertViewHas('expenses', fn ($expenses) => $expenses->total() === 0);
        $view->set('from', '2026-10-01')->set('search', 'Spesa 26')->assertViewHas('expenses', fn ($expenses) => $expenses->total() === 1);
    }

    public function test_expense_category_badges_only_appear_after_assignment_and_filter_without_changing_other_filters(): void
    {
        $category = app(SaveExpenseCategory::class)->execute(['name' => 'Licenze operative']);
        app(SaveExpenseCategory::class)->execute(['name' => 'Categoria non usata']);
        $child = app(SaveExpenseCategory::class)->execute(['name' => 'Software', 'parent_id' => $category->id]);
        $view = Livewire::test(ExpensesIndex::class)->assertDontSeeHtml('class="finance-category-badges"');
        $expense = app(SaveExpense::class)->execute(['title' => 'Spesa senza classificazione', 'amount' => 10,
            'expense_date' => '2026-10-10', 'status' => 'pending', 'document_kind' => 'invoice']);
        $view->call('$refresh')->assertDontSeeHtml('class="finance-category-badges"');
        Livewire::test(ExpenseForm::class, ['expense' => $expense])->set('category_id', $category->id)
            ->set('subcategory_id', $child->id)->call('save')->assertHasNoErrors();
        app(SaveExpense::class)->execute(['title' => 'Altra spesa senza categoria', 'amount' => 20,
            'expense_date' => '2026-10-10', 'status' => 'pending', 'document_kind' => 'invoice']);
        $view->call('$refresh')->assertSee('Licenze operative (1)')->assertDontSee('Categoria non usata')
            ->set('status', 'pending')->set('from', '2026-10-01')->set('subcategory_id', (string) $child->id)
            ->call('selectCategory', (string) $category->id)->assertSet('subcategory_id', '')
            ->assertSet('status', 'pending')->assertSet('from', '2026-10-01')
            ->assertViewHas('expenses', fn ($expenses) => $expenses->total() === 1 && $expenses->first()->id === $expense->id)
            ->call('selectCategory', '')->assertViewHas('expenses', fn ($expenses) => $expenses->total() === 2);
    }

    public function test_subcategory_badges_filter_the_selected_parent_reset_pagination_and_reject_other_parents(): void
    {
        $saveCategory = app(SaveExpenseCategory::class);
        $parent = $saveCategory->execute(['name' => 'Strumenti']);
        $software = $saveCategory->execute(['name' => 'Software', 'parent_id' => $parent->id]);
        $hardware = $saveCategory->execute(['name' => 'Hardware', 'parent_id' => $parent->id]);
        $other = $saveCategory->execute(['name' => 'Trasferte']);
        $transport = $saveCategory->execute(['name' => 'Trasporti', 'parent_id' => $other->id]);
        foreach ([...array_fill(0, 22, $software), $hardware, null, $transport] as $index => $child) {
            Expense::create(['user_id' => auth()->id(), 'title' => 'Spesa '.($index + 1), 'amount' => 10,
                'category_id' => $child?->parent_id ?? $parent->id, 'subcategory_id' => $child?->id,
                'expense_date' => '2026-10-10', 'status' => 'pending']);
        }
        $view = Livewire::test(ExpensesIndex::class)->set('status', 'pending')->set('from', '2026-10-01')
            ->assertDontSeeHtml('class="finance-subcategory-filters"')->call('selectCategory', (string) $parent->id)
            ->assertViewHas('subcategories', fn ($children) => $children->modelKeys() === [$hardware->id, $software->id])
            ->call('gotoPage', 2)->assertSet('paginators.page', 2)
            ->call('selectSubcategory', (string) $hardware->id)->assertHasNoErrors()->assertSet('paginators.page', 1)
            ->assertSet('category_id', (string) $parent->id)->assertSet('status', 'pending')->assertSet('from', '2026-10-01')
            ->assertViewHas('expenses', fn ($expenses) => $expenses->total() === 1 && $expenses->first()->subcategory_id === $hardware->id)
            ->assertViewHas('subcategories', fn ($children) => $children->count() === 2)
            ->call('selectSubcategory', 'none')->assertViewHas('expenses', fn ($expenses) => $expenses->total() === 1 && $expenses->first()->subcategory_id === null)
            ->call('selectSubcategory', '')->assertViewHas('expenses', fn ($expenses) => $expenses->total() === 24)
            ->call('selectSubcategory', (string) $software->id)->assertViewHas('expenses', fn ($expenses) => $expenses->total() === 22)
            ->call('selectCategory', (string) $other->id)->assertSet('subcategory_id', '')
            ->assertViewHas('subcategories', fn ($children) => $children->modelKeys() === [$transport->id]);
        $view->call('selectSubcategory', (string) $hardware->id)->assertHasErrors('subcategory_id')->assertSet('subcategory_id', '');
        $view->call('selectCategory', '')->assertHasNoErrors()->assertDontSeeHtml('class="finance-subcategory-filters"');
        $view->call('selectSubcategory', (string) $software->id)->assertHasErrors('subcategory_id')->assertSet('subcategory_id', '');
    }

    public function test_imported_invoice_associations_preserve_fiscal_data_and_move_payment_attribution(): void
    {
        $invoice = $this->invoice(['fiscal_status' => InvoiceFiscalStatus::Imported, 'import_metadata' => [
            'number' => 'FPR 9/26', 'items' => [
                ['description' => 'Dominio', 'quantity' => '1', 'unit_price' => '30', 'vat_rate' => '22', 'total' => 30],
                ['description' => 'Hosting', 'quantity' => '1', 'unit_price' => '70', 'vat_rate' => '22', 'total' => 70],
            ],
        ]]);
        $otherProject = Project::create(['client_id' => $this->client->id, 'name' => 'Secondo progetto', 'slug' => 'secondo-progetto', 'status' => 'active']);
        $domain = $this->service(['name' => 'Dominio prova', 'type' => 'domain', 'domain' => 'example.test']);
        $hosting = $this->service();
        app(RegisterPaymentAction::class)->execute(['invoice_id' => $invoice->id, 'amount' => 20, 'payment_date' => '2026-10-10', 'method' => 'bank_transfer']);
        $before = $invoice->fresh()->only(['import_metadata', 'fiscal_status', 'subtotal', 'tax_amount', 'total', 'paid_total', 'status']);
        $this->patch(route('invoices.associations.update', $invoice), [
            'project_id' => $otherProject->id, 'services' => [0 => $domain->id, 1 => $hosting->id],
        ])->assertSessionHasNoErrors()->assertRedirect(route('invoices.show', $invoice));
        $invoice->refresh();
        $this->assertEquals($before, $invoice->only(array_keys($before)));
        $this->assertEquals($otherProject->id, $invoice->payments()->sole()->project_id);
        $this->assertCount(2, $invoice->importedServiceLinks);
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('Domini e hosting collegati')->assertSee('Dominio prova');
        $this->get(route('hosting-services.show', $domain))->assertOk()->assertSee('30,00')->assertSee('FPR 9/26');
        $this->assertEquals(1, $domain->linkedInvoices()->count());
        $this->assertEquals($otherProject->id, app(EconomicSummaryService::class)->summaryByProject(auth()->user())->sole()->project_id);
        $this->assertEquals(102, app(EconomicSummaryService::class)->summaryByProject(auth()->user())->sole()->total_outstanding);
    }

    public function test_cross_client_projects_services_and_forged_lines_are_rejected_atomically(): void
    {
        $invoice = $this->invoice(['fiscal_status' => InvoiceFiscalStatus::Imported,
            'import_metadata' => ['items' => [['description' => 'Hosting', 'total' => 100]]]]);
        $outsider = Client::factory()->create();
        $other = $this->service(['client_id' => $outsider->id]);
        $valid = $this->service();
        $project = Project::create(['client_id' => $outsider->id, 'name' => 'Esterno', 'slug' => 'esterno', 'status' => 'active']);
        $this->patch(route('invoices.associations.update', $invoice), ['project_id' => $project->id])->assertSessionHasErrors('project_id');
        $this->patch(route('invoices.associations.update', $invoice), ['project_id' => null, 'services' => [0 => $other->id]])->assertSessionHasErrors('services.0');
        $this->patch(route('invoices.associations.update', $invoice), ['project_id' => null, 'services' => [0 => $valid->id, 99 => $valid->id]])->assertSessionHasErrors('services');
        $this->assertEquals($this->project->id, $invoice->fresh()->project_id);
        $this->assertDatabaseCount('invoice_imported_service_links', 0);
        $this->get(route('finance.lookup', ['type' => 'services', 'client_id' => $this->client->id]))
            ->assertOk()->assertJsonMissing(['id' => $other->id])->assertJsonFragment(['id' => $valid->id]);
    }

    public function test_report_directory_is_bounded_and_details_filters_charts_and_comparison_agree(): void
    {
        $invoice = $this->invoice();
        $this->invoice(['issue_date' => '2026-09-01', 'subtotal' => 200, 'tax_amount' => 44, 'total' => 244]);
        $response = $this->get(route('economic-summary.show', ['group' => 'clients', 'id' => $this->client->id,
            'from' => '2026-10-01', 'to' => '2026-10-31', 'compare_from' => '2026-09-01', 'compare_to' => '2026-09-30']))->assertOk();
        $this->assertEquals(100, $response->viewData('globalSummary')['total_invoiced']);
        $this->assertEquals(200, $response->viewData('comparison')['total_invoiced']);
        $this->assertEquals([122], $response->viewData('chartData')['series'][2]['data']);
        $this->assertEquals([$invoice->id], $response->viewData('documents')->pluck('record_id')->all());
        for ($i = 0; $i < 24; $i++) {
            $client = Client::factory()->create(['name' => 'Cliente elenco '.$i]);
            $this->invoice(['client_id' => $client->id, 'project_id' => null]);
        }
        $page = $this->get(route('economic-summary.index'))->assertOk();
        $this->assertEquals(25, $page->viewData('directory')->total());
        $this->assertCount(20, $page->viewData('directory'));
        $this->assertNull($page->viewData('documents'));
        $this->get(route('economic-summary.index', ['directory_search' => 'Cliente elenco 23']))->assertOk()
            ->assertViewHas('directory', fn ($rows) => $rows->total() === 1);
        $this->get(route('economic-summary.index', ['client_id' => $this->client->id, 'min_amount' => 200]))->assertOk()
            ->assertViewHas('globalSummary', fn ($totals) => $totals['total_invoiced'] === 200.0);
    }

    public function test_invoice_sorting_is_numeric_for_aruba_sequences_and_retains_service_filters(): void
    {
        $second = $this->invoice(['number' => 'FPR 2/26']);
        $tenth = $this->invoice(['number' => 'FPR 10/26', 'issue_date' => '2026-10-02', 'total' => 300]);
        $this->get(route('invoices.index', ['sort' => 'number_asc']))->assertOk()
            ->assertViewHas('documents', fn ($rows) => $rows->pluck('id')->all() === [$second->id, $tenth->id]);
        $this->get(route('invoices.index', ['sort' => 'number_desc']))->assertOk()
            ->assertViewHas('documents', fn ($rows) => $rows->pluck('id')->all() === [$tenth->id, $second->id]);
        $this->get(route('invoices.index', ['sort' => 'date_asc']))->assertOk()
            ->assertViewHas('documents', fn ($rows) => $rows->first()->id === $second->id);
        $this->get(route('invoices.index', ['sort' => 'date_desc']))->assertOk()
            ->assertViewHas('documents', fn ($rows) => $rows->first()->id === $tenth->id);
        $this->get(route('invoices.index', ['sort' => 'amount_desc']))->assertOk()
            ->assertViewHas('documents', fn ($rows) => $rows->first()->id === $tenth->id);
        $service = $this->service();
        $second->items()->create(['description' => 'Hosting', 'quantity' => 1, 'unit_price' => 100, 'total' => 100, 'hosting_service_id' => $service->id]);
        $this->get(route('invoices.index', ['service_type' => 'hosting', 'sort' => 'date_desc']))->assertOk()
            ->assertViewHas('documents', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $second->id);
    }

    public function test_invoice_type_sorting_groups_both_document_types_before_pagination(): void
    {
        for ($i = 1; $i <= 17; $i++) {
            $this->invoice();
        }
        foreach (['2026-09-20', '2026-09-21'] as $date) {
            \App\Models\CreditNote::create([
                'client_id' => $this->client->id, 'created_by' => auth()->id(), 'number' => 'NC-'.$date,
                'issue_date' => $date, 'fiscal_document_type' => 'TD04', 'currency' => 'EUR',
                'subtotal' => 100, 'tax_amount' => 22, 'total' => 122, 'payable_total' => 122,
                'import_fingerprint' => hash('sha256', $date), 'import_source' => 'file', 'import_metadata' => [],
            ]);
        }
        $this->get(route('invoices.index', ['sort' => 'type_asc']))->assertOk()
            ->assertViewHas('documents', fn ($rows) => $rows->total() === 19
                && $rows->pluck('fiscal_document_type')->all() === array_fill(0, 15, 'TD01'));
        $this->get(route('invoices.index', ['sort' => 'type_asc', 'page' => 2]))->assertOk()
            ->assertViewHas('documents', fn ($rows) => $rows->pluck('fiscal_document_type')->all() === ['TD01', 'TD01', 'TD04', 'TD04']);
        $this->get(route('invoices.index', ['sort' => 'type_desc']))->assertOk()
            ->assertViewHas('documents', fn ($rows) => $rows->pluck('fiscal_document_type')->all() === ['TD04', 'TD04', ...array_fill(0, 13, 'TD01')]
                && $rows->first()->number === 'NC-2026-09-21');
        $this->get(route('invoices.index', ['sort' => 'type_desc', 'document_type' => 'TD04']))->assertOk()
            ->assertViewHas('documents', fn ($rows) => $rows->total() === 2 && $rows->every(fn ($row) => $row instanceof \App\Models\CreditNote));
    }

    public function test_native_invoice_services_are_saved_and_cannot_be_moved_to_another_customer(): void
    {
        $service = $this->service();
        $payload = ['client_id' => $this->client->id, 'project_id' => $this->project->id, 'number' => 'NATIVE-SERVICE',
            'issue_date' => '2026-10-01', 'status' => 'draft', 'currency' => 'EUR', 'subtotal' => 100, 'tax_amount' => 22,
            'items' => [['description' => 'Hosting', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 22, 'hosting_service_id' => $service->id]]];
        $this->post(route('invoices.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $invoice = Invoice::where('number', 'NATIVE-SERVICE')->sole();
        $item = $invoice->items()->sole();
        $this->assertEquals($service->id, $item->hosting_service_id);
        $payload['items'][0]['id'] = $item->id;
        $payload['items'][0]['description'] = 'Hosting aggiornato';
        $this->put(route('invoices.update', $invoice), $payload)->assertSessionHasNoErrors();
        $this->assertEquals('Hosting aggiornato', $item->fresh()->description);
        $outsider = Client::factory()->create();
        $otherService = $this->service(['client_id' => $outsider->id]);
        $payload['items'][0]['hosting_service_id'] = $otherService->id;
        $this->put(route('invoices.update', $invoice), $payload)->assertSessionHasErrors('items.0.hosting_service_id');
        $payload['client_id'] = $outsider->id;
        $payload['project_id'] = null;
        $this->put(route('invoices.update', $invoice), $payload)->assertSessionHasErrors('client_id');
        $this->assertEquals($this->client->id, $invoice->fresh()->client_id);
        $this->assertEquals($service->id, $item->fresh()->hosting_service_id);
    }

    public function test_optional_upper_filters_work_without_lower_bounds_and_invalid_orders_are_rejected(): void
    {
        $this->invoice();
        $this->get(route('economic-summary.index', ['to' => '2026-10-31', 'max_amount' => 200]))
            ->assertOk()->assertViewHas('globalSummary', fn ($totals) => $totals['total_invoiced'] === 100.0);
        $this->get(route('invoices.index', ['max_amount' => 200]))->assertOk()
            ->assertViewHas('documents', fn ($rows) => $rows->total() === 1);
        Livewire::test(ExpensesIndex::class)->set('to', '2026-10-31')->set('max_amount', 200)->assertHasNoErrors();
        $this->get(route('invoices.index', ['sort' => 'untrusted_sql']))->assertSessionHasErrors('sort');
    }

    public function test_larapex_charts_preserve_filtered_totals_and_group_minor_expense_categories(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $category = app(SaveExpenseCategory::class)->execute(['name' => 'Categoria '.$i]);
            app(SaveExpense::class)->execute(['title' => 'Spesa grafico', 'amount' => $i * 10, 'expense_date' => '2026-10-01',
                'status' => 'pending', 'category_id' => $category->id, 'document_kind' => 'invoice']);
        }
        $this->invoice();
        $response = $this->get(route('economic-summary.index', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk();
        $charts = $response->viewData('distributionCharts');
        $this->assertSame('bar', $charts[0]['options']['chart']['type']);
        $this->assertEquals([100], $charts[0]['options']['series'][0]->data);
        $this->assertSame('donut', $charts[1]['options']['chart']['type']);
        $this->assertCount(6, $charts[1]['entries']);
        $this->assertEquals(280, array_sum($charts[1]['options']['series']));
        $this->assertEquals('Altre categorie', $charts[1]['entries'][5]['name']);
        $this->assertEquals(30, $charts[1]['entries'][5]['amount']);
        $this->assertEquals([280], $response->viewData('trendCharts')[3]['series'][0]->data);
        $filtered = $this->get(route('economic-summary.index', ['max_amount' => 40]))->assertOk();
        $this->assertEquals(100, array_sum($filtered->viewData('distributionCharts')[1]['options']['series']));
        $this->assertEquals(0, $filtered->viewData('globalSummary')['total_invoiced']);
    }

    public function test_recurring_expenses_keep_the_selected_category_and_subcategory(): void
    {
        $category = app(SaveExpenseCategory::class)->execute(['name' => 'Ricorrenti']);
        $child = app(SaveExpenseCategory::class)->execute(['name' => 'Licenze', 'parent_id' => $category->id]);
        $payload = ['title' => 'Licenza ricorrente', 'amount' => 20, 'frequency' => 'monthly', 'starts_on' => '2026-11-01',
            'ends_on' => '2026-12-01', 'active' => true, 'document_kind' => 'invoice', 'category_id' => $category->id, 'subcategory_id' => $child->id];
        $this->post(route('expenses.recurrences.store'), $payload)->assertSessionHasNoErrors();
        $recurrence = \App\Models\ExpenseRecurrence::sole();
        $this->assertEquals(2, $recurrence->expenses()->where('category_id', $category->id)->where('subcategory_id', $child->id)->count());
        $other = app(SaveExpenseCategory::class)->execute(['name' => 'Consulenze']);
        $payload['category_id'] = $other->id;
        $this->put(route('expenses.recurrences.update', $recurrence), $payload)->assertSessionHasErrors('subcategory_id');
        $this->assertEquals($category->id, $recurrence->fresh()->category_id);
    }

    public function test_finance_mutations_and_lookups_are_forbidden_to_operational_accounts(): void
    {
        $invoice = $this->invoice();
        $this->actingAs(User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]));
        $this->patch(route('invoices.associations.update', $invoice), ['project_id' => null])->assertForbidden();
        $this->get(route('finance.lookup', ['type' => 'services', 'client_id' => $this->client->id]))->assertForbidden();
        $this->get(route('economic-summary.show', ['group' => 'clients', 'id' => $this->client->id]))->assertForbidden();
        Livewire::test(ExpenseCategories::class)->assertForbidden();
    }

    public function test_whole_invoice_service_replaces_project_and_preserves_fiscal_and_collection_data(): void
    {
        $invoice = $this->invoice(['fiscal_status' => InvoiceFiscalStatus::Imported,
            'import_metadata' => ['number' => 'FPR 1/26', 'items' => [['description' => 'Servizio', 'quantity' => '1', 'unit_price' => '100', 'vat_rate' => '22', 'total' => 100]]]]);
        $service = $this->service(['type' => 'domain', 'name' => 'Dominio della fattura']);
        $invoice->importedServiceLinks()->create(['line_index' => 0, 'hosting_service_id' => $service->id]);
        app(RegisterPaymentAction::class)->execute(['invoice_id' => $invoice->id, 'amount' => 20, 'payment_date' => '2026-10-10', 'method' => 'bank_transfer']);
        $before = $invoice->fresh()->only(['import_metadata', 'fiscal_status', 'subtotal', 'tax_amount', 'total', 'paid_total', 'status']);
        $totals = app(\App\Services\FinanceReportQuery::class)->totals([]);
        $this->get(route('invoices.associations.edit', $invoice))->assertOk()->assertSee('Nuovo progetto')->assertDontSee('Servizi collegati alle voci');
        $this->patch(route('invoices.associations.update', $invoice), ['assignment_type' => 'domain', 'hosting_service_id' => $service->id])
            ->assertSessionHasNoErrors()->assertRedirect(route('invoices.show', $invoice));
        $invoice->refresh();
        $this->assertNull($invoice->project_id);
        $this->assertNull($invoice->payments()->sole()->project_id);
        $this->assertEquals($service->id, $invoice->hosting_service_id);
        $this->assertDatabaseCount('invoice_imported_service_links', 0);
        $this->assertEquals($before, $invoice->only(array_keys($before)));
        $this->assertEquals($totals, app(\App\Services\FinanceReportQuery::class)->totals([]));
        $this->assertEquals(0, app(\App\Services\FinanceReportQuery::class)->totals(['project_id' => $this->project->id])['total_invoiced']);
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('Dominio della fattura');
        $this->get(route('hosting-services.show', $service))->assertOk()->assertSee('Intera fattura: 100,00');
        $this->get(route('invoices.index', ['service_type' => 'domain']))->assertOk()
            ->assertViewHas('documents', fn ($rows) => $rows->total() === 1);
        $this->patch(route('invoices.associations.update', $invoice), ['assignment_type' => 'project', 'project_id' => $this->project->id])->assertSessionHasNoErrors();
        $this->assertNull($invoice->fresh()->hosting_service_id);
        $this->assertEquals($this->project->id, $invoice->payments()->sole()->project_id);
        $this->assertEquals(0, $service->linkedInvoices()->count());
    }

    public function test_service_assignment_rejects_wrong_customer_type_and_archived_records(): void
    {
        $invoice = $this->invoice();
        $foreign = $this->service(['client_id' => Client::factory()->create()->id]);
        $domain = $this->service(['type' => 'domain']);
        $archived = $this->service();
        $archived->delete();
        foreach ([$foreign, $domain, $archived] as $service) {
            $this->patch(route('invoices.associations.update', $invoice), ['assignment_type' => 'hosting', 'hosting_service_id' => $service->id])
                ->assertSessionHasErrors('hosting_service_id');
        }
        $this->assertEquals($this->project->id, $invoice->fresh()->project_id);
        $this->assertNull($invoice->hosting_service_id);
        $hosting = $this->service(['password' => 'isolated-test-only']);
        $this->getJson(route('finance.lookup', ['type' => 'services', 'client_id' => $this->client->id, 'service_type' => 'hosting']))
            ->assertOk()->assertExactJson([['id' => $hosting->id, 'name' => $hosting->name]])->assertDontSee('isolated-test-only');
    }

    public function test_inline_project_creation_assigns_invoice_and_creator_without_duplicate_projects(): void
    {
        $invoice = $this->invoice(['fiscal_status' => InvoiceFiscalStatus::Imported]);
        $initialCount = Project::count();
        $payload = ['assignment_type' => 'new_project', 'new_project_name' => 'Nuovo intervento', 'new_project_description' => 'Descrizione progetto'];
        $this->patch(route('invoices.associations.update', $invoice), $payload)->assertSessionHasNoErrors();
        $project = Project::where('name', 'Nuovo intervento')->sole();
        $this->assertEquals($this->client->id, $project->client_id);
        $this->assertEquals($project->id, $invoice->fresh()->project_id);
        $this->assertTrue($project->users()->whereKey(auth()->id())->exists());
        $this->patch(route('invoices.associations.update', $invoice), $payload)->assertSessionHasErrors('new_project_name');
        $this->assertDatabaseCount('projects', $initialCount + 1);
        $this->actingAs(User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]));
        $payload['new_project_name'] = 'Non autorizzato';
        $this->patch(route('invoices.associations.update', $invoice), $payload)->assertForbidden();
        $this->assertDatabaseCount('projects', $initialCount + 1);
    }

    public function test_ajax_lists_validate_filters_preserve_context_and_escape_names(): void
    {
        $this->client->update(['name' => 'Cliente <script>test</script>']);
        $this->invoice();
        $response = $this->withHeaders(['X-Finance-Partial' => 'report-directory'])->getJson(route('economic-summary.index', [
            'directory_search' => 'Cliente', 'from' => '2026-10-01', 'group' => 'clients',
        ]))->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Cliente &lt;script&gt;test&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>test</script>', $html);
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringContainsString('name="from" value="2026-10-01"', $html);
        $this->withHeaders(['X-Finance-Partial' => 'report-directory'])->getJson(route('economic-summary.index', [
            'group' => 'projects', 'directory_search' => 'Progetto prova',
        ]))->assertOk()->assertJsonStructure(['html']);
        $response = $this->withHeaders(['X-Finance-Partial' => 'invoice-list'])->getJson(route('invoices.index', [
            'document_type' => 'TD01', 'sort' => 'number_desc', 'end_date' => '2026-10-31', 'client_id' => $this->client->id,
        ]))->assertOk();
        $this->assertStringContainsString('name="document_type" value="TD01"', $response->json('html'));
        $this->assertStringContainsString('aria-sort="descending"', $response->json('html'));
        $this->assertStringNotContainsString('id="invoice-sort"', $response->json('html'));
        $this->withHeaders(['X-Finance-Partial' => 'invoice-list'])->getJson(route('invoices.index', ['sort' => 'not_allowed']))->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->actingAs(User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]));
        $this->withHeaders(['X-Finance-Partial' => 'invoice-list'])->getJson(route('invoices.index'))->assertForbidden();
        $this->withHeaders(['X-Finance-Partial' => 'report-directory'])->getJson(route('economic-summary.index'))->assertForbidden();
    }
}
