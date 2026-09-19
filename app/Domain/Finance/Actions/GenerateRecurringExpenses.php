<?php

namespace App\Domain\Finance\Actions;

use App\Models\ExpenseRecurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GenerateRecurringExpenses
{
    public function execute(ExpenseRecurrence $recurrence, ?CarbonImmutable $through = null, ?CarbonImmutable $cancelMissingBefore = null): int
    {
        $through ??= CarbonImmutable::today()->addMonthsNoOverflow(23)->endOfMonth();

        return DB::transaction(function () use ($recurrence, $through, $cancelMissingBefore) {
            $recurrence = ExpenseRecurrence::lockForUpdate()->findOrFail($recurrence->id);
            if (! $recurrence->active) {
                return 0;
            }
            $end = $recurrence->ends_on?->min($through) ?? $through;
            $created = 0;
            for ($index = 0; ($date = $recurrence->occurrence($index))->lte($end); $index++) {
                $expense = $recurrence->expenses()->whereDate('recurrence_date', $date->toDateString())->firstOrCreate([], [
                    'recurrence_date' => $date,
                    'user_id' => $recurrence->user_id, 'title' => $recurrence->title,
                    'amount' => $recurrence->amount, 'category' => $recurrence->category,
                    'supplier' => $recurrence->supplier, 'document_kind' => $recurrence->document_kind,
                    'expense_date' => $date, 'due_date' => $date,
                    'status' => $cancelMissingBefore && $date->lt($cancelMissingBefore) ? 'cancelled' : 'pending',
                    'notes' => $recurrence->notes,
                ]);
                $created += (int) $expense->wasRecentlyCreated;
            }

            return $created;
        });
    }

    public function updateFuture(ExpenseRecurrence $recurrence): void
    {
        // Never rewrite paid, documented, manually changed or past occurrences.
        $future = $recurrence->expenses()->where('recurrence_date', '>=', today()->toDateString())
            ->whereIn('status', ['pending', 'cancelled'])->whereNull('expense_document_id')->where('recurrence_overridden', false);
        if (! $recurrence->active) {
            $future->update(['status' => 'cancelled']);

            return;
        }
        if ($recurrence->ends_on) {
            (clone $future)->where('recurrence_date', '>', $recurrence->ends_on)->update(['status' => 'cancelled']);
            $future->where('recurrence_date', '<=', $recurrence->ends_on);
        }
        $future->update(array_merge($recurrence->only(['title', 'amount', 'category', 'supplier', 'document_kind', 'notes']), ['status' => 'pending']));
    }
}
