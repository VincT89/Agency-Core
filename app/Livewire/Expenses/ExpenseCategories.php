<?php

namespace App\Livewire\Expenses;

use App\Domain\Finance\Actions\SaveExpenseCategory;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class ExpenseCategories extends Component
{
    use WithPagination;

    public string $search = '';
    public string $name = '';
    public $parent_id = '';
    public bool $active = true;
    #[Locked]
    public ?int $editingId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function edit(int $id): void
    {
        $this->authorize('create', Expense::class);
        $category = ExpenseCategory::findOrFail($id);
        $this->editingId = $id;
        $this->name = $category->name;
        $this->parent_id = $category->parent_id ?? '';
        $this->active = $category->active;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset('name', 'parent_id', 'active', 'editingId');
        $this->resetValidation();
    }

    public function save(): void
    {
        app(SaveExpenseCategory::class)->execute([
            'name' => $this->name, 'parent_id' => $this->parent_id ?: null, 'active' => $this->active,
        ], $this->editingId ? ExpenseCategory::findOrFail($this->editingId) : null);
        $this->cancel();
        session()->flash('success', 'Categoria salvata.');
    }

    public function render()
    {
        $this->authorize('create', Expense::class);
        return view('livewire.expenses.expense-categories', [
            'categories' => ExpenseCategory::with('parent')->when($this->search !== '', fn ($query) => $query
                ->where('name', 'like', '%'.$this->search.'%'))->orderBy('name')->paginate(20),
            'parents' => ExpenseCategory::whereNull('parent_id')->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => 'Categorie delle spese']);
    }
}
