<?php

namespace App\Services;

use App\Domain\Finance\Services\CashAmount;

class ReportInvoiceAmounts
{
    public static function outstanding(float $netTurnover, float $collected): float
    {
        /** The report uses invoice taxable amounts and receipts; payments from other periods can produce negative balances. */
        $grossCents = (int) round(CashAmount::cents($netTurnover) * 122 / 100);

        return ($grossCents - CashAmount::cents($collected)) / 100;
    }
}
