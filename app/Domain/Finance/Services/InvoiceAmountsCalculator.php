<?php

namespace App\Domain\Finance\Services;

use App\Models\Invoice;

class InvoiceAmountsCalculator
{
    /**
     * @return array{total: float, tax_amount: float, total_with_tax: float}
     */
    public function line(float $quantity, float $unitPrice, ?float $vatRate): array
    {
        $total = round($quantity * $unitPrice, 2, PHP_ROUND_HALF_UP);
        $taxAmount = $vatRate === null
            ? 0.0
            : round($total * $vatRate / 100, 2, PHP_ROUND_HALF_UP);

        return [
            'total' => $total,
            'tax_amount' => $taxAmount,
            'total_with_tax' => round($total + $taxAmount, 2, PHP_ROUND_HALF_UP),
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    public function lineAttributes(array $line): array
    {
        $quantity = (float) $line['quantity'];
        $unitPrice = (float) $line['unit_price'];
        $vatRate = filled($line['vat_rate'] ?? null)
            ? (float) $line['vat_rate']
            : null;

        return array_merge([
            'description' => $line['description'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_of_measure' => strtoupper((string) ($line['unit_of_measure'] ?? 'NR')),
            'vat_rate' => $vatRate,
            'vat_nature' => $vatRate === 0.0
                ? strtoupper((string) ($line['vat_nature'] ?? ''))
                : null,
            'vat_reference' => $vatRate === 0.0
                ? ($line['vat_reference'] ?? null)
                : null,
        ], $this->line($quantity, $unitPrice, $vatRate));
    }

    public function recalculate(Invoice $invoice): void
    {
        $invoice->load('items');

        $subtotal = round((float) $invoice->items->sum('total'), 2, PHP_ROUND_HALF_UP);
        $taxAmount = round((float) $invoice->items->sum('tax_amount'), 2, PHP_ROUND_HALF_UP);

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => round($subtotal + $taxAmount, 2, PHP_ROUND_HALF_UP),
        ]);
    }
}
