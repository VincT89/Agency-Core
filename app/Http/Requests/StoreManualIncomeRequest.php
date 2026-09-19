<?php

namespace App\Http\Requests;

use App\Models\Expense;
use App\Models\ManualIncome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Expense::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'payer' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,99999999.99'],
            'expected_on' => ['required', 'date_format:Y-m-d'],
            'received_on' => ['nullable', 'required_if:status,received', 'date_format:Y-m-d', 'before_or_equal:today'],
            'status' => ['required', Rule::in(array_keys(ManualIncome::STATUSES))],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
