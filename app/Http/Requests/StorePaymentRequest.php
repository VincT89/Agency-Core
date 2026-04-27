<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = Invoice::query()->find($this->input('invoice_id'));
        if (! $invoice) {
            return false;
        }

        return $this->user()->can('update', $invoice);
    }

    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'exists:invoices,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $invoiceId = $this->input('invoice_id');
                $amount = (float) $this->input('amount', 0);

                $invoice = Invoice::query()->find($invoiceId);

                if (! $invoice) {
                    return;
                }

                $alreadyPaid = (float) $invoice->payments()->sum('amount');
                $newPaidTotal = $alreadyPaid + $amount;

                if ($newPaidTotal > (float) $invoice->total) {
                    $validator->errors()->add(
                        'amount',
                        'Il pagamento supera il totale residuo della fattura.'
                    );
                }
            },
        ];
    }
}