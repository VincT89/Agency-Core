<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\DTOs\FatturaPaXmlDocument;
use App\Enums\Finance\FatturaPaPaymentMethod;
use App\Exceptions\Finance\ElectronicInvoiceXmlException;
use DOMDocument;
use DOMElement;

class FatturaPaXmlBuilder
{
    private const NAMESPACE = 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2';
    private const TRANSMITTER_COUNTRY = 'IT';
    private const TRANSMITTER_TAX_CODE = '01879020517';
    private const TRANSMITTER_PHONE = '05750505';
    private const TRANSMITTER_EMAIL = 'info@arubapec.it';

    public function __construct(
        private readonly FatturaPaXmlValidator $validator,
    ) {}

    public function build(array $snapshot): FatturaPaXmlDocument
    {
        $this->assertSupported($snapshot);

        $progressive = $this->progressive($snapshot);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $document->preserveWhiteSpace = false;

        $root = $document->createElementNS(self::NAMESPACE, 'p:FatturaElettronica');
        $root->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:ds',
            'http://www.w3.org/2000/09/xmldsig#'
        );
        $root->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $root->setAttribute('versione', 'FPR12');
        $document->appendChild($root);

        $this->appendHeader($document, $root, $snapshot, $progressive);
        $this->appendBody($document, $root, $snapshot);

        $xml = $document->saveXML();

        if (! is_string($xml) || $xml === '') {
            throw new ElectronicInvoiceXmlException([
                'Non è stato possibile generare il file XML della fattura.',
            ]);
        }

        $this->validator->validate($xml);

        return new FatturaPaXmlDocument(
            filename: self::TRANSMITTER_COUNTRY.self::TRANSMITTER_TAX_CODE."_{$progressive}.xml",
            progressive: $progressive,
            content: $xml,
            hash: hash('sha256', $xml),
        );
    }

    private function appendHeader(
        DOMDocument $document,
        DOMElement $root,
        array $snapshot,
        string $progressive,
    ): void {
        $header = $this->append($document, $root, 'FatturaElettronicaHeader');
        $transmission = $this->append($document, $header, 'DatiTrasmissione');
        $transmitter = $this->append($document, $transmission, 'IdTrasmittente');
        $this->append($document, $transmitter, 'IdPaese', self::TRANSMITTER_COUNTRY);
        $this->append($document, $transmitter, 'IdCodice', self::TRANSMITTER_TAX_CODE);
        $this->append($document, $transmission, 'ProgressivoInvio', $progressive);
        $this->append($document, $transmission, 'FormatoTrasmissione', 'FPR12');
        $this->append(
            $document,
            $transmission,
            'CodiceDestinatario',
            $snapshot['customer']['recipient_code'],
        );

        $contacts = $this->append($document, $transmission, 'ContattiTrasmittente');
        $this->append($document, $contacts, 'Telefono', self::TRANSMITTER_PHONE);
        $this->append($document, $contacts, 'Email', self::TRANSMITTER_EMAIL);

        if (
            $snapshot['customer']['recipient_code'] === '0000000'
            && filled($snapshot['customer']['pec'] ?? null)
        ) {
            $this->append($document, $transmission, 'PECDestinatario', $snapshot['customer']['pec']);
        }

        $this->appendIssuer($document, $header, $snapshot['issuer']);
        $this->appendCustomer($document, $header, $snapshot['customer']);
    }

    private function appendIssuer(DOMDocument $document, DOMElement $header, array $issuer): void
    {
        $seller = $this->append($document, $header, 'CedentePrestatore');
        $identity = $this->append($document, $seller, 'DatiAnagrafici');
        $vat = $this->append($document, $identity, 'IdFiscaleIVA');
        $this->append($document, $vat, 'IdPaese', $issuer['vat_country_code']);
        $this->append($document, $vat, 'IdCodice', $issuer['vat_number']);
        $this->appendOptional($document, $identity, 'CodiceFiscale', $issuer['tax_code'] ?? null);
        $registry = $this->append($document, $identity, 'Anagrafica');
        $this->append($document, $registry, 'Denominazione', $issuer['legal_name']);
        $this->append($document, $identity, 'RegimeFiscale', $issuer['fiscal_regime']);
        $this->appendAddress($document, $seller, $issuer);
    }

    private function appendCustomer(DOMDocument $document, DOMElement $header, array $customer): void
    {
        $buyer = $this->append($document, $header, 'CessionarioCommittente');
        $identity = $this->append($document, $buyer, 'DatiAnagrafici');

        if (filled($customer['vat_number'] ?? null)) {
            $vat = $this->append($document, $identity, 'IdFiscaleIVA');
            $this->append($document, $vat, 'IdPaese', $customer['vat_country_code']);
            $this->append($document, $vat, 'IdCodice', $customer['vat_number']);
        }

        $this->appendOptional($document, $identity, 'CodiceFiscale', $customer['tax_code'] ?? null);
        $registry = $this->append($document, $identity, 'Anagrafica');
        $this->append($document, $registry, 'Denominazione', $customer['legal_name']);
        $this->appendAddress($document, $buyer, $customer);
    }

    private function appendAddress(DOMDocument $document, DOMElement $parent, array $party): void
    {
        $address = $this->append($document, $parent, 'Sede');
        $this->append($document, $address, 'Indirizzo', $party['address']);
        $this->append(
            $document,
            $address,
            'CAP',
            $party['country_code'] === 'IT' ? $party['postal_code'] : '00000',
        );
        $this->append($document, $address, 'Comune', $party['city']);

        if ($party['country_code'] === 'IT') {
            $this->append($document, $address, 'Provincia', $party['province']);
        }

        $this->append($document, $address, 'Nazione', $party['country_code']);
    }

    private function appendBody(DOMDocument $document, DOMElement $root, array $snapshot): void
    {
        $body = $this->append($document, $root, 'FatturaElettronicaBody');
        $general = $this->append($document, $body, 'DatiGenerali');
        $documentData = $this->append($document, $general, 'DatiGeneraliDocumento');
        $this->append($document, $documentData, 'TipoDocumento', 'TD01');
        $this->append($document, $documentData, 'Divisa', $snapshot['document']['currency']);
        $this->append($document, $documentData, 'Data', $snapshot['document']['issue_date']);
        $this->append($document, $documentData, 'Numero', $snapshot['document']['fiscal_number']);
        $this->append($document, $documentData, 'ImportoTotaleDocumento', $snapshot['document']['total']);

        $goods = $this->append($document, $body, 'DatiBeniServizi');

        foreach ($snapshot['lines'] as $line) {
            $detail = $this->append($document, $goods, 'DettaglioLinee');
            $this->append($document, $detail, 'NumeroLinea', $line['line_number']);
            $this->append($document, $detail, 'Descrizione', $line['description']);
            $this->append($document, $detail, 'Quantita', $line['quantity']);
            $this->appendOptional($document, $detail, 'UnitaMisura', $line['unit_of_measure'] ?? null);
            $this->append($document, $detail, 'PrezzoUnitario', $line['unit_price']);
            $this->append($document, $detail, 'PrezzoTotale', $line['taxable_total']);
            $this->append($document, $detail, 'AliquotaIVA', $line['vat_rate']);

            if ((float) $line['vat_rate'] === 0.0) {
                $this->append($document, $detail, 'Natura', $line['vat_nature']);
            }
        }

        foreach ($this->vatSummaries($snapshot['lines']) as $summary) {
            $recap = $this->append($document, $goods, 'DatiRiepilogo');
            $this->append($document, $recap, 'AliquotaIVA', $summary['vat_rate']);

            if ((float) $summary['vat_rate'] === 0.0) {
                $this->append($document, $recap, 'Natura', $summary['vat_nature']);
            }

            $this->append($document, $recap, 'ImponibileImporto', $summary['taxable_total']);
            $this->append($document, $recap, 'Imposta', $summary['tax_amount']);

            if ((float) $summary['vat_rate'] > 0.0) {
                $this->append($document, $recap, 'EsigibilitaIVA', 'I');
            }

            $this->appendOptional(
                $document,
                $recap,
                'RiferimentoNormativo',
                $summary['vat_reference'],
            );
        }

        $this->appendPayment($document, $body, $snapshot);
    }

    private function appendPayment(DOMDocument $document, DOMElement $body, array $snapshot): void
    {
        $payment = $snapshot['payment'];
        $data = $this->append($document, $body, 'DatiPagamento');
        $this->append($document, $data, 'CondizioniPagamento', 'TP02');
        $detail = $this->append($document, $data, 'DettaglioPagamento');
        $this->append($document, $detail, 'ModalitaPagamento', $payment['method']);
        $this->append($document, $detail, 'DataScadenzaPagamento', $payment['due_date']);
        $this->append($document, $detail, 'ImportoPagamento', $snapshot['document']['total']);

        if (filled($payment['iban'] ?? null)) {
            $this->append($document, $detail, 'IBAN', $payment['iban']);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, string|null>>
     */
    private function vatSummaries(array $lines): array
    {
        $groups = [];

        foreach ($lines as $line) {
            $key = implode('|', [
                $line['vat_rate'],
                $line['vat_nature'] ?? '',
                $line['vat_reference'] ?? '',
            ]);

            $groups[$key] ??= [
                'vat_rate' => $line['vat_rate'],
                'vat_nature' => $line['vat_nature'] ?? null,
                'vat_reference' => $line['vat_reference'] ?? null,
                'taxable_total_raw' => 0.0,
                'tax_amount_raw' => 0.0,
            ];
            $groups[$key]['taxable_total_raw'] += (float) $line['taxable_total'];
            $groups[$key]['tax_amount_raw'] += (float) $line['tax_amount'];
        }

        return array_values(array_map(fn (array $group): array => [
            'vat_rate' => $group['vat_rate'],
            'vat_nature' => $group['vat_nature'],
            'vat_reference' => $group['vat_reference'],
            'taxable_total' => $this->money($group['taxable_total_raw']),
            'tax_amount' => $this->money($group['tax_amount_raw']),
        ], $groups));
    }

    private function assertSupported(array $snapshot): void
    {
        $issues = [];
        $document = $snapshot['document'] ?? [];
        $customer = $snapshot['customer'] ?? [];
        $payment = $snapshot['payment'] ?? [];

        if (($document['type'] ?? null) !== 'TD01') {
            $issues[] = 'È supportata soltanto la fattura ordinaria TD01.';
        }

        if (($document['currency'] ?? null) !== 'EUR') {
            $issues[] = 'La trasmissione elettronica è abilitata soltanto per fatture in euro.';
        }

        if (strlen((string) ($customer['recipient_code'] ?? '')) !== 7) {
            $issues[] = 'Le fatture verso la Pubblica Amministrazione non sono ancora supportate.';
        }

        if (FatturaPaPaymentMethod::tryFrom((string) ($payment['method'] ?? '')) === null) {
            $issues[] = 'Seleziona una modalità di pagamento valida nei dati fiscali.';
        }

        if (blank($payment['due_date'] ?? null)) {
            $issues[] = 'Indica la data di scadenza della fattura.';
        }

        if ($issues !== []) {
            throw new ElectronicInvoiceXmlException($issues);
        }
    }

    private function progressive(array $snapshot): string
    {
        $year = (int) substr((string) $snapshot['document']['issue_date'], 0, 4);
        $sequence = (int) $snapshot['document']['sequence_number'];
        $fiscalNumber = trim((string) ($snapshot['document']['fiscal_number'] ?? ''));

        if (
            $year < 2000
            || $year > 9999
            || $sequence < 1
            || $sequence > 999999
            || $fiscalNumber === ''
        ) {
            throw new ElectronicInvoiceXmlException([
                'Il progressivo della fattura non può essere trasformato nel formato FatturaPA.',
            ]);
        }

        return strtoupper(substr(hash('sha256', implode('|', [
            $snapshot['issuer']['vat_country_code'] ?? '',
            $snapshot['issuer']['vat_number'] ?? '',
            $fiscalNumber,
        ])), 0, 10));
    }

    private function append(
        DOMDocument $document,
        DOMElement $parent,
        string $name,
        string|int|float|null $value = null,
    ): DOMElement {
        $element = $document->createElement($name);

        if ($value !== null) {
            $element->appendChild($document->createTextNode((string) $value));
        }

        $parent->appendChild($element);

        return $element;
    }

    private function appendOptional(
        DOMDocument $document,
        DOMElement $parent,
        string $name,
        mixed $value,
    ): void {
        if (filled($value)) {
            $this->append($document, $parent, $name, (string) $value);
        }
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2, PHP_ROUND_HALF_UP), 2, '.', '');
    }
}
