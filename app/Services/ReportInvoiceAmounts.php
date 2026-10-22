<?php

namespace App\Services;

use App\Domain\Finance\Services\CashAmount;

class ReportInvoiceAmounts
{
    public static function outstanding(float $netTurnover, float $collected): float
    {
        /** The report uses its displayed net turnover and receipts; negative balances preserve credits and receipts from other periods. */
        $grossCents = (int) round(CashAmount::cents($netTurnover) * 122 / 100);

        return ($grossCents - CashAmount::cents($collected)) / 100;
    }
}
