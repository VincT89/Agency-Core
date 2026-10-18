<?php

namespace App\Domain\Finance\Actions;

use App\Models\Invoice;
use App\Services\InvoicePaymentSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ChangeInvoiceDueDate
{
    public function execute(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            Gate::authorize('changeDueDate', $invoice);
            if (! hash_equals(ChangeInvoiceStatus::version($invoice), $data['version'])) {
                throw ValidationException::withMessages(['version' => 'La fattura è cambiata mentre la modificavi. Riapri il modulo e verifica la scadenza aggiornata.']);
            }
            $validated = Validator::make($data, ['due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$invoice->issue_date->format('Y-m-d')]])->validate();
            if ($invoice->due_date?->format('Y-m-d') === $validated['due_date']) return $invoice;

            /** Only the operational deadline changes; original XML, fiscal snapshot and payment data remain intact. */
            $invoice->due_date = $validated['due_date'];
            app(InvoicePaymentSyncService::class)->syncStatus($invoice);

            return $invoice;
        });
    }
}
