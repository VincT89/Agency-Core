<?php

namespace App\Http\Requests;

use App\Models\Expense;
use App\Models\ExpenseDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Expense::class);
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(array_keys(ExpenseDocument::KINDS))],
            'issuer' => ['required', 'string', 'max:255'],
            'issuer_identifier' => ['nullable', 'required_if:kind,invoice', 'string', 'max:50'],
            'issuer_country' => ['nullable', 'required_if:kind,invoice', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'number' => ['required', 'string', 'max:100'],
            'document_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,99999999.99'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xml'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('issuer_identifier'))) {
            $this->merge(['issuer_identifier' => strtoupper(trim($this->input('issuer_identifier')))]);
        }
        if (is_string($this->input('issuer_country'))) {
            $this->merge(['issuer_country' => strtoupper(trim($this->input('issuer_country')))]);
        }
    }
}
