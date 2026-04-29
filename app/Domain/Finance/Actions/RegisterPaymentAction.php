<?php

namespace App\Domain\Finance\Actions;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class RegisterPaymentAction
{
    public function execute(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::findOrFail($data['invoice_id']);

            $data['client_id']  = $invoice->client_id;
            $data['project_id'] = $invoice->project_id;
            $data['created_by'] = auth()->id();
            
            $payment = Payment::create($data);

            // Blocca la fattura per sincronizzare il totale pagato senza conflitti
            $invoice = Invoice::lockForUpdate()->find($invoice->id);
            $invoice->paid_total += $payment->amount;

            // Aggiorna lo stato della fattura in base al saldo residuo
            if ($invoice->paid_total >= $invoice->total) {
                $invoice->status = 'paid';
            } elseif ($invoice->status === 'issued' && $invoice->paid_total > 0) {
                $invoice->status = 'partially_paid';
            }
            $invoice->save();

            event(new \App\Domain\Finance\Events\PaymentRecorded($payment));

            return $payment;
        });
    }
}
