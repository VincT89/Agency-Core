<?php

namespace App\Domain\Finance\Actions;

use App\Enums\Finance\InvoiceFiscalStatus;
use App\Exceptions\Finance\InvoiceFiscalPreparationException;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class ReopenElectronicInvoiceDraftAction
{
    public function execute(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedInvoice->fiscal_status->allowsReopening()) {
                throw new InvoiceFiscalPreparationException([
                    'Puoi riaprire soltanto una fattura pronta, scartata o non elaborata dal servizio.',
                ]);
            }

            $lockedInvoice->update([
                'fiscal_status' => InvoiceFiscalStatus::NotPrepared,
                'fiscal_locked_at' => null,
                'fiscal_snapshot' => null,
            ]);

            return $lockedInvoice->fresh();
        });
    }
}
