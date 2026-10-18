<?php

namespace App\Domain\Finance\Services;

use App\Enums\Finance\InvoiceFiscalStatus;
use App\Models\BillingProfile;
use App\Models\CreditNote;
use App\Models\Invoice;

class InvoicePartyIdentity
{
    public function compatible(CreditNote $note, Invoice $invoice): bool
    {
        return $this->same($this->customer($note), $this->customer($invoice))
            && $this->same($this->issuer($note), $this->issuer($invoice));
    }

    public function customer(Invoice|CreditNote $document): array
    {
        /** Imported identities come from the original snapshot, never from a subsequently edited client. */
        if ($this->imported($document)) {
            return $this->identity($document->import_metadata['counterparty'] ?? []);
        }
        if (is_array($document->fiscal_snapshot['customer'] ?? null)) {
            return $this->identity($document->fiscal_snapshot['customer']);
        }

        return $this->identity($document->client?->only(['country_code', 'vat_number', 'tax_code']) ?? [], true);
    }

    private function issuer(Invoice|CreditNote $document): array
    {
        if ($this->imported($document)) {
            return $this->identity($document->import_metadata['agency'] ?? []);
        }
        if (is_array($document->fiscal_snapshot['issuer'] ?? null)) {
            return $this->identity($document->fiscal_snapshot['issuer']);
        }

        return $this->identity(BillingProfile::current()?->only(['vat_country_code', 'vat_number', 'tax_code']) ?? [], true);
    }

    private function imported(Invoice|CreditNote $document): bool
    {
        return $document instanceof CreditNote || $document->fiscal_status === InvoiceFiscalStatus::Imported;
    }

    private function identity(array $party, bool $editable = false): array
    {
        return ['country' => $this->normalize($party['vat_country_code'] ?? $party['country_code'] ?? ''),
            'vat' => $this->normalize($party['vat_number'] ?? ''), 'tax_code' => $this->normalize($party['tax_code'] ?? ''),
            'editable' => $editable];
    }

    private function same(array $left, array $right): bool
    {
        if (! preg_match('/^[A-Z]{2}$/', $left['country']) || $left['country'] !== $right['country']) {
            return false;
        }
        if ($left['tax_code'] !== '' && $right['tax_code'] !== '' && $left['tax_code'] !== $right['tax_code']) {
            return false;
        }
        if ($left['vat'] !== '' || $right['vat'] !== '') {
            if ($left['vat'] === '' || $right['vat'] === '') return false;
            /** An editable address-book value may include the country prefix; XML IdCodice stays literal. */
            return $left['vat'] === $right['vat']
                || ($left['editable'] && $left['vat'] === $left['country'].$right['vat'])
                || ($right['editable'] && $right['vat'] === $right['country'].$left['vat']);
        }

        return $left['tax_code'] !== '' && $left['tax_code'] === $right['tax_code'];
    }

    private function normalize(mixed $value): string
    {
        return is_string($value) ? mb_strtoupper(preg_replace('/\s+/u', '', $value)) : '';
    }
}
