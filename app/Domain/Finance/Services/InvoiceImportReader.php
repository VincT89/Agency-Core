<?php

namespace App\Domain\Finance\Services;

use App\Models\BillingProfile;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvoiceImportReader
{
    public const DOCUMENT_TYPES = ['TD01', 'TD02', 'TD03', 'TD04', 'TD06', 'TD24', 'TD25'];

    public function read(string $xml, string $direction): array
    {
        if (! in_array($direction, ['in', 'out'], true) || strlen($xml) > 10 * 1024 * 1024
            || str_contains($xml, "\0") || preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $xml)) {
            $this->invalid('Carica un XML FatturaPA valido, senza definizioni esterne, di massimo 10 MB.');
        }
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS) || $document->doctype
                || $document->documentElement?->localName !== 'FatturaElettronica') {
                $this->invalid('Il file non contiene un XML FatturaPA ordinario leggibile.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $header = $xpath->query('/*/*[local-name()="FatturaElettronicaHeader"]')->item(0);
        if (! $header) {
            $this->invalid('Manca l’intestazione della fattura.');
        }
        $sender = $this->party($xpath, $header, 'CedentePrestatore');
        $receiver = $this->party($xpath, $header, 'CessionarioCommittente');
        $agency = $direction === 'out' ? $sender : $receiver;
        $this->assertAgency($agency);
        $bodies = $xpath->query('/*/*[local-name()="FatturaElettronicaBody"]');
        if ($bodies->length < 1 || $bodies->length > 100) {
            $this->invalid('Il file deve contenere da una a 100 fatture.');
        }
        $documents = [];
        foreach ($bodies as $index => $body) {
            $general = $xpath->query('./*[local-name()="DatiGenerali"]/*[local-name()="DatiGeneraliDocumento"]', $body)->item(0);
            if (! $general) {
                $this->invalid('Mancano i dati generali della fattura.');
            }
            $type = $this->value($xpath, $general, 'TipoDocumento');
            if (! in_array($type, self::DOCUMENT_TYPES, true) || ($direction === 'in' && $type === 'TD04')) {
                $this->invalid('Il tipo '.$type.' richiede una gestione distinta. L’importazione non registra note di credito, autofatture o integrazioni come normali fatture.');
            }
            if ($this->value($xpath, $general, 'Divisa') !== 'EUR') {
                $this->invalid('L’importazione gestisce fatture in euro. Verifica separatamente i documenti in altre valute.');
            }
            if ($xpath->query('./*[local-name()="DatiRitenuta"]', $general)->length
                || ($direction === 'in' && $xpath->query('.//*[local-name()="EsigibilitaIVA" and text()="S"]', $body)->length)) {
                $this->invalid('Questa fattura contiene ritenute o split payment: occorre gestire separatamente il netto da pagare prima di importarla.');
            }
            $subtotal = 0;
            $tax = 0;
            $splitTax = 0;
            $summaries = $xpath->query('./*[local-name()="DatiBeniServizi"]/*[local-name()="DatiRiepilogo"]', $body);
            if (! $summaries->length) {
                $this->invalid('Mancano i riepiloghi IVA della fattura.');
            }
            foreach ($summaries as $summary) {
                $subtotal += CashAmount::cents($this->amount($this->value($xpath, $summary, 'ImponibileImporto')));
                $tax += CashAmount::cents($this->amount($this->value($xpath, $summary, 'Imposta')));
                if ($this->value($xpath, $summary, 'EsigibilitaIVA') === 'S') {
                    $splitTax += CashAmount::cents($this->amount($this->value($xpath, $summary, 'Imposta')));
                }
            }
            $total = $this->value($xpath, $general, 'ImportoTotaleDocumento');
            if ($total === '') {
                if ($xpath->query('./*[local-name()="ScontoMaggiorazione" or local-name()="DatiBollo" or local-name()="Arrotondamento"]', $general)->length) {
                    $this->invalid('Il totale documento manca e sono presenti rettifiche: verifica il documento originale prima dell’importazione.');
                }
                $total = CashAmount::decimal($subtotal + $tax);
            }
            $payments = [];
            foreach ($xpath->query('./*[local-name()="DatiPagamento"]/*[local-name()="DettaglioPagamento"]', $body) as $payment) {
                $payments[] = ['date' => $this->value($xpath, $payment, 'DataScadenzaPagamento'), 'amount' => $this->value($xpath, $payment, 'ImportoPagamento')];
            }
            $lines = [];
            foreach ($xpath->query('./*[local-name()="DatiBeniServizi"]/*[local-name()="DettaglioLinee"]', $body) as $line) {
                if (count($lines) >= 1000) {
                    $this->invalid('La fattura supera il limite di 1.000 righe.');
                }
                $lines[] = ['description' => $this->value($xpath, $line, 'Descrizione'), 'quantity' => $this->value($xpath, $line, 'Quantita'),
                    'unit_price' => $this->value($xpath, $line, 'PrezzoUnitario'), 'total' => $this->value($xpath, $line, 'PrezzoTotale'), 'vat_rate' => $this->value($xpath, $line, 'AliquotaIVA')];
            }
            $total = $this->amount($total);
            $payable = CashAmount::cents($total) - $splitTax;
            if ($payable <= 0) {
                $this->invalid('Il netto del documento non è valido. Verifica il totale e l’IVA in split payment.');
            }
            $references = [];
            foreach ($xpath->query('./*[local-name()="DatiGenerali"]/*[local-name()="DatiFattureCollegate"]', $body) as $reference) {
                $references[] = ['number' => $this->value($xpath, $reference, 'IdDocumento'),
                    'date' => $this->value($xpath, $reference, 'Data'), 'source' => 'xml'];
            }
            /** References written in descriptions remain suggestions until a person confirms the allocation. */
            if ($type === 'TD04') {
                foreach ($lines as $line) {
                    if (preg_match('/\bstorno\s+fattura\s+(?:n\.?\s*)?((?:(?:FPA|FPR)\s*)?\d+\/[\d]{2,4})\b/iu', $line['description'], $match)) {
                        $references[] = ['number' => trim($match[1]), 'date' => '', 'source' => 'description'];
                    }
                }
            }
            $headerData = [];
            foreach ($header->childNodes as $node) {
                if ($node->nodeType === XML_ELEMENT_NODE && $node->localName !== 'DatiTrasmissione') {
                    $headerData[] = $this->canonicalNode($node);
                }
            }
            $format = $this->value($xpath, $header, 'DatiTrasmissione/FormatoTrasmissione') ?: $document->documentElement->getAttribute('versione');
            $data = [
                'body_index' => $index, 'direction' => $direction, 'document_type' => $type,
                'number' => $this->value($xpath, $general, 'Numero'), 'issue_date' => $this->value($xpath, $general, 'Data'),
                'due_date' => count($payments) === 1 ? ($payments[0]['date'] ?: null) : null,
                'subtotal' => $this->amount(CashAmount::decimal($subtotal)), 'tax_amount' => $this->amount(CashAmount::decimal($tax)),
                'total' => $total, 'payable_total' => CashAmount::decimal($payable), 'split_payment_tax' => CashAmount::decimal($splitTax),
                'currency' => 'EUR', 'agency' => $agency, 'transmission_format' => $format,
                'credit_references' => $references,
                'content_fingerprint' => hash('sha256', json_encode([$format, $headerData, $this->canonicalNode($body)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
                'counterparty' => $direction === 'out' ? $receiver : $sender, 'items' => $lines, 'payments' => $payments,
            ];
            $validator = Validator::make($data, ['number' => 'required|string|max:100', 'issue_date' => 'required|date_format:Y-m-d',
                'due_date' => 'nullable|date_format:Y-m-d', 'total' => 'required|numeric|gt:0',
                'counterparty.name' => 'required|string|max:255', 'counterparty.vat_number' => 'nullable|string|max:50',
                'counterparty.tax_code' => 'nullable|string|max:50', 'counterparty.country_code' => 'required|alpha|size:2',
                'counterparty.address' => 'nullable|string|max:255', 'counterparty.postal_code' => 'nullable|string|max:10',
                'counterparty.city' => 'nullable|string|max:100', 'counterparty.province' => 'nullable|string|max:2',
                'payments.*.date' => 'nullable|date_format:Y-m-d', 'payments.*.amount' => ['nullable', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/']]);
            if ($validator->fails()) {
                $this->invalid('La fattura contiene dati anagrafici, date o importi incompleti o non validi.');
            }
            if ($splitTax > 0 && $payments !== [] && array_sum(array_map(fn ($payment) => CashAmount::cents($payment['amount'] ?: '0'), $payments)) !== $payable) {
                $this->invalid('L’importo dei pagamenti non corrisponde al netto dello split payment. Verifica il documento prima di importarlo.');
            }
            $documents[] = $data;
        }

        return $documents;
    }

    public function assertAgency(array $agency): void
    {
        $profile = BillingProfile::current();
        if (! $profile?->vat_number || ! $profile->vat_country_code) {
            $this->invalid('Completa prima la partita IVA e il paese nei dati fiscali dell’agenzia.');
        }
        if (strtoupper($agency['vat_number'] ?? '') !== strtoupper($profile->vat_number)
            || strtoupper($agency['country_code'] ?? '') !== strtoupper($profile->vat_country_code)) {
            $this->invalid('La partita IVA dell’agenzia non corrisponde alla direzione scelta: verifica se la fattura è emessa o ricevuta.');
        }
    }

    private function party(DOMXPath $xpath, DOMNode $header, string $tag): array
    {
        $node = $xpath->query('./*[local-name()="'.$tag.'"]', $header)->item(0);
        if (! $node) {
            $this->invalid('Mancano i dati del cedente o del destinatario.');
        }
        $name = $this->value($xpath, $node, 'DatiAnagrafici/Anagrafica/Denominazione');

        return [
            'name' => $name ?: trim($this->value($xpath, $node, 'DatiAnagrafici/Anagrafica/Nome').' '.$this->value($xpath, $node, 'DatiAnagrafici/Anagrafica/Cognome')),
            'vat_number' => $this->value($xpath, $node, 'DatiAnagrafici/IdFiscaleIVA/IdCodice'),
            'tax_code' => $this->value($xpath, $node, 'DatiAnagrafici/CodiceFiscale'),
            'country_code' => strtoupper($this->value($xpath, $node, 'DatiAnagrafici/IdFiscaleIVA/IdPaese') ?: $this->value($xpath, $node, 'Sede/Nazione')),
            'address' => trim($this->value($xpath, $node, 'Sede/Indirizzo').' '.$this->value($xpath, $node, 'Sede/NumeroCivico')),
            'postal_code' => $this->value($xpath, $node, 'Sede/CAP'), 'city' => $this->value($xpath, $node, 'Sede/Comune'),
            'province' => $this->value($xpath, $node, 'Sede/Provincia'),
        ];
    }

    private function value(DOMXPath $xpath, DOMNode $node, string $path): string
    {
        $expression = './'.implode('/', array_map(fn ($tag) => '*[local-name()="'.$tag.'"]', explode('/', $path)));

        return trim($xpath->evaluate('string('.$expression.')', $node));
    }

    private function amount(string $amount): string
    {
        if (! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $amount)) {
            $this->invalid('La fattura contiene un importo non valido o superiore al limite gestito.');
        }

        return CashAmount::decimal(CashAmount::cents($amount));
    }

    private function canonicalNode(DOMNode $node): array
    {
        $attributes = [];
        foreach ($node->attributes ?? [] as $attribute) {
            if ($attribute->namespaceURI !== 'http://www.w3.org/2000/xmlns/') {
                $attributes[$attribute->localName] = $attribute->value;
            }
        }
        ksort($attributes);
        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $children[] = $this->canonicalNode($child);
            }
        }

        return [$node->localName, $attributes, $children ?: trim($node->textContent)];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['document' => $message]);
    }
}
