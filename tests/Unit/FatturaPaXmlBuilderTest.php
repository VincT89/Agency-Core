<?php

namespace Tests\Unit;

use App\Domain\Finance\Services\FatturaPaXmlBuilder;
use App\Exceptions\Finance\ElectronicInvoiceXmlException;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class FatturaPaXmlBuilderTest extends TestCase
{
    public function test_it_builds_the_supported_fpr12_document_with_aruba_as_transmitter(): void
    {
        $xml = app(FatturaPaXmlBuilder::class)->build($this->snapshot());
        $document = new DOMDocument;
        $document->loadXML($xml->content);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace(
            'p',
            'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2'
        );

        $this->assertMatchesRegularExpression(
            '/^IT01879020517_[A-F0-9]{10}\.xml$/',
            $xml->filename,
        );
        $this->assertSame(64, strlen($xml->hash));
        $this->assertSame(
            '01879020517',
            $xpath->evaluate('string(/p:FatturaElettronica/FatturaElettronicaHeader/DatiTrasmissione/IdTrasmittente/IdCodice)')
        );
        $this->assertSame(
            'ABC1234',
            $xpath->evaluate('string(/p:FatturaElettronica/FatturaElettronicaHeader/DatiTrasmissione/CodiceDestinatario)')
        );
        $this->assertSame(
            'FE-2026-0042',
            $xpath->evaluate('string(/p:FatturaElettronica/FatturaElettronicaBody/DatiGenerali/DatiGeneraliDocumento/Numero)')
        );
        $this->assertSame(
            'Servizio & consulenza <mensile>',
            $xpath->evaluate('string(/p:FatturaElettronica/FatturaElettronicaBody/DatiBeniServizi/DettaglioLinee/Descrizione)')
        );
        $this->assertSame(
            'MP05',
            $xpath->evaluate('string(/p:FatturaElettronica/FatturaElettronicaBody/DatiPagamento/DettaglioPagamento/ModalitaPagamento)')
        );
        $this->assertSame(
            'IT60X0542811101000000123456',
            $xpath->evaluate('string(/p:FatturaElettronica/FatturaElettronicaBody/DatiPagamento/DettaglioPagamento/IBAN)')
        );
    }

    public function test_it_blocks_public_administration_documents_until_they_are_supported(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['customer']['recipient_code'] = 'ABC123';

        $this->expectException(ElectronicInvoiceXmlException::class);

        app(FatturaPaXmlBuilder::class)->build($snapshot);
    }

    private function snapshot(): array
    {
        return [
            'schema_version' => 2,
            'issuer' => [
                'legal_name' => 'Agenzia Demo Srl',
                'vat_country_code' => 'IT',
                'vat_number' => '01234567897',
                'tax_code' => '01234567897',
                'fiscal_regime' => 'RF01',
                'address' => 'Via Verdi 1',
                'postal_code' => '80100',
                'city' => 'Napoli',
                'province' => 'NA',
                'country_code' => 'IT',
                'email' => 'amministrazione@example.test',
                'pec' => 'agenzia@pec.example.test',
                'iban' => 'IT60X0542811101000000123456',
            ],
            'customer' => [
                'legal_name' => 'Cliente Demo Srl',
                'vat_country_code' => 'IT',
                'vat_number' => '12345678903',
                'tax_code' => '12345678903',
                'address' => 'Via Roma 10',
                'postal_code' => '20100',
                'city' => 'Milano',
                'province' => 'MI',
                'country_code' => 'IT',
                'recipient_code' => 'ABC1234',
                'pec' => null,
            ],
            'document' => [
                'type' => 'TD01',
                'fiscal_number' => 'FE-2026-0042',
                'sequence_number' => 42,
                'issue_date' => '2026-07-31',
                'due_date' => '2026-08-30',
                'currency' => 'EUR',
                'subtotal' => '100.00',
                'tax_amount' => '22.00',
                'total' => '122.00',
            ],
            'payment' => [
                'terms' => 'TP02',
                'method' => 'MP05',
                'due_date' => '2026-08-30',
                'iban' => 'IT60X0542811101000000123456',
            ],
            'lines' => [[
                'line_number' => 1,
                'description' => 'Servizio & consulenza <mensile>',
                'quantity' => '1.00',
                'unit_of_measure' => 'NR',
                'unit_price' => '100.00',
                'taxable_total' => '100.00',
                'vat_rate' => '22.00',
                'vat_nature' => null,
                'vat_reference' => null,
                'tax_amount' => '22.00',
                'total_with_tax' => '122.00',
            ]],
        ];
    }
}
