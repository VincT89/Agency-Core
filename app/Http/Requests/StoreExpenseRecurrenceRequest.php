<?php

namespace App\Http\Requests;

use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Models\ExpenseRecurrence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRecurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Expense::class);
    }

    public function rules(): array
    {
        $recurrence = $this->route('recurrence');

        return [
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,99999999.99'],
            'category' => ['nullable', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'document_kind' => ['required', Rule::in(array_keys(ExpenseDocument::KINDS))],
            'frequency' => ['required', Rule::in($recurrence ? [$recurrence->frequency] : array_keys(ExpenseRecurrence::FREQUENCIES))],
            'starts_on' => ['required', 'date_format:Y-m-d', $recurrence ? Rule::in([$recurrence->starts_on->toDateString()]) : 'after_or_equal:'.today()->subYears(5)->toDateString()],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
