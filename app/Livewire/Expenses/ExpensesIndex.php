<?php

namespace App\Livewire\Expenses;

use App\Domain\Finance\Queries\ExpenseIndexQuery;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ExpensesIndex extends Component
{
    use WithPagination;

    public $status = '';
    public $expenseable_type = '';
    public $search = '';
    public $recurrence_id = '';
    public $category_id = '';
    public $subcategory_id = '';
    public $from = '';
    public $to = '';
    public $min_amount = '';
    public $max_amount = '';
    public $client_id = '';
    public $project_id = '';
    public $sort = 'due_asc';

    protected $queryString = [
        'status' => ['except' => ''], 'expenseable_type' => ['except' => ''], 'search' => ['except' => ''],
        'recurrence_id' => ['except' => ''], 'category_id' => ['except' => ''], 'subcategory_id' => ['except' => ''],
        'from' => ['except' => ''], 'to' => ['except' => ''], 'min_amount' => ['except' => ''], 'max_amount' => ['except' => ''],
        'client_id' => ['except' => ''], 'project_id' => ['except' => ''], 'sort' => ['except' => 'due_asc'],
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', Expense::class);
    }

    public function selectCategory(string $id): void
    {
        $this->category_id = $id;
        $this->subcategory_id = '';
        $this->resetValidation('subcategory_id');
        $this->resetPage();
    }

    public function selectSubcategory(string $id): void
    {
        $this->authorize('viewAny', Expense::class);
        if ($id !== '' && (! preg_match('/^[1-9][0-9]*$/', (string) $this->category_id)
            || ($id !== 'none' && (! preg_match('/^[1-9][0-9]*$/', $id)
                || ! ExpenseCategory::where('parent_id', $this->category_id)->whereKey($id)->exists())))) {
            throw ValidationException::withMessages(['subcategory_id' => 'Seleziona una sottocategoria della categoria scelta.']);
        }
        $this->subcategory_id = $id;
        $this->resetValidation('subcategory_id');
        $this->resetPage();
    }

    public function updated($field): void
    {
        if ($field !== 'paginators') {
            $this->resetPage();
        }
    }

    public function render()
    {
        $this->authorize('viewAny', Expense::class);
        $filters = $this->validate([
            'status' => 'nullable|in:pending,paid,cancelled', 'search' => 'nullable|string|max:100',
            'expenseable_type' => 'nullable|in:client,project,task,ticket,hosting_service',
            'recurrence_id' => 'nullable|integer|exists:expense_recurrences,id',
            'category_id' => ['nullable', 'regex:/^(none|[1-9][0-9]*)$/'],
            'subcategory_id' => ['nullable', 'regex:/^(none|[1-9][0-9]*)$/'],
            'from' => 'nullable|date_format:Y-m-d',
            'to' => ['nullable', 'date_format:Y-m-d', ...(filled($this->from) ? ['after_or_equal:from'] : [])],
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => ['nullable', 'numeric', 'min:0', ...(filled($this->min_amount) ? ['gte:min_amount'] : [])],
            'client_id' => 'nullable|integer|exists:clients,id', 'project_id' => 'nullable|integer|exists:projects,id',
            'sort' => 'required|in:due_asc,date_desc,date_asc,amount_desc,amount_asc',
        ]);
        $filters['expense_recurrence_id'] = $this->recurrence_id;
        $builder = app(ExpenseIndexQuery::class);
        $query = $builder->query($filters)->with(['expenseable', 'document', 'recurrence', 'categoryGroup', 'subcategory']);
        $query->leftJoin('expense_categories as root_category', 'root_category.id', '=', 'expenses.category_id')
            ->leftJoin('expense_categories as child_category', 'child_category.id', '=', 'expenses.subcategory_id')
            ->select('expenses.*')->orderBy('root_category.name')->orderBy('child_category.name');
        $column = match ($this->sort) { 'date_desc', 'date_asc' => 'expenses.expense_date', 'amount_desc', 'amount_asc' => 'expenses.amount', default => 'expenses.due_date' };
        $query->orderBy($column, str_ends_with($this->sort, '_desc') ? 'desc' : 'asc')->orderBy('expenses.id');
        $groups = $builder->query($filters, false)->leftJoin('expense_categories as root_category', 'root_category.id', '=', 'expenses.category_id')
            ->whereNotNull('expenses.category_id')
            ->selectRaw("expenses.category_id, COALESCE(root_category.name, 'Senza categoria') AS name, COUNT(*) AS count,
                SUM(CASE WHEN expenses.status = 'cancelled' THEN 0 ELSE expenses.amount END) AS amount")
            ->groupBy('expenses.category_id', 'root_category.name')->orderBy('root_category.name')->paginate(15, ['*'], 'categories_page');
        return view('livewire.expenses.expenses-index', [
            'expenses' => $query->paginate(20), 'groups' => $groups,
            'subcategories' => ExpenseCategory::whereNotNull('parent_id')->where('parent_id', is_numeric($this->category_id) ? $this->category_id : 0)->orderBy('name')->get(),
            'clientLabel' => $this->client_id ? Client::find($this->client_id)?->name : '',
            'projectLabel' => $this->project_id ? Project::find($this->project_id)?->name : '',
        ])->layout('layouts.app', ['title' => 'Spese']);
    }
}
