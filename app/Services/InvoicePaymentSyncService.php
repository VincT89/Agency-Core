<?php

namespace App\Services;

use App\Models\Invoice;

class InvoicePaymentSyncService
{
    public function sync(Invoice $invoice): void
    {
        $invoice->loadMissing('payments');

        $paidTotal = (float) $invoice->payments->sum('amount');
        $invoice->paid_total = $paidTotal;
        $this->syncStatus($invoice);
    }

    public function syncStatus(Invoice $invoice): void
    {
        $paidTotal = (float) $invoice->paid_total;
        $invoiceTotal = $invoice->receivable_total;

        if ($invoice->status !== 'cancelled') {
            if ($invoiceTotal <= 0 && (float) $invoice->credited_total > 0) {
                $invoice->status = 'credited';
            } elseif ($paidTotal >= $invoiceTotal && $invoiceTotal > 0) {
                $invoice->status = 'paid';
            } elseif ($paidTotal > 0 && $paidTotal < $invoiceTotal) {
                $invoice->status = 'partially_paid';
            } elseif ($paidTotal <= 0) {
                if ($invoice->status !== 'draft') {
                    if ($invoice->due_date && $invoice->due_date->lt(today())) {
                        $invoice->status = 'overdue';
                    } else {
                        $invoice->status = 'issued';
                    }
                }
            }
        }

        $invoice->save();
    }
}
