<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Http\Requests\Concerns\ValidatesProjectOwnership;

class UpdateInvoiceRequest extends FormRequest
{
    use ValidatesProjectOwnership;
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invoice'));
    }

    public function rules(): array
    {
        $invoice = $this->route('invoice');

        return [
            'client_id' => ['required', 'exists:clients,id'],
            'project_id' => ['required', 'exists:projects,id'],

            'number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('invoices', 'number')->ignore(is_object($invoice) ? $invoice->id : $invoice),
            ],

            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],

            'status' => ['required', Rule::in(Invoice::STATUSES)],
            'currency' => ['required', 'string', 'size:3'],

            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['required', 'numeric', 'min:0'],
            'paid_total' => ['nullable', 'numeric', 'min:0'],

            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->withProjectOwnershipCheck($validator);

                $subtotal = (float) $this->input('subtotal', 0);
                $taxAmount = (float) $this->input('tax_amount', 0);
                $paidTotal = (float) $this->input('paid_total', 0);

                $total = $subtotal + $taxAmount;

                if ($paidTotal > $total) {
                    $validator->errors()->add(
                        'paid_total',
                        'Il totale incassato non può superare il totale fattura.'
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper($this->input('currency', 'EUR')),
            'paid_total' => $this->input('paid_total', 0),
        ]);
    }
}