<?php

namespace App\Domain\Finance\Services;

use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Support\Collection;

class CreditNoteSuggestions
{
    private const REFERENCE = '(?:(?:FPA|FPR)\s*)?\d+\/\d{2,4}';

    public function references(CreditNote $note, Collection $invoices): Collection
    {
        $references = collect($note->import_metadata['credit_references'] ?? [])
            ->filter(fn ($reference) => is_array($reference) && is_string($reference['number'] ?? null));
        $amounts = $this->lineAmounts($note);
        $references = $references->unique(fn ($reference) => $this->normalize($reference['number']).'|'.($reference['date'] ?? ''));
        $available = CashAmount::cents($note->unallocated_total);

        return $references->map(function ($reference) use ($note, $invoices, $amounts, $available) {
            $number = $this->normalize($reference['number']);
            $candidates = $invoices->filter(fn ($invoice) => $this->matches($invoice, $reference))->values();
            $cents = $amounts[$number] ?? null;
            $candidate = $candidates->count() === 1 ? $candidates->first() : null;
            $linked = $candidate && $note->allocations->contains('invoice_id', $candidate->id);
            $invoiceAvailable = $candidate ? CashAmount::cents($candidate->payable_total ?? $candidate->total)
                - CashAmount::cents($candidate->credit_allocations_sum_amount ?? 0) : 0;
            /** A reference without a year or date cannot uniquely identify a document across fiscal years. */
            $dated = filled($reference['date'] ?? '') || preg_match('/\/(\d{2}|\d{4})$/', $number);

            return $reference + ['candidates' => $candidates, 'suggested_amount' => $cents !== null ? CashAmount::decimal($cents) : null,
                'can_prefill' => $dated && $candidate && ! $linked && $cents > 0 && $cents <= $available && $cents <= $invoiceAvailable,
                'already_linked' => (bool) $linked];
        })->values();
    }

    private function matches(Invoice $invoice, array $reference): bool
    {
        $number = $this->normalize($reference['number']);
        $candidate = $this->normalize($invoice->display_number);
        if (! preg_match('/^(FPA|FPR)/', $number)) $candidate = preg_replace('/^(FPA|FPR)/', '', $candidate);
        if ($candidate !== $number || (filled($reference['date'] ?? '') && $invoice->issue_date->format('Y-m-d') !== $reference['date'])) return false;
        if (preg_match('/\/(\d{2}|\d{4})$/', $number, $match)) {
            return $invoice->issue_date->format(strlen($match[1]) === 2 ? 'y' : 'Y') === $match[1];
        }

        return true;
    }

    private function lineAmounts(CreditNote $note): array
    {
        $items = $note->import_metadata['items'] ?? [];
        if (! is_array($items) || $items === []) return [];
        $amounts = [];
        $baseTotal = 0;
        $taxTotal = 0;
        $split = CashAmount::cents($note->payable_total) !== CashAmount::cents($note->total);
        foreach ($items as $item) {
            /** Only an entire, unambiguous line can assign its amount to a referenced invoice. */
            if (! is_array($item) || ! preg_match('/^\s*storno\s+fattura\s+(?:n\.?\s*)?('.self::REFERENCE.')\s*$/iu', $item['description'] ?? '', $match)
                || ! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', (string) ($item['total'] ?? ''))
                || ! preg_match('/^\d{1,3}(?:\.\d{1,2})?$/', (string) ($item['vat_rate'] ?? ''))) return [];
            $base = CashAmount::cents($item['total']);
            $rate = CashAmount::cents($item['vat_rate']);
            if ($base <= 0 || $rate > 10000) return [];
            $tax = intdiv($base * $rate + 5000, 10000);
            $baseTotal += $base;
            $taxTotal += $tax;
            $number = $this->normalize($match[1]);
            $amounts[$number] = ($amounts[$number] ?? 0) + $base + ($split ? 0 : $tax);
        }
        /** Fees, rounding differences or mixed split payment require manual review instead of guessed allocations. */
        if ($baseTotal !== CashAmount::cents($note->subtotal) || $taxTotal !== CashAmount::cents($note->tax_amount)
            || $baseTotal + $taxTotal !== CashAmount::cents($note->total)
            || array_sum($amounts) !== CashAmount::cents($note->payable_total)) return [];

        return $amounts;
    }

    private function normalize(string $value): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', '', $value));
    }
}
