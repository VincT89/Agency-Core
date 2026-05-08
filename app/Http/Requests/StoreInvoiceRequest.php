<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Http\Requests\Concerns\ValidatesProjectOwnership;

class StoreInvoiceRequest extends FormRequest
{
    use ValidatesProjectOwnership;
    public function authorize(): bool
    {
        return $this->user()->canAccessFinance();
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'project_id' => ['nullable', 'exists:projects,id', 'prohibits:marketing_campaign_id', 'required_without:marketing_campaign_id'],
            'marketing_campaign_id' => ['nullable', 'exists:marketing_campaigns,id', 'prohibits:project_id', 'required_without:project_id'],

            'number' => ['required', 'string', 'max:255', 'unique:invoices,number'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],

            'status' => ['required', Rule::in(Invoice::STATUSES)],
            'currency' => ['required', 'string', 'size:3'],

            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['required', 'numeric', 'min:0'],
            'paid_total' => ['nullable', 'numeric', 'min:0'],

            'items'                  => ['nullable', 'array'],
            'items.*.description'    => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity'       => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit_price'     => ['required_with:items', 'numeric', 'min:0'],

            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->withProjectOwnershipCheck($validator);

                if ($this->input('marketing_campaign_id') && $this->input('client_id')) {
                    $exists = \App\Models\MarketingCampaign::query()
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