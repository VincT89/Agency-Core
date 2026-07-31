<?php

namespace App\Domain\Finance\Services;

use App\Exceptions\Finance\ElectronicInvoiceXmlException;
use DOMDocument;
use DOMXPath;

class FatturaPaXmlValidator
{
    private const NAMESPACE = 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2';

    public function validate(string $xml): void
    {
        $issues = [];

        if (strlen($xml) > 5 * 1024 * 1024) {
            $issues[] = 'Il file XML supera il limite Aruba di 5 MB.';
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new DOMDocument;
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);

        if (! $loaded) {
            $issues[] = 'Il file XML generato non è formalmente leggibile.';
        } elseif ($document->doctype !== null) {
            $issues[] = 'Il file XML contiene una dichiarazione non consentita.';
        } else {
            $root = $document->documentElement;

            if (
                $root === null
                || $root->localName !== 'FatturaElettronica'
                || $root->namespaceURI !== self::NAMESPACE
            ) {
                $issues[] = 'Il file XML non usa il formato FatturaPA previsto.';
            }

            if ($root?->getAttribute('versione') !== 'FPR12') {
                $issues[] = 'Il formato di trasmissione deve essere FPR12.';
            }

            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('p', self::NAMESPACE);

            $requiredValues = [
                '/p:FatturaElettronica/FatturaElettronicaHeader/DatiTrasmissione/IdTrasmittente/IdPaese' => 'IT',
                '/p:FatturaElettronica/FatturaElettronicaHeader/DatiTrasmissione/IdTrasmittente/IdCodice' => '01879020517',
                '/p:FatturaElettronica/FatturaElettronicaHeader/DatiTrasmissione/FormatoTrasmissione' => 'FPR12',
                '/p:FatturaElettronica/FatturaElettronicaBody/DatiGenerali/DatiGeneraliDocumento/TipoDocumento' => 'TD01',
            ];

            foreach ($requiredValues as $expression => $expected) {
                if (trim((string) $xpath->evaluate("string({$expression})")) !== $expected) {
                    $issues[] = 'Il file XML non contiene tutti i dati fiscali obbligatori.';
                    break;
                }
            }

            if ((int) $xpath->evaluate('count(/p:FatturaElettronica/FatturaElettronicaBody/DatiBeniServizi/DettaglioLinee)') < 1) {
                $issues[] = 'Il file XML non contiene voci di fatturazione.';
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($issues !== []) {
            throw new ElectronicInvoiceXmlException(array_values(array_unique($issues)));
        }
    }
}
