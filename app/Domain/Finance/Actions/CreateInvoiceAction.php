<?php

namespace App\Domain\Finance\Actions;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class CreateInvoiceAction
{
    public function execute(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $data['created_by'] = auth()->id();
            $data['total'] = (float) $data['subtotal'] + (float) $data['tax_amount'];
            $data['paid_total'] = $data['paid_total'] ?? 0;

            $invoice = Invoice::create($data);

            foreach ($data['items'] ?? [] as $line) {
                $qty   = (float) $line['quantity'];
                $price = (float) $line['unit_price'];
                $invoice->items()->create([
                    'billable_type' => null,
                    'billable_id'   => null,
                    'description'   => $line['description'],
                    'quantity'      => $qty,
                    'unit_price'    => $price,
                    'total'         => $qty * $price,
                ]);
            }

            return $invoice;
        });
    }
}
