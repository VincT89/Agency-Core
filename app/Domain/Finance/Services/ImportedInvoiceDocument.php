<?php

namespace App\Domain\Finance\Services;

use App\Models\CreditNote;
use App\Models\Invoice;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImportedInvoiceDocument
{
    public function read(Invoice|CreditNote $record): array
    {
        $attachment = $record->attachments()->where('description', 'Documento originale importato')
            ->whereIn('extension', ['xml', 'p7m'])->oldest('id')->first();
        if (! $attachment) {
            $this->invalid();
        }
        $disk = Storage::disk($attachment->disk);
        if (! $disk->exists($attachment->path) || $disk->size($attachment->path) > 10 * 1024 * 1024) {
            $this->invalid();
        }
        $xml = app(InvoiceImportFile::class)->xml($disk->get($attachment->path), $attachment->extension);
        /** Never evaluate XML stylesheets, entities or external resources from an imported file. */
        if (strlen($xml) > 10 * 1024 * 1024 || str_contains($xml, "\0") || preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $xml)) {
            $this->invalid();
        }
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS) || $dom->doctype
                || $dom->documentElement?->localName !== 'FatturaElettronica') {
                $this->invalid();
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($dom);
        $header = $xpath->query('/*/*[local-name()="FatturaElettronicaHeader"]')->item(0);
        $index = $record->import_metadata['body_index'] ?? 0;
        $bodies = $xpath->query('/*/*[local-name()="FatturaElettronicaBody"]');
        if (! $header || ! is_int($index) || $index < 0 || $index >= $bodies->length) {
            $this->invalid();
        }
        $body = $bodies->item($index);
        $general = $this->nodes($xpath, $body, 'DatiGenerali/DatiGeneraliDocumento')->item(0);
        $number = $record instanceof Invoice ? $record->display_number : $record->number;
        if (! $general || $this->value($xpath, $general, 'Numero') !== $number
            || $this->value($xpath, $general, 'Data') !== $record->issue_date->format('Y-m-d')
            || $this->value($xpath, $general, 'TipoDocumento') !== $record->fiscal_document_type) {
            $this->invalid();
        }
        /** Read the historical issuer and customer from the original, independently of today's registry. */
        $document = [
            'number' => $number,
            'type' => $record->fiscal_document_type,
            'date' => $this->value($xpath, $general, 'Data'),
            'currency' => $this->value($xpath, $general, 'Divisa'),
            'issuer' => $this->party($xpath, $header, 'CedentePrestatore'),
            'customer' => $this->party($xpath, $header, 'CessionarioCommittente'),
            'recipient_code' => $this->value($xpath, $header, 'DatiTrasmissione/CodiceDestinatario'),
            'recipient_pec' => $this->value($xpath, $header, 'DatiTrasmissione/PECDestinatario'),
            'subtotal' => $record->subtotal,
            'tax' => $record->tax_amount,
            'total' => $record->total,
            'payable' => $record->payable_total ?? $record->total,
            'split_tax' => $record->import_metadata['split_payment_tax'] ?? '0.00',
            'stamp' => $this->value($xpath, $general, 'DatiBollo/ImportoBollo'),
            'rounding' => $this->value($xpath, $general, 'Arrotondamento'),
            'items' => [], 'taxes' => [], 'payments' => [], 'notes' => [], 'references' => [],
            'attachment' => $attachment,
        ];
        foreach ($this->nodes($xpath, $body, 'DatiBeniServizi/DettaglioLinee') as $line) {
            if (count($document['items']) >= 1000) {
                $this->invalid();
            }
            $item = $this->fields($xpath, $line, [
                'description' => 'Descrizione', 'quantity' => 'Quantita', 'unit' => 'UnitaMisura',
                'unit_price' => 'PrezzoUnitario', 'total' => 'PrezzoTotale', 'vat_rate' => 'AliquotaIVA',
                'nature' => 'Natura', 'period_from' => 'DataInizioPeriodo', 'period_to' => 'DataFinePeriodo',
            ]);
            $item['adjustments'] = [];
            foreach ($this->nodes($xpath, $line, 'ScontoMaggiorazione') as $adjustment) {
                $item['adjustments'][] = $this->fields($xpath, $adjustment, ['type' => 'Tipo', 'percentage' => 'Percentuale', 'amount' => 'Importo']);
            }
            $document['items'][] = $item;
        }
        foreach ($this->nodes($xpath, $body, 'DatiBeniServizi/DatiRiepilogo') as $tax) {
            $document['taxes'][] = $this->fields($xpath, $tax, [
                'rate' => 'AliquotaIVA', 'nature' => 'Natura', 'taxable' => 'ImponibileImporto', 'amount' => 'Imposta',
                'exigibility' => 'EsigibilitaIVA', 'reference' => 'RiferimentoNormativo',
            ]);
        }
        foreach ($this->nodes($xpath, $body, 'DatiPagamento/DettaglioPagamento') as $payment) {
            $document['payments'][] = $this->fields($xpath, $payment, [
                'method' => 'ModalitaPagamento', 'date' => 'DataScadenzaPagamento', 'amount' => 'ImportoPagamento',
                'beneficiary' => 'Beneficiario', 'bank' => 'IstitutoFinanziario', 'iban' => 'IBAN', 'bic' => 'BIC',
            ]);
        }
        foreach ($this->nodes($xpath, $general, 'Causale') as $note) {
            $document['notes'][] = trim($note->textContent);
        }
        foreach (['DatiFattureCollegate' => 'Fattura collegata', 'DatiOrdineAcquisto' => 'Ordine di acquisto',
            'DatiContratto' => 'Contratto', 'DatiConvenzione' => 'Convenzione'] as $tag => $label) {
            foreach ($this->nodes($xpath, $body, 'DatiGenerali/'.$tag) as $reference) {
                $document['references'][] = ['label' => $label] + $this->fields($xpath, $reference,
                    ['number' => 'IdDocumento', 'date' => 'Data', 'cup' => 'CodiceCUP', 'cig' => 'CodiceCIG']);
            }
        }

        return $document;
    }

    private function party(DOMXPath $xpath, DOMNode $header, string $tag): array
    {
        $node = $this->nodes($xpath, $header, $tag)->item(0);
        if (! $node) {
            $this->invalid();
        }
        $party = $this->fields($xpath, $node, [
            'name' => 'DatiAnagrafici/Anagrafica/Denominazione', 'vat_number' => 'DatiAnagrafici/IdFiscaleIVA/IdCodice',
            'vat_country' => 'DatiAnagrafici/IdFiscaleIVA/IdPaese', 'tax_code' => 'DatiAnagrafici/CodiceFiscale',
            'address' => 'Sede/Indirizzo', 'postal_code' => 'Sede/CAP', 'city' => 'Sede/Comune',
            'province' => 'Sede/Provincia', 'country' => 'Sede/Nazione', 'regime' => 'DatiAnagrafici/RegimeFiscale',
        ]);
        $party['name'] = $party['name'] ?: trim($this->value($xpath, $node, 'DatiAnagrafici/Anagrafica/Nome').' '.$this->value($xpath, $node, 'DatiAnagrafici/Anagrafica/Cognome'));
        $party['address'] = trim($party['address'].' '.$this->value($xpath, $node, 'Sede/NumeroCivico'));

        return $party;
    }

    private function fields(DOMXPath $xpath, DOMNode $node, array $fields): array
    {
        return array_map(fn ($path) => $this->value($xpath, $node, $path), $fields);
    }

    private function nodes(DOMXPath $xpath, DOMNode $node, string $path): \DOMNodeList
    {
        return $xpath->query('./'.implode('/', array_map(fn ($tag) => '*[local-name()="'.$tag.'"]', explode('/', $path))), $node);
    }

    private function value(DOMXPath $xpath, DOMNode $node, string $path): string
    {
        return trim($this->nodes($xpath, $node, $path)->item(0)?->textContent ?? '');
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['document' => 'Anteprima non disponibile: il documento XML originale è mancante o non leggibile. I dati importati restano disponibili nel dettaglio.']);
    }
}
