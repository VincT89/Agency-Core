<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('payment'));
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $payment = $this->route('payment');
                $amount = (float) $this->input('amount', 0);

                if (! $payment) {
                    return;
                }

                $invoice = $payment->invoice;

                if (! $invoice) {
                    return;
                }

                $alreadyPaidExcludingCurrent = (float) $invoice->payments()
                    ->where('id', '!=', $payment->id)
                    ->sum('amount');

                $newPaidTotal = $alreadyPaidExcludingCurrent + $amount;

                if ($newPaidTotal > $invoice->receivable_total && $amount > (float) $payment->amount) {
                    $validator->errors()->add(
                        'amount',
                        'Il pagamento supera il totale residuo della fattura.'
                    );
                }
            },
        ];
    }
}
