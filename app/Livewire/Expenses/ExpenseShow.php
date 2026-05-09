<?php

namespace App\Livewire\Expenses;

use App\Models\Expense;
use Livewire\Component;

class ExpenseShow extends Component
{
    public Expense $expense;

    public function mount(Expense $expense)
    {
        abort_unless(auth()->user()->canAccessFinance(), 403);
        
        $this->expense = $expense;
    }

    public function markAsPaid()
    {
        $this->expense->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        
        session()->flash('success', 'Spesa segnata come pagata.');
    }

    public function markAsPending()
    {
        $this->expense->update([
            'status' => 'pending',
            'paid_at' => null,
        ]);
        
        session()->flash('success', 'Spesa riportata a "Da Pagare".');
    }

    public function markAsCancelled()
    {
        $this->expense->update([
            'status' => 'cancelled',
            'paid_at' => null,
        ]);
        
        session()->flash('success', 'Spesa annullata.');
    }

    public function render()
    {
        return view('livewire.expenses.expense-show')->layout('layouts.app');
    }
}
