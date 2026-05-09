<?php

namespace App\Livewire\Expenses;

use App\Models\Expense;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use Livewire\Component;
use Livewire\WithPagination;

class ExpensesIndex extends Component
{
    use WithPagination;

    public function mount()
    {
        abort_unless(auth()->user()->canAccessFinance(), 403);
    }

    public $status = '';
    public $expenseable_type = '';
    public $search = '';

    protected $queryString = [
        'status' => ['except' => ''],
        'expenseable_type' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function updatingExpenseableType()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Expense::query()
            ->ownedBy(auth()->id())
            ->with(['expenseable', 'attachments']);

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->expenseable_type) {
            $map = [
                'client' => Client::class,
                'project' => Project::class,
                'ticket' => Ticket::class,
                'task' => Task::class,
            ];

            if (isset($map[$this->expenseable_type])) {
                $query->where('expenseable_type', $map[$this->expenseable_type]);
            }
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                  ->orWhere('supplier', 'like', '%' . $this->search . '%')
                  ->orWhere('category', 'like', '%' . $this->search . '%');
            });
        }

        // Default ordering: pending first, due_date asc, created_at desc
        $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
              ->orderBy('due_date', 'asc')
              ->orderBy('created_at', 'desc');

        $expenses = $query->paginate(20);

        return view('livewire.expenses.expenses-index', [
            'expenses' => $expenses,
        ])->layout('layouts.app');
    }
}
