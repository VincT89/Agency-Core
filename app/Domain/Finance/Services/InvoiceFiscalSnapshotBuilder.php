<?php

namespace App\Domain\Finance\Services;

use App\Models\BillingProfile;
use App\Models\Invoice;

class InvoiceFiscalSnapshotBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Invoice $invoice, BillingProfile $profile): array
    {
        $invoice->loadMissing(['client', 'items']);
        $client = $invoice->client;
        $clientCountry = strtoupper((string) $client->country_code);
        $recipientCode = filled($client->sdi_code)
            ? strtoupper((string) $client->sdi_code)
            : ($clientCountry === 'IT' ? '0000000' : 'XXXXXXX');

        return [
            'schema_version' => 2,
            'prepared_at' => now()->toIso8601String(),
            'issuer' => [
                'legal_name' => $profile->legal_name,
                'vat_country_code' => strtoupper($profile->vat_country_code),
                'vat_number' => $this->normalizeIdentifier($profile->vat_number, $profile->vat_country_code),
                'tax_code' => $this->normalizeIdentifier($profile->tax_code),
                'fiscal_regime' => strtoupper($profile->fiscal_regime),
                'address' => $profile->address,
                'postal_code' => $profile->postal_code,
                'city' => $profile->city,
                'province' => strtoupper((string) $profile->province),
                'country_code' => strtoupper($profile->country_code),
                'email' => $profile->email,
                'pec' => $profile->pec,
                'iban' => $this->normalizeIdentifier($profile->iban),
            ],
            'customer' => [
                'legal_name' => $client->company_name ?: $client->name,
                'vat_country_code' => $clientCountry,
                'vat_number' => $this->normalizeIdentifier($client->vat_number, $clientCountry),
                'tax_code' => $this->normalizeIdentifier($client->tax_code),
                'address' => $client->address,
                'postal_code' => $client->postal_code,
                'city' => $client->city,
                'province' => strtoupper((string) $client->province),
                'country_code' => $clientCountry,
                'recipient_code' => $recipientCode,
                'pec' => $client->pec,
                'billing_email' => $client->billing_email,
            ],
            'document' => [
                'type' => $invoice->fiscal_document_type,
                'fiscal_number' => $invoice->fiscal_number,
                'sequence_number' => $invoice->fiscal_sequence_number,
                'internal_reference' => $invoice->number,
                'issue_date' => $invoice->issue_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'currency' => strtoupper($invoice->currency),
                'subtotal' => $this->money($invoice->subtotal),
                'tax_amount' => $this->money($invoice->tax_amount),
                'total' => $this->money($invoice->total),
                'notes' => $invoice->notes,
            ],
            'payment' => [
                'terms' => 'TP02',
                'method' => strtoupper((string) $profile->default_payment_method),
                'due_date' => $invoice->due_date?->toDateString(),
                'iban' => $this->normalizeIdentifier($profile->iban),
            ],
            'lines' => $invoice->items
                ->values()
                ->map(fn ($item, $index) => [
                    'line_number' => $index + 1,
                    'description' => $item->description,
                    'quantity' => number_format((float) $item->quantity, 2, '.', ''),
                    'unit_of_measure' => $item->unit_of_measure,
                    'unit_price' => $this->money($item->unit_price),
                    'taxable_total' => $this->money($item->total),
                    'vat_rate' => number_format((float) $item->vat_rate, 2, '.', ''),
                    'vat_nature' => $item->vat_nature,
                    'vat_reference' => $item->vat_reference,
                    'tax_amount' => $this->money($item->tax_amount),
                    'total_with_tax' => $this->money($item->total_with_tax),
                ])
                ->all(),
        ];
    }

    private function normalizeIdentifier(?string $value, ?string $countryCode = null): ?string
    {
        if (blank($value)) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', $value) ?? '');
        $countryCode = strtoupper((string) $countryCode);

        return $countryCode !== '' && str_starts_with($normalized, $countryCode)
            ? substr($normalized, 2)
            : $normalized;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
