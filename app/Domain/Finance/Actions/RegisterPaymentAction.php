<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Events\PaymentRecorded;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoicePaymentSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use App\Domain\Finance\Services\CashAmount;

class RegisterPaymentAction
{
    public function execute(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::lockForUpdate()->findOrFail($data['invoice_id']);
            Gate::authorize('recordPayment', $invoice);
            if ($invoice->status === 'cancelled') {
                throw ValidationException::withMessages(['invoice_id' => 'La fattura è annullata nel gestionale. Riportala a Da incassare prima di registrare un pagamento.']);
            }
            /** Recheck under the invoice lock, including credits confirmed during payment entry. */
            $paid = CashAmount::cents($invoice->payments()->sum('amount'));
            if (CashAmount::cents($data['amount']) <= 0 || $paid + CashAmount::cents($data['amount']) > CashAmount::cents($invoice->receivable_total)) {
                throw ValidationException::withMessages(['amount' => 'Il pagamento supera il totale residuo della fattura.']);
            }

            $data['client_id'] = $invoice->client_id;
            $data['project_id'] = $invoice->project_id;
            $data['created_by'] = auth()->id();

            $payment = Payment::create($data);

            // Ricalcola lo stato e il totale pagato usando il servizio dedicato
            app(InvoicePaymentSyncService::class)->sync($invoice->fresh());

            event(new PaymentRecorded($payment));

            return $payment;
        });
    }
}
