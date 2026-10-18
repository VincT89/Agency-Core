<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Services\CashAmount;
use App\Domain\Finance\Services\InvoicePartyIdentity;
use App\Models\CreditNote;
use App\Models\CreditNoteAllocation;
use App\Models\Invoice;
use App\Services\AuditLogService;
use App\Services\InvoicePaymentSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AllocateCreditNote
{
    public function execute(CreditNote $creditNote, int $invoiceId, string $amount): CreditNoteAllocation
    {
        Gate::authorize('allocate', $creditNote);

        return DB::transaction(function () use ($creditNote, $invoiceId, $amount) {
            $note = CreditNote::lockForUpdate()->findOrFail($creditNote->id);
            $invoice = Invoice::lockForUpdate()->findOrFail($invoiceId);
            Gate::authorize('view', $invoice);
            if ($invoice->client_id !== $note->client_id || $invoice->currency !== $note->currency
                || in_array($invoice->status, ['draft', 'cancelled'], true)) {
                throw ValidationException::withMessages(['invoice_id' => 'Scegli una fattura emessa dello stesso cliente e nella stessa valuta.']);
            }
            if (! app(InvoicePartyIdentity::class)->compatible($note, $invoice)) {
                throw ValidationException::withMessages(['invoice_id' => 'I dati fiscali del cliente o dell’emittente non corrispondono oppure sono incompleti. Verifica IdPaese, IdCodice e gli eventuali codici fiscali nei documenti originali.']);
            }
            $cents = CashAmount::cents($amount);
            $existing = $note->allocations()->where('invoice_id', $invoice->id)->first();
            if ($existing) {
                if (CashAmount::cents($existing->amount) !== $cents) {
                    throw ValidationException::withMessages(['amount' => 'La fattura è già collegata con un altro importo. Rimuovi il collegamento prima di correggerlo.']);
                }

                return $existing;
            }
            $noteAvailable = CashAmount::cents($note->payable_total) - CashAmount::cents($note->allocations()->sum('amount'));
            $invoiceAvailable = CashAmount::cents($invoice->payable_total ?? $invoice->total) - CashAmount::cents($invoice->creditAllocations()->sum('amount'));
            if ($cents <= 0 || $cents > $noteAvailable || $cents > $invoiceAvailable) {
                throw ValidationException::withMessages(['amount' => 'L’importo supera il credito disponibile o la parte della fattura ancora stornabile.']);
            }
            $allocation = $note->allocations()->create(['invoice_id' => $invoice->id, 'amount' => CashAmount::decimal($cents), 'created_by' => auth()->id()]);
            $this->refreshInvoice($invoice);
            app(AuditLogService::class)->log('credit_linked', $invoice, null, ['credit_note_id' => $note->id, 'amount' => $allocation->amount], 'Collegata nota di credito '.$note->number);

            return $allocation;
        });
    }

    public function remove(CreditNoteAllocation $allocation): void
    {
        Gate::authorize('allocate', $allocation->creditNote);
        DB::transaction(function () use ($allocation) {
            $note = CreditNote::lockForUpdate()->findOrFail($allocation->credit_note_id);
            $invoice = Invoice::lockForUpdate()->findOrFail($allocation->invoice_id);
            $current = CreditNoteAllocation::lockForUpdate()->find($allocation->id);
            if (! $current) {
                return;
            }
            $current->delete();
            $this->refreshInvoice($invoice);
            app(AuditLogService::class)->log('credit_unlinked', $invoice, ['credit_note_id' => $note->id, 'amount' => $current->amount], null, 'Rimosso collegamento alla nota di credito '.$note->number);
        });
    }

    private function refreshInvoice(Invoice $invoice): void
    {
        /** Applying a credit never fabricates a payment or rewrites the original invoice amounts. */
        $invoice->credited_total = $invoice->creditAllocations()->sum('amount');
        app(InvoicePaymentSyncService::class)->syncStatus($invoice);
    }
}
