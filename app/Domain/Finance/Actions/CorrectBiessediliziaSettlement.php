<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Services\CashAmount;
use App\Domain\Finance\Services\InvoicePartyIdentity;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AuditLogService;
use App\Services\InvoicePaymentSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CorrectBiessediliziaSettlement
{
    public function execute(bool $apply = false): array
    {
        return DB::transaction(function () use ($apply) {
            /** Match the allocation service lock order and stop if the verified records have changed. */
            $note = CreditNote::lockForUpdate()->findOrFail(1);
            $invoice = Invoice::lockForUpdate()->findOrFail(153);
            Gate::authorize('allocate', $note);
            Gate::authorize('recordPayment', $invoice);
            $payment = Payment::lockForUpdate()->find(18);
            $allocations = $note->allocations()->lockForUpdate()->get();
            $linked = $allocations->where('invoice_id', $invoice->id);
            $normalize = fn ($value) => preg_replace('/\s+/u', '', mb_strtoupper((string) $value));
            $clientMatches = collect([$invoice->client?->name, $invoice->client?->company_name])
                ->contains(fn ($name) => $normalize($name) === 'BIESSEDILIZIAS.R.L.');
            $this->ensure($clientMatches && $invoice->client_id === $note->client_id
                && $invoice->currency === 'EUR' && $note->currency === 'EUR'
                && $normalize($invoice->display_number) === 'FPR6/26' && $normalize($note->number) === 'FPR17/26'
                && $invoice->issue_date->format('Y-m-d') === '2026-01-20' && $note->issue_date->format('Y-m-d') === '2026-02-18'
                && CashAmount::cents($invoice->subtotal) === 90000 && CashAmount::cents($invoice->total) === 109800
                && CashAmount::cents($invoice->payable_total ?? $invoice->total) === 109800
                && CashAmount::cents($note->total) === 585600 && CashAmount::cents($note->payable_total) === 585600
                && app(InvoicePartyIdentity::class)->compatible($note, $invoice), 'I documenti non corrispondono ai dati verificati: nessuna modifica eseguita.');
            $alreadyCorrected = ! $payment && $linked->count() === 1 && CashAmount::cents($linked->first()->amount) === 109800
                && ! $invoice->payments()->exists() && CashAmount::cents($invoice->paid_total) === 0
                && CashAmount::cents($invoice->credited_total) === 109800 && $invoice->status === 'credited';
            if ($alreadyCorrected) return ['status' => 'already_corrected', 'payment_id' => 18, 'invoice_id' => 153, 'credit_note_id' => 1];
            $this->ensure($payment && $payment->invoice_id === $invoice->id && $payment->client_id === $invoice->client_id
                && CashAmount::cents($payment->amount) === 109800 && $payment->method === 'bank_transfer'
                && preg_match('/^SALDO-SCADENZA-[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/i', (string) $payment->reference)
                && $payment->notes === 'Saldo registrato in blocco: bonifico, data coincidente con la scadenza.'
                && $invoice->due_date && $payment->payment_date->isSameDay($invoice->due_date)
                && $invoice->payments()->count() === 1 && $invoice->creditAllocations()->count() === 0
                && $allocations->isEmpty() && $invoice->status === 'paid'
                && CashAmount::cents($invoice->paid_total) === 109800 && CashAmount::cents($invoice->credited_total) === 0,
                'Il pagamento o le allocazioni sono diversi dallo stato verificato: nessuna modifica eseguita.');
            $result = ['status' => $apply ? 'corrected' : 'preview', 'payment_id' => 18, 'invoice_id' => 153,
                'credit_note_id' => 1, 'payment_reference' => $payment->reference, 'amount' => '1098.00', 'remaining_credit' => '4758.00'];
            if (! $apply) return $result;
            $before = ['payment' => $payment->getAttributes(), 'invoice' => $invoice->only(['paid_total', 'credited_total', 'status'])];
            /** The original payment is artificial; preserve its exact values in the audit before deleting only this row. */
            app(AllocateCreditNote::class)->execute($note, $invoice->id, '1098.00');
            $payment->delete();
            app(InvoicePaymentSyncService::class)->sync($invoice->fresh());
            app(AuditLogService::class)->log('artificial_settlement_corrected', $invoice, $before,
                $invoice->fresh()->only(['paid_total', 'credited_total', 'status']), 'Corretto il solo saldo artificiale 18 e collegati 1098,00 euro della nota 1 alla fattura 153.');
            return $result;
        });
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) throw ValidationException::withMessages(['correction' => $message]);
    }
}
