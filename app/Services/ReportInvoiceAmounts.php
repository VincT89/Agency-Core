<?php

namespace App\Services;

class ReportInvoiceAmounts
{
    public static function outstandingSql(): string
    {
        /** This report deliberately applies 22% even to split payment; invoice balances and payment validation retain their actual amounts. */
        $taxableCredits = 'COALESCE((SELECT SUM(credit_note_allocations.amount * 1.0 * credit_notes.subtotal / NULLIF(credit_notes.payable_total, 0))
            FROM credit_note_allocations
            INNER JOIN credit_notes ON credit_notes.id = credit_note_allocations.credit_note_id
            WHERE credit_note_allocations.invoice_id = invoices.id), 0)';
        $residual = 'ROUND((invoices.subtotal - '.$taxableCredits.') * 1.22, 2) - COALESCE(invoices.paid_total, 0)';

        return 'CASE WHEN ('.$residual.') > 0 THEN ('.$residual.') ELSE 0 END';
    }
}
