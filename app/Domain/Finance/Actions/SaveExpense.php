<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Services\CashAmount;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SaveExpense
{
    public function execute(array $data, ?Expense $expense = null): Expense
    {
        Gate::authorize($expense ? 'update' : 'create', $expense ?? Expense::class);

        return DB::transaction(function () use ($data, $expense) {
            $expense = $expense ? Expense::lockForUpdate()->findOrFail($expense->id) : new Expense(['user_id' => auth()->id()]);
            $documentId = $data['expense_document_id'] ?? $expense->expense_document_id;
            if (array_key_exists('expense_document_id', $data)) {
                $documentId = $data['expense_document_id'] ?: null;
            }
            $status = $data['status'] ?? $expense->status;
            $kind = $data['document_kind'] ?? $expense->document_kind ?? 'invoice';
            $amount = $data['amount'] ?? $expense->amount;
            if ($status === 'paid' && ! $documentId) {
                throw ValidationException::withMessages(['expense_document_id' => 'Collega la fattura o il giustificativo prima di confermare il pagamento.']);
            }
            if ($documentId) {
                $document = ExpenseDocument::lockForUpdate()->findOrFail($documentId);
                if ($document->kind !== $kind || ! Storage::disk($document->disk)->exists($document->path)) {
                    throw ValidationException::withMessages(['expense_document_id' => 'Il documento deve essere disponibile e del tipo richiesto dalla spesa.']);
                }
                $allocated = $document->expenses()->where('status', '!=', 'cancelled')
                    ->when($expense->exists, fn ($query) => $query->whereKeyNot($expense->id))->sum('amount');
                if ($status !== 'cancelled' && CashAmount::cents($allocated) + CashAmount::cents($amount) > CashAmount::cents($document->amount)) {
                    throw ValidationException::withMessages(['expense_document_id' => 'L’importo delle spese collegate supera il totale del documento. Verifica eventuali duplicati o rate.']);
                }
            }
            $data['expense_document_id'] = $documentId;
            $data['paid_at'] = $status === 'paid' ? ($data['paid_at'] ?? $expense->paid_at ?? now()) : null;
            if ($expense->expense_recurrence_id) {
                $data['recurrence_overridden'] = true;
            }
            $expense->fill($data)->save();

            return $expense;
        });
    }
}
