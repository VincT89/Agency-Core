<?php

namespace App\Services;

use App\Models\Invoice;

class InvoicePaymentSyncService
{
    public function sync(Invoice $invoice): void
    {
        $invoice->loadMissing('payments');

        $paidTotal = (float) $invoice->payments->sum('amount');
        $invoiceTotal = (float) $invoice->total;

        $invoice->paid_total = $paidTotal;

        if ($invoice->status !== 'cancelled') {
            if ($paidTotal >= $invoiceTotal && $invoiceTotal > 0) {
                $invoice->status = 'paid';
            } elseif ($paidTotal > 0 && $paidTotal < $invoiceTotal) {
                $invoice->status = 'partially_paid';
            } elseif ($paidTotal <= 0) {
                if ($invoice->status !== 'draft') {
                    if ($invoice->due_date && $invoice->due_date->isPast()) {
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