<?php

namespace App\Domain\Finance\Actions;

use App\Models\BillingProfile;
use App\Models\ExpenseDocument;
use App\Services\Integrations\Aruba\ArubaConfiguration;
use App\Services\Integrations\Aruba\ArubaInvoiceClient;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportArubaExpenseDocument
{
    public function __construct(private ArubaInvoiceClient $client, private ArubaConfiguration $configuration, private StoreExpenseDocument $store) {}

    public function execute(string $id, int $bodyIndex): ExpenseDocument
    {
        $environment = $this->configuration->environment();
        $profile = BillingProfile::current();
        if (! $profile?->vat_number || ! $profile->vat_country_code) {
            $this->invalid('Completa prima i dati fiscali dell’agenzia, inclusa la partita IVA.');
        }
        $account = hash('sha256', implode('|', [$environment, $this->configuration->username(), $profile->vat_country_code, $profile->vat_number]));
        $existing = ExpenseDocument::where('aruba_account', $account)->where('aruba_id', $id)->where('aruba_body_index', $bodyIndex)->first();
        if ($existing && Storage::disk($existing->disk)->exists($existing->path)) {
            return $existing;
        }
        $payload = $this->client->receivedInvoiceDetail($id);
        if (($payload['docType'] ?? '') !== 'in' || (string) ($payload['id'] ?? '') !== $id) {
            $this->invalid('Aruba non ha restituito la fattura ricevuta richiesta.');
        }
        $encoded = ($payload['unsignedFile'] ?? '') ?: ($payload['file'] ?? '');
        $xml = is_string($encoded) && strlen($encoded) <= 15 * 1024 * 1024 ? base64_decode($encoded, true) : false;
        if (! $xml || strlen($xml) > 10 * 1024 * 1024 || preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $xml)) {
            $this->invalid('Il documento XML restituito da Aruba non è valido o supera 10 MB.');
        }
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
                $this->invalid('Aruba non ha restituito un XML leggibile.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $get = fn (string $path, $context = null) => trim($xpath->evaluate('string('.$path.')', $context));
        $header = '/*[local-name()="FatturaElettronica"]/*[local-name()="FatturaElettronicaHeader"]';
        $receiver = $header.'/*[local-name()="CessionarioCommittente"]/*[local-name()="DatiAnagrafici"]/*[local-name()="IdFiscaleIVA"]';
        if (strtoupper($get($receiver.'/*[local-name()="IdPaese"]')) !== strtoupper($profile->vat_country_code)
            || strtoupper($get($receiver.'/*[local-name()="IdCodice"]')) !== strtoupper($profile->vat_number)) {
            $this->invalid('La fattura non è intestata alla partita IVA configurata per l’agenzia.');
        }
        $body = $xpath->query('/*[local-name()="FatturaElettronica"]/*[local-name()="FatturaElettronicaBody"]')->item($bodyIndex);
        if (! $body) {
            $this->invalid('La fattura selezionata non è presente nel lotto Aruba.');
        }
        $general = './*[local-name()="DatiGenerali"]/*[local-name()="DatiGeneraliDocumento"]';
        if ($get($general.'/*[local-name()="Divisa"]', $body) !== 'EUR') {
            $this->invalid('Il prospetto gestisce importi in euro. Questa fattura richiede una verifica della valuta.');
        }
        if (in_array($get($general.'/*[local-name()="TipoDocumento"]', $body), ['TD04', 'TD08'], true)) {
            $this->invalid('Questo documento è una nota di credito: non può essere registrato come una spesa da pagare.');
        }
        $sender = $header.'/*[local-name()="CedentePrestatore"]/*[local-name()="DatiAnagrafici"]';
        $name = $get($sender.'/*[local-name()="Anagrafica"]/*[local-name()="Denominazione"]');
        if ($name === '') {
            $name = trim($get($sender.'/*[local-name()="Anagrafica"]/*[local-name()="Nome"]').' '.$get($sender.'/*[local-name()="Anagrafica"]/*[local-name()="Cognome"]'));
        }
        $number = $get($general.'/*[local-name()="Numero"]', $body);
        $amount = $get($general.'/*[local-name()="ImportoTotaleDocumento"]', $body);
        // The provider supplies the document total when the optional XML total is absent.
        if ($amount === '') {
            $providerTotal = $payload['invoices'][$bodyIndex]['totalDocument'] ?? null;
            $amount = is_numeric($providerTotal) ? number_format((float) $providerTotal, 2, '.', '') : '';
        }
        $dueDates = $xpath->query('./*[local-name()="DatiPagamento"]/*[local-name()="DettaglioPagamento"]/*[local-name()="DataScadenzaPagamento"]', $body);
        $data = [
            'kind' => 'invoice', 'issuer' => $name,
            'issuer_identifier' => $get($sender.'/*[local-name()="IdFiscaleIVA"]/*[local-name()="IdCodice"]') ?: $get($sender.'/*[local-name()="CodiceFiscale"]'),
            'issuer_country' => strtoupper($get($sender.'/*[local-name()="IdFiscaleIVA"]/*[local-name()="IdPaese"]')),
            'number' => $number, 'document_date' => $get($general.'/*[local-name()="Data"]', $body),
            'due_date' => $dueDates->length === 1 ? trim($dueDates->item(0)->textContent) : null,
            'amount' => $amount, 'currency' => 'EUR',
            'aruba_environment' => $environment, 'aruba_account' => $account, 'aruba_id' => $id, 'aruba_body_index' => $bodyIndex,
        ];
        $validator = Validator::make($data, [
            'issuer' => 'required|string|max:255', 'issuer_identifier' => 'required|string|max:50',
            'issuer_country' => 'required|string|size:2|regex:/^[A-Z]{2}$/',
            'number' => 'required|string|max:100', 'document_date' => 'required|date_format:Y-m-d',
            'due_date' => 'nullable|date_format:Y-m-d', 'amount' => 'required|numeric|decimal:0,2|between:0.01,99999999.99',
        ]);
        if ($validator->fails()) {
            $this->invalid('I dati della fattura ricevuta sono incompleti o non validi. Verifica il documento in Aruba.');
        }

        return $this->store->execute($data, $xml, 'fattura-aruba-'.preg_replace('/[^A-Za-z0-9_-]/', '_', $id).'.xml');
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['document' => $message]);
    }
}
