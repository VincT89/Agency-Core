<?php

namespace App\Http\Requests;

use App\Enums\Finance\VatNature;
use App\Http\Requests\Concerns\ValidatesInvoiceLines;
use App\Http\Requests\Concerns\ValidatesProjectOwnership;
use App\Models\Invoice;
use App\Models\MarketingCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInvoiceRequest extends FormRequest
{
    use ValidatesInvoiceLines;
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
            'project_id' => ['nullable', 'exists:projects,id', 'prohibits:marketing_campaign_id', 'required_without:marketing_campaign_id'],
            'marketing_campaign_id' => ['nullable', 'exists:marketing_campaigns,id', 'prohibits:project_id', 'required_without:project_id'],

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
            'fiscal_document_type' => ['required', 'in:TD01'],

            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['required', 'numeric', 'min:0'],
            'paid_total' => ['nullable', 'numeric', 'min:0'],

            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'integer', 'exists:invoice_items,id'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.unit_of_measure' => ['nullable', 'string', 'max:10'],
            'items.*.vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.vat_nature' => ['nullable', Rule::enum(VatNature::class)],
            'items.*.vat_reference' => ['nullable', 'string', 'max:255'],

            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->withProjectOwnershipCheck($validator);
                $this->addInvoiceLineTaxErrors($validator);

                if ($this->input('marketing_campaign_id') && $this->input('client_id')) {
                    $exists = MarketingCampaign::query()
                        ->where('id', $this->input('marketing_campaign_id'))
                        ->where('client_id', $this->input('client_id'))
                        ->exists();

                    if (! $exists) {
                        $validator->errors()->add(
                            'marketing_campaign_id',
                            'La campagna selezionata non appartiene al cliente indicato.'
                        );
                    }
                }

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

                $invoice = $this->route('invoice');

                if (
                    $invoice instanceof Invoice
                    && filled($invoice->fiscal_number)
                    && ! $validator->errors()->has('issue_date')
                    && filled($this->input('issue_date'))
                    && $invoice->issue_date?->format('Y') !== Carbon::parse($this->input('issue_date'))->format('Y')
                ) {
                    $validator->errors()->add(
                        'issue_date',
                        'Il numero fiscale è già riservato: la data deve restare nello stesso anno.'
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
            'fiscal_document_type' => strtoupper($this->input('fiscal_document_type', 'TD01')),
        ]);
    }
}
