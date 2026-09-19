<?php

namespace App\Livewire\Expenses;

use App\Models\Expense;
use App\Domain\Finance\Actions\SaveExpense;
use Livewire\Component;

class ExpenseShow extends Component
{
    #[\Livewire\Attributes\Locked]
    public Expense $expense;

    public function mount(Expense $expense)
    {
        abort_unless(auth()->user()->canAccessFinance(), 403);
        
        $this->expense = $expense;
    }

    public function markAsPaid()
    {
        $this->expense = app(SaveExpense::class)->execute([
            'status' => 'paid',
            'paid_at' => now(),
        ], $this->expense);
        
        session()->flash('success', 'Spesa segnata come pagata.');
    }

    public function markAsPending()
    {
        $this->expense = app(SaveExpense::class)->execute([
            'status' => 'pending',
            'paid_at' => null,
        ], $this->expense);
        
        session()->flash('success', 'Spesa riportata a "Da Pagare".');
    }

    public function markAsCancelled()
    {
        $this->expense = app(SaveExpense::class)->execute([
            'status' => 'cancelled',
            'paid_at' => null,
        ], $this->expense);
        
        session()->flash('success', 'Spesa annullata.');
    }

    public function render()
    {
        $this->authorize('view', $this->expense);
        $this->expense->load(['document', 'recurrence']);
        return view('livewire.expenses.expense-show')
            ->layout('layouts.app', ['title' => 'Spesa: '.$this->expense->title]);
    }
}
