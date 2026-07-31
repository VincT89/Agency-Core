<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Services\InvoiceFiscalReadinessService;
use App\Domain\Finance\Services\InvoiceFiscalSnapshotBuilder;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Exceptions\Finance\InvoiceFiscalPreparationException;
use App\Models\BillingProfile;
use App\Models\Invoice;
use App\Models\InvoiceNumberSequence;
use Illuminate\Support\Facades\DB;

class PrepareElectronicInvoiceAction
{
    public function __construct(
        private readonly InvoiceFiscalReadinessService $readiness,
        private readonly InvoiceFiscalSnapshotBuilder $snapshotBuilder,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvoice->fiscal_status === InvoiceFiscalStatus::Ready) {
                return $lockedInvoice;
            }

            if ($lockedInvoice->fiscal_status !== InvoiceFiscalStatus::NotPrepared) {
                throw new InvoiceFiscalPreparationException([
                    'Questa fattura non può essere preparata perché il suo invio fiscale è già iniziato.',
                ]);
            }

            $profile = BillingProfile::query()
                ->where('profile_key', 'default')
                ->lockForUpdate()
                ->first();

            $lockedInvoice->load(['client', 'items']);
            $result = $this->readiness->check($lockedInvoice, $profile);

            if (! $result->isReady()) {
                throw new InvoiceFiscalPreparationException($result->issues);
            }

            if ($profile === null) {
                throw new InvoiceFiscalPreparationException([
                    'Configura i dati fiscali dell’agenzia.',
                ]);
            }

            if (blank($lockedInvoice->fiscal_number)) {
                [$fiscalNumber, $sequenceNumber] = $this->reserveNumber(
                    $profile,
                    (int) $lockedInvoice->issue_date->format('Y'),
                );

                $lockedInvoice->fiscal_number = $fiscalNumber;
                $lockedInvoice->fiscal_sequence_number = $sequenceNumber;
            }

            $lockedInvoice->fiscal_status = InvoiceFiscalStatus::Ready;
            $lockedInvoice->fiscal_locked_at = now();
            $lockedInvoice->fiscal_snapshot = $this->snapshotBuilder->build(
                $lockedInvoice,
                $profile,
            );
            $lockedInvoice->save();

            return $lockedInvoice->fresh(['client', 'items']);
        });
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function reserveNumber(BillingProfile $profile, int $year): array
    {
        $series = strtoupper($profile->invoice_series);
        $sequence = InvoiceNumberSequence::query()
            ->where('billing_profile_id', $profile->id)
            ->where('year', $year)
            ->where('series', $series)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            $sequence = InvoiceNumberSequence::create([
                'billing_profile_id' => $profile->id,
                'year' => $year,
                'series' => $series,
                'next_number' => $profile->initial_sequence,
            ]);
        }

        $reserved = $sequence->next_number;
        $sequence->increment('next_number');

        return [
            sprintf('%s-%d-%04d', $series, $year, $reserved),
            $reserved,
        ];
    }
}
