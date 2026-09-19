<?php

namespace App\Livewire\Expenses;

use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Domain\Finance\Actions\SaveExpense;
use App\Domain\Finance\Services\CashAmount;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use Livewire\Component;

class ExpenseForm extends Component
{
    #[\Livewire\Attributes\Locked]
    public ?Expense $expense = null;

    public $title = '';
    public $description = '';
    public $amount = '';
    public $category = '';
    public $supplier = '';
    public $expense_date = '';
    public $due_date = '';
    public $status = 'pending';
    public $notes = '';
    public $document_kind = 'invoice';
    public $expense_document_id = '';
    public $paid_on = '';

    public $expenseable_type = '';
    public $expenseable_id = '';

    public $expenseableOptions = [];

    private const EXPENSEABLE_MAP = [
        'client' => Client::class,
        'project' => Project::class,
        'ticket' => Ticket::class,
        'task' => Task::class,
        'hosting_service' => \App\Models\HostingService::class,
    ];

    public function mount(Expense $expense = null)
    {
        abort_unless(auth()->user()->canAccessFinance(), 403);

        if ($expense && $expense->exists) {

            $this->expense = $expense;
            $this->title = $expense->title;
            $this->description = $expense->description;
            $this->amount = $expense->amount;
            $this->category = $expense->category;
            $this->supplier = $expense->supplier;
            $this->expense_date = $expense->expense_date ? $expense->expense_date->format('Y-m-d') : '';
            $this->due_date = $expense->due_date ? $expense->due_date->format('Y-m-d') : '';
            $this->status = $expense->status;
            $this->notes = $expense->notes;
            $this->document_kind = $expense->document_kind;
            $this->expense_document_id = $expense->expense_document_id ?? '';
            $this->paid_on = $expense->paid_at?->format('Y-m-d') ?? today()->toDateString();

            if ($expense->expenseable_type) {
                $type = array_search($expense->expenseable_type, self::EXPENSEABLE_MAP);
                if ($type !== false) {
                    $this->expenseable_type = $type;
                    $this->loadExpenseableOptions();
                    $this->expenseable_id = $expense->expenseable_id;
                }
            }
        } else {
            $this->expense_date = now()->format('Y-m-d');
            $this->paid_on = today()->toDateString();
        }
        if (request()->filled('document_id')) {
            $document = ExpenseDocument::findOrFail(request()->integer('document_id'));
            $this->expense_document_id = $document->id;
            if (! $this->expense) {
                $this->document_kind = $document->kind;
                $this->title = ExpenseDocument::KINDS[$document->kind].' '.$document->number;
                $this->supplier = $document->issuer;
                $allocated = $document->expenses()->where('status', '!=', 'cancelled')->sum('amount');
                $this->amount = CashAmount::decimal(max(0, CashAmount::cents($document->amount) - CashAmount::cents($allocated)));
                $this->expense_date = $document->document_date->toDateString();
                $this->due_date = $document->due_date?->toDateString() ?? '';
            }
        }
    }

    public function updatingExpenseableType()
    {
        $this->expenseable_id = '';
        $this->expenseableOptions = [];
    }

    public function updatedExpenseableType()
    {
        $this->loadExpenseableOptions();
    }

    protected function loadExpenseableOptions()
    {
        if (!$this->expenseable_type || !isset(self::EXPENSEABLE_MAP[$this->expenseable_type])) {
            $this->expenseableOptions = [];
            return;
        }

        $userId = auth()->id();
        $options = [];

        switch ($this->expenseable_type) {
            case 'client':
                if (auth()->user()->can('viewAny', Client::class)) {
                    $options = Client::orderBy('name')->get(['id', 'name as label'])->toArray();
                }
                break;
            case 'project':
                if (auth()->user()->canManageSystem() || auth()->user()->isAdministration()) {
                    $options = Project::orderBy('name')->get(['id', 'name as label'])->toArray();
                } else {
                    $options = Project::whereHas('users', function($q) use ($userId) {
                        $q->where('users.id', $userId);
                    })->orderBy('name')->get(['id', 'name as label'])->toArray();
                }
                break;
            case 'ticket':
                if (auth()->user()->canManageSystem() || auth()->user()->isAdministration()) {
                    $options = Ticket::orderBy('code', 'desc')->get(['id', 'title as label', 'code'])->map(function($t) {
                        return ['id' => $t->id, 'label' => "{$t->code} - {$t->label}"];
                    })->toArray();
                } else {
                    $options = Ticket::assignedTo($userId)->orderBy('code', 'desc')->get(['id', 'title as label', 'code'])->map(function($t) {
                        return ['id' => $t->id, 'label' => "{$t->code} - {$t->label}"];
                    })->toArray();
                }
                break;
            case 'task':
                if (auth()->user()->canManageSystem() || auth()->user()->isAdministration()) {
                    $options = Task::orderBy('id', 'desc')->get(['id', 'title as label'])->toArray();
                } else {
                    $options = Task::assignedTo($userId)->orderBy('id', 'desc')->get(['id', 'title as label'])->toArray();
                }
                break;
            case 'hosting_service':
                if (auth()->user()->canManageSystem() || auth()->user()->isAdministration()) {
                    $options = \App\Models\HostingService::orderBy('name')->get(['id', 'name as label'])->toArray();
                } else {
                    // For now, if no specific assignment, we'll allow viewing all or just specific ones based on client access
                    // Let's allow access if they have access to the client? Or just show all if no restriction is on the model
                    $options = \App\Models\HostingService::orderBy('name')->get(['id', 'name as label'])->toArray();
                }
                break;
        }

        $this->expenseableOptions = $options;
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|decimal:0,2|between:0.01,99999999.99',
            'expense_date' => 'required|date',
            'due_date' => 'nullable|date',
            'status' => 'required|in:pending,paid,cancelled',
            'category' => 'nullable|string|max:255',
            'supplier' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'document_kind' => 'required|in:invoice,payslip,receipt,other',
            'expense_document_id' => 'nullable|integer|exists:expense_documents,id',
            'paid_on' => 'nullable|required_if:status,paid|date_format:Y-m-d|before_or_equal:today',
            'expenseable_type' => 'nullable|string|in:client,project,ticket,task,hosting_service',
            'expenseable_id' => 'nullable|integer|required_with:expenseable_type',
        ];
    }

    public function save()
    {
        $this->authorize($this->expense ? 'update' : 'create', $this->expense ?? Expense::class);
        $this->validate();

        $data = [
            'title' => $this->title,
            'description' => $this->description,
            'amount' => $this->amount,
            'category' => $this->category,
            'supplier' => $this->supplier,
            'expense_date' => $this->expense_date,
            'due_date' => $this->due_date ?: null,
            'status' => $this->status,
            'notes' => $this->notes,
            'document_kind' => $this->document_kind,
            'expense_document_id' => $this->expense_document_id ?: null,
            'paid_at' => $this->status === 'paid' ? $this->paid_on : null,
            'expenseable_type' => null,
            'expenseable_id' => null,
        ];

        if ($this->expenseable_type && $this->expenseable_id) {
            // Additional security check: ensure user actually has access to the selected model
            $this->loadExpenseableOptions();
            $allowedIds = collect($this->expenseableOptions)->pluck('id')->toArray();
            if (!in_array($this->expenseable_id, $allowedIds)) {
                $this->addError('expenseable_id', 'Non hai accesso a questo elemento.');
                return;
            }

            $data['expenseable_type'] = self::EXPENSEABLE_MAP[$this->expenseable_type];
            $data['expenseable_id'] = $this->expenseable_id;
        }

        $this->expense = app(SaveExpense::class)->execute($data, $this->expense);
        session()->flash('success', 'Spesa salvata.');

        return redirect()->route('expenses.show', $this->expense);
    }

    public function render()
    {
        abort_unless(auth()->user()->canAccessFinance(), 403);
        return view('livewire.expenses.expense-form', ['documents' => ExpenseDocument::orderByDesc('document_date')->get(['id', 'kind', 'issuer', 'number', 'amount'])])
            ->layout('layouts.app', ['title' => $this->expense ? 'Modifica spesa' : 'Nuova spesa']);
    }
}
