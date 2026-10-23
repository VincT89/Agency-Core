<?php

namespace App\Livewire\Expenses;

use App\Domain\Finance\Services\ExpenseImportBatch;
use App\Domain\Finance\Services\ExpenseImportReader;
use App\Models\Expense;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class ExpenseImport extends Component
{
    use WithFileUploads;

    public $file;
    public bool $confirmed = false;
    #[Locked]
    public ?string $fingerprint = null;
    #[Locked]
    public array $rows = [];
    #[Locked]
    public bool $completed = false;

    public function mount(): void
    {
        $this->authorize('create', Expense::class);
    }

    public function updatedFile(): void
    {
        $this->authorize('create', Expense::class);
        $this->reset('fingerprint', 'rows', 'confirmed', 'completed');
        $this->resetValidation();
    }

    public function preview(): void
    {
        $this->authorize('create', Expense::class);
        $this->updatedFile();
        $rows = $this->read();
        $this->rows = app(ExpenseImportBatch::class)->process($rows);
        $this->fingerprint = $this->sourceFingerprint();
    }

    public function import(): void
    {
        $this->authorize('create', Expense::class);
        $this->validate(['confirmed' => 'accepted']);
        $rows = $this->read();
        if ($this->completed || ! $this->fingerprint || ! hash_equals($this->fingerprint, $this->sourceFingerprint())) {
            throw ValidationException::withMessages(['file' => 'Il file è cambiato o manca l’anteprima: leggi nuovamente il file.']);
        }
        /** Re-read the unchanged upload and revalidate every row; browser preview values never become database input. */
        $this->rows = app(ExpenseImportBatch::class)->process($rows, true);
        $this->completed = true;
        $this->fingerprint = null;
        $this->file->delete();
        $this->file = null;
    }

    private function read(): array
    {
        $this->validate(['file' => 'required|file|max:10240|extensions:xls,xlsx,csv,xml,zip']);
        return app(ExpenseImportReader::class)->read($this->file->getRealPath(), $this->file->getClientOriginalName());
    }

    private function sourceFingerprint(): string
    {
        return hash('sha256', $this->file->getClientOriginalName().'|'.hash_file('sha256', $this->file->getRealPath()));
    }

    public function render()
    {
        $this->authorize('create', Expense::class);
        return view('livewire.expenses.expense-import')->layout('layouts.app', ['title' => 'Importa spese']);
    }
}
