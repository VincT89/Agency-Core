<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\DTOs\InvoiceFiscalReadiness;
use App\Domain\Finance\Support\ItalianTaxIdentifier;
use App\Enums\Finance\VatNature;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;

class InvoiceFiscalReadinessService
{
    public function __construct(
        private readonly InvoiceAmountsCalculator $amounts,
    ) {}

    public function check(
        Invoice $invoice,
        ?BillingProfile $profile = null,
    ): InvoiceFiscalReadiness {
        $invoice->loadMissing(['client', 'items']);
        $profile ??= BillingProfile::current();

        $issues = [];

        $this->checkProfile($profile, $issues);
        $this->checkClient($invoice->client, $issues);
        $this->checkInvoice($invoice, $issues);

        return new InvoiceFiscalReadiness(array_values(array_unique($issues)));
    }

    /**
     * @param  array<int, string>  $issues
     */
    private function checkProfile(?BillingProfile $profile, array &$issues): void
    {
        if ($profile === null) {
            $issues[] = 'Configura i dati fiscali dell’agenzia.';

            return;
        }

        $required = [
            'legal_name' => 'denominazione dell’agenzia',
            'vat_country_code' => 'Stato della partita IVA dell’agenzia',
            'vat_number' => 'partita IVA dell’agenzia',
            'fiscal_regime' => 'regime fiscale dell’agenzia',
            'address' => 'indirizzo dell’agenzia',
            'postal_code' => 'CAP dell’agenzia',
            'city' => 'comune dell’agenzia',
            'country_code' => 'Stato della sede dell’agenzia',
            'invoice_series' => 'serie di numerazione',
        ];

        foreach ($required as $field => $label) {
            if (blank($profile->{$field})) {
                $issues[] = "Completa il campo {$label}.";
            }
        }

        if (! $this->isCountryCode($profile->vat_country_code)) {
            $issues[] = 'Il codice Stato della partita IVA dell’agenzia deve avere due lettere.';
        }

        if (! $this->isCountryCode($profile->country_code)) {
            $issues[] = 'Il codice Stato della sede dell’agenzia deve avere due lettere.';
        }

        if (! preg_match('/^RF\d{2}$/', strtoupper((string) $profile->fiscal_regime))) {
            $issues[] = 'Il regime fiscale dell’agenzia deve essere nel formato RF seguito da due cifre.';
        }

        if (
            strtoupper((string) $profile->country_code) === 'IT'
            && ! preg_match('/^[A-Z]{2}$/', strtoupper((string) $profile->province))
        ) {
            $issues[] = 'Indica la sigla di due lettere della provincia dell’agenzia.';
        }

        if (
            strtoupper((string) $profile->vat_country_code) === 'IT'
            && ! ItalianTaxIdentifier::isValidVatNumber($profile->vat_number)
        ) {
            $issues[] = 'La partita IVA italiana dell’agenzia non è formalmente valida.';
        }

        if (
            filled($profile->tax_code)
            && strtoupper((string) $profile->country_code) === 'IT'
            && ! ItalianTaxIdentifier::isValidTaxCode($profile->tax_code)
        ) {
            $issues[] = 'Il codice fiscale dell’agenzia non è formalmente valido.';
        }
    }

    /**
     * @param  array<int, string>  $issues
     */
    private function checkClient(?Client $client, array &$issues): void
    {
        if ($client === null) {
            $issues[] = 'Seleziona un cliente valido.';

            return;
        }

        if (blank($client->company_name) && blank($client->name)) {
            $issues[] = 'Completa la denominazione del cliente.';
        }

        if (! $this->isCountryCode($client->country_code)) {
            $issues[] = 'Completa il codice Stato di due lettere nei dati del cliente.';
        }

        foreach ([
            'address' => 'indirizzo',
            'postal_code' => 'CAP',
            'city' => 'comune',
        ] as $field => $label) {
            if (blank($client->{$field})) {
                $issues[] = "Completa {$label} nei dati del cliente.";
            }
        }

        $countryCode = strtoupper((string) $client->country_code);

        if ($countryCode === 'IT') {
            if (! preg_match('/^[A-Z]{2}$/', strtoupper((string) $client->province))) {
                $issues[] = 'Completa la provincia del cliente con una sigla di due lettere.';
            }

            if (blank($client->vat_number) && blank($client->tax_code)) {
                $issues[] = 'Inserisci la partita IVA o il codice fiscale del cliente.';
            }

            if (
                filled($client->vat_number)
                && ! ItalianTaxIdentifier::isValidVatNumber($client->vat_number)
            ) {
                $issues[] = 'La partita IVA italiana del cliente non è formalmente valida.';
            }

            if (
                filled($client->tax_code)
                && ! ItalianTaxIdentifier::isValidTaxCode($client->tax_code)
            ) {
                $issues[] = 'Il codice fiscale del cliente non è formalmente valido.';
            }

            if (
                blank($client->sdi_code)
                && blank($client->pec)
            ) {
                $issues[] = 'Inserisci il codice destinatario oppure la PEC del cliente.';
            }

            if (
                filled($client->sdi_code)
                && ! preg_match('/^[A-Z0-9]{6,7}$/', strtoupper((string) $client->sdi_code))
            ) {
                $issues[] = 'Il codice destinatario del cliente deve contenere 6 o 7 caratteri.';
            }
        } elseif (blank($client->vat_number) && blank($client->tax_code)) {
            $issues[] = 'Inserisci l’identificativo fiscale del cliente estero.';
        }
    }

    /**
     * @param  array<int, string>  $issues
     */
    private function checkInvoice(Invoice $invoice, array &$issues): void
    {
        if ($invoice->fiscal_document_type !== 'TD01') {
            $issues[] = 'Per ora è supportata soltanto la fattura ordinaria TD01.';
        }

        if ($invoice->issue_date === null) {
            $issues[] = 'Indica la data della fattura.';
        }

        if ($invoice->items->isEmpty()) {
            $issues[] = 'Aggiungi almeno una voce alla fattura.';

            return;
        }

        foreach ($invoice->items->values() as $index => $item) {
            $this->checkLine($item, $index + 1, $issues);
        }

        $subtotal = round((float) $invoice->items->sum('total'), 2, PHP_ROUND_HALF_UP);
        $taxAmount = round((float) $invoice->items->sum('tax_amount'), 2, PHP_ROUND_HALF_UP);
        $total = round($subtotal + $taxAmount, 2, PHP_ROUND_HALF_UP);

        if (! $this->sameAmount((float) $invoice->subtotal, $subtotal)) {
            $issues[] = 'L’imponibile non coincide con la somma delle voci: salva nuovamente la fattura.';
        }

        if (! $this->sameAmount((float) $invoice->tax_amount, $taxAmount)) {
            $issues[] = 'L’IVA non coincide con la somma delle voci: salva nuovamente la fattura.';
        }

        if (! $this->sameAmount((float) $invoice->total, $total)) {
            $issues[] = 'Il totale non coincide con imponibile e IVA: salva nuovamente la fattura.';
        }
    }

    /**
     * @param  array<int, string>  $issues
     */
    private function checkLine(InvoiceItem $item, int $position, array &$issues): void
    {
        $prefix = "Voce {$position}";

        if (blank($item->description)) {
            $issues[] = "{$prefix}: inserisci la descrizione.";
        }

        if ((float) $item->quantity <= 0) {
            $issues[] = "{$prefix}: la quantità deve essere maggiore di zero.";
        }

        if ($item->vat_rate === null) {
            $issues[] = "{$prefix}: seleziona l’aliquota IVA.";

            return;
        }

        $vatRate = (float) $item->vat_rate;

        if ($vatRate < 0 || $vatRate > 100) {
            $issues[] = "{$prefix}: l’aliquota IVA non è valida.";
        }

        if ($vatRate === 0.0) {
            if (VatNature::tryFrom((string) $item->vat_nature) === null) {
                $issues[] = "{$prefix}: seleziona la natura dell’operazione a IVA zero.";
            }

            if (blank($item->vat_reference)) {
                $issues[] = "{$prefix}: indica il riferimento normativo per l’IVA zero.";
            }
        } elseif (filled($item->vat_nature)) {
            $issues[] = "{$prefix}: la natura IVA va indicata solo con aliquota zero.";
        }

        $calculated = $this->amounts->line(
            (float) $item->quantity,
            (float) $item->unit_price,
            $vatRate,
        );

        if (! $this->sameAmount((float) $item->total, $calculated['total'])) {
            $issues[] = "{$prefix}: l’imponibile della voce deve essere ricalcolato.";
        }

        if (! $this->sameAmount((float) $item->tax_amount, $calculated['tax_amount'])) {
            $issues[] = "{$prefix}: l’IVA della voce deve essere ricalcolata.";
        }

        if (
            $item->total_with_tax === null
            || ! $this->sameAmount((float) $item->total_with_tax, $calculated['total_with_tax'])
        ) {
            $issues[] = "{$prefix}: il totale della voce deve essere ricalcolato.";
        }
    }

    private function isCountryCode(?string $value): bool
    {
        return preg_match('/^[A-Z]{2}$/', strtoupper((string) $value)) === 1;
    }

    private function sameAmount(float $left, float $right): bool
    {
        return abs($left - $right) < 0.005;
    }
}
