<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Services\InvoiceAmountsCalculator;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class CreateInvoiceAction
{
    public function __construct(
        private readonly InvoiceAmountsCalculator $amounts,
    ) {}

    public function execute(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $data['created_by'] = auth()->id();
            $data['total'] = (float) $data['subtotal'] + (float) $data['tax_amount'];
            $data['paid_total'] = $data['paid_total'] ?? 0;

            $invoice = Invoice::create($data);

            foreach ($data['items'] ?? [] as $line) {
                $invoice->items()->create(array_merge(
                    $this->amounts->lineAttributes($line),
                    [
                        'billable_type' => null,
                        'billable_id' => null,
                    ],
                ));
            }

            $allLinesHaveVat = collect($data['items'] ?? [])
                ->isNotEmpty()
                && collect($data['items'])->every(
                    fn (array $line): bool => filled($line['vat_rate'] ?? null)
                );

            if ($allLinesHaveVat) {
                $this->amounts->recalculate($invoice);
            }

            return $invoice;
        });
    }
}
