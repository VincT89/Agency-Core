<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Actions\ImportExpenseRow;
use App\Models\Expense;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ExpenseImportBatch
{
    public function process(array $rows, bool $apply = false): array
    {
        Gate::authorize('create', Expense::class);
        $result = [];
        foreach ($rows as $row) {
            $summary = ['filename' => $row['filename'], 'line' => $row['line'], 'title' => mb_substr((string) ($row['data']['title'] ?? ''), 0, 255)];
            try {
                $import = app(ImportExpenseRow::class)->execute($row, $apply);
                $summary += collect($import)->only(['status', 'message', 'id'])->all();
                $summary += collect($import['data'])->only(['amount', 'expense_date', 'due_date', 'supplier'])->all();
                if (isset($import['id'])) $summary['url'] = route('expenses.show', $import['id']);
            } catch (ValidationException $exception) {
                $summary += ['status' => 'error', 'message' => implode(' ', $exception->validator->errors()->all())];
            }
            $result[] = $summary;
        }
        return $result;
    }
}
