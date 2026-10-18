<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Services\CashAmount;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeInvoiceStatus
{
    public const STATUSES = ['issued', 'partially_paid', 'paid', 'overdue', 'cancelled'];

    public static function version(Invoice $invoice): string
    {
        return hash('sha256', json_encode([$invoice->id, $invoice->client_id, $invoice->currency, $invoice->status, $invoice->total, $invoice->payable_total,
            $invoice->paid_total, $invoice->credited_total, $invoice->due_date?->format('Y-m-d')], JSON_THROW_ON_ERROR));
    }

    public function execute(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            Gate::authorize('changeStatus', $invoice);
            if (! in_array($data['status'], self::STATUSES, true)) {
                $this->invalid('Lo stato selezionato non è disponibile.');
            }
            /** The locked financial version prevents stale forms or double submits from adding a second receipt. */
            if (! hash_equals(self::version($invoice), $data['version'])) {
                $this->invalid('La fattura è cambiata mentre la modificavi. Verifica gli importi aggiornati prima di salvare di nuovo.');
            }
            $paid = CashAmount::cents($invoice->payments()->sum('amount'));
            if ($paid !== CashAmount::cents($invoice->paid_total)) {
                $this->invalid('Il totale incassato non corrisponde ai pagamenti registrati. Verifica prima i pagamenti della fattura.');
            }
            if ($invoice->status === 'credited') {
                $this->invalid('La fattura è interamente stornata. Verifica prima le note di credito collegate.');
            }
            $status = $data['status'];
            if (in_array($status, ['issued', 'overdue', 'cancelled'], true)) {
                if ($paid > 0) {
                    $this->invalid('Sono presenti pagamenti: correggili o rimuovili dalla sezione Pagamenti registrati prima di impostare questo stato.');
                }
                if ($status === 'cancelled' && $invoice->creditAllocations()->exists()) {
                    $this->invalid('Sono presenti note di credito collegate. Verifica e rimuovi i collegamenti prima di annullare la fattura nel gestionale.');
                }
                if ($status === 'overdue' && (! $invoice->due_date || ! $invoice->due_date->lt(today()))) {
                    $this->invalid('Puoi impostare Scaduta solo quando la data di scadenza è già trascorsa.');
                }
                $invoice->update(['status' => $status]);

                return $invoice;
            }
            if ($invoice->status === 'cancelled') {
                $this->invalid('Riporta prima la fattura a Da incassare, poi registra l’incasso.');
            }
            $residual = CashAmount::cents($invoice->residual);
            if ($status === 'paid' && $residual === 0 && $paid > 0) {
                $invoice->update(['status' => 'paid']);

                return $invoice;
            }
            $amount = $status === 'paid' ? $residual : CashAmount::cents($data['amount'] ?? 0);
            if ($amount <= 0 || ($status === 'partially_paid' && $amount >= $residual)) {
                throw ValidationException::withMessages(['amount' => 'L’incasso parziale deve essere maggiore di zero e inferiore al residuo. Per saldare la fattura scegli Incassata.']);
            }
            app(RegisterPaymentAction::class)->execute([
                'invoice_id' => $invoice->id, 'amount' => CashAmount::decimal($amount),
                'payment_date' => $data['payment_date'], 'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
            ]);

            return $invoice->fresh();
        });
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['status' => $message]);
    }
}
