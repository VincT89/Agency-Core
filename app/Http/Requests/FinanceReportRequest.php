<?php

namespace App\Http\Requests;

use App\Models\ExpenseCategory;
use App\Services\FinanceReportQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canAccessFinance();
    }

    public function rules(): array
    {
        return [
            'from' => 'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31',
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'compare_from' => 'nullable|required_with:compare_to|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31',
            'compare_to' => 'nullable|required_with:compare_from|date_format:Y-m-d|after_or_equal:compare_from|before_or_equal:2100-12-31',
            'client_id' => 'nullable|integer|exists:clients,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'q' => 'nullable|string|max:100',
            'directory_search' => 'nullable|string|max:100',
            'invoice_status' => ['nullable', Rule::in(FinanceReportQuery::STATUSES)],
            'expense_status' => 'nullable|in:pending,paid',
            'min_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'max_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99', ...($this->filled('min_amount') ? ['gte:min_amount'] : [])],
            'category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')->whereNull('parent_id')],
            'subcategory_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')->where('parent_id', $this->input('category_id', 0))],
            'group' => 'nullable|in:clients,projects,categories',
            'sort' => 'nullable|in:name_asc,name_desc,turnover_desc,turnover_asc,pending_desc,costs_desc',
            'interval' => 'nullable|in:month,quarter,year',
            'page' => 'nullable|integer|min:1|max:10000000',
            'documents_page' => 'nullable|integer|min:1|max:10000000',
            'documents' => 'nullable|boolean',
        ];
    }
}
