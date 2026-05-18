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
            $invoice = Invoice::lockForUpdate()->findOrFail($data['invoice_id']);

            $data['client_id']  = $invoice->client_id;
            $data['project_id'] = $invoice->project_id;
            $data['created_by'] = auth()->id();
            
            $payment = Payment::create($data);

            // Ricalcola lo stato e il totale pagato usando il servizio dedicato
            app(\App\Services\InvoicePaymentSyncService::class)->sync($invoice->fresh());

            event(new \App\Domain\Finance\Events\PaymentRecorded($payment));

            return $payment;
        });
    }
}
