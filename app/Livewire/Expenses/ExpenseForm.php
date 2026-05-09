<?php

namespace App\Livewire\Expenses;

use App\Models\Expense;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use Livewire\Component;

class ExpenseForm extends Component
{
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
                    $options = Client::orderBy('company_name')->get(['id', 'company_name as label'])->toArray();
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
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'due_date' => 'nullable|date',
            'status' => 'required|in:pending,paid,cancelled',
            'category' => 'nullable|string|max:255',
            'supplier' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'expenseable_type' => 'nullable|string|in:client,project,ticket,task,hosting_service',
            'expenseable_id' => 'nullable|integer|required_with:expenseable_type',
        ];
    }

    public function save()
    {
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

        if ($this->expense) {
            // Update logic for paid_at
            if ($this->status === 'paid' && $this->expense->status !== 'paid') {
                $data['paid_at'] = now();
            } elseif ($this->status !== 'paid') {
                $data['paid_at'] = null;
            }
            
            $this->expense->update($data);
            session()->flash('success', 'Spesa aggiornata con successo.');
        } else {
            $data['user_id'] = auth()->id();
            if ($this->status === 'paid') {
                $data['paid_at'] = now();
            }
            $this->expense = Expense::create($data);
            session()->flash('success', 'Spesa creata con successo.');
        }

        return redirect()->route('expenses.show', $this->expense);
    }

    public function render()
    {
        return view('livewire.expenses.expense-form')->layout('layouts.app');
    }
}
