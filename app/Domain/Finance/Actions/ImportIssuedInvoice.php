<?php

namespace App\Domain\Finance\Actions;

use App\Actions\Clients\CreateClientAction;
use App\Domain\Finance\Services\CashAmount;
use App\Domain\Finance\Services\InvoiceImportReader;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ImportIssuedInvoice
{
    public function execute(array $document, array $source, ?int $clientId, bool $createClient, ?string $dueDate): Invoice
    {
        Gate::authorize('create', Invoice::class);
        app(InvoiceImportReader::class)->assertAgency($document['agency']);
        if ($source['format'] === 'pdf' && ($createClient || (int) ($document['client_id'] ?? 0) !== $clientId)) {
            throw ValidationException::withMessages(['clientId' => 'Il cliente è cambiato. Verifica nuovamente i dati del PDF.']);
        }
        $normalize = fn ($value) => mb_strtoupper(preg_replace('/\s+/u', '', trim((string) $value)));
        $fingerprint = hash('sha256', implode('|', [$normalize($document['agency']['country_code']),
            $normalize($document['agency']['vat_number']), substr($document['issue_date'], 0, 4), $normalize($document['number'])]));
        $path = null;
        try {
            return DB::transaction(function () use ($document, $source, $clientId, $createClient, $dueDate, $fingerprint, &$path) {
                BillingProfile::where('profile_key', 'default')->lockForUpdate()->firstOrFail();
                $existing = Invoice::where('import_fingerprint', $fingerprint)->lockForUpdate()->first();
                if (! $existing) {
                    $existing = Invoice::whereYear('issue_date', substr($document['issue_date'], 0, 4))->where(function ($query) use ($document) {
                        $query->where('fiscal_number', $document['number'])->orWhere('number', $document['number']);
                    })->lockForUpdate()->first();
                }
                if ($existing) {
                    if ($existing->currency !== $document['currency'] || CashAmount::cents($existing->total) !== CashAmount::cents($document['total'])
                        || CashAmount::cents($existing->subtotal) !== CashAmount::cents($document['subtotal'])
                        || CashAmount::cents($existing->tax_amount) !== CashAmount::cents($document['tax_amount'])
                        || $existing->issue_date->format('Y-m-d') !== $document['issue_date']
                        || (filled($existing->fiscal_document_type) && $existing->fiscal_document_type !== $document['document_type'])
                        || (! $createClient && $clientId && (int) $existing->client_id !== $clientId)
                        || ($source['format'] !== 'pdf' && (! $existing->client || ! $this->matches($existing->client, $document['counterparty'])))) {
                        throw ValidationException::withMessages(['document' => 'Esiste già una fattura con questi riferimenti, ma con dati diversi. Verificala prima di proseguire.']);
                    }

                    return $existing;
                }
                if ($createClient) {
                    Gate::authorize('create', Client::class);
                    $matches = $this->matchingClients($document['counterparty']);
                    if ($matches->isNotEmpty()) {
                        throw ValidationException::withMessages(['clientId' => 'Esiste già un cliente con questi dati fiscali. Selezionalo per evitare un’anagrafica duplicata.']);
                    }
                    $party = $document['counterparty'];
                    $client = app(CreateClientAction::class)->execute(collect($party)->only(['name', 'vat_number', 'tax_code', 'address', 'postal_code', 'city', 'province', 'country_code'])->all());
                } else {
                    $client = Client::findOrFail($clientId);
                    Gate::authorize('view', $client);
                    if ($source['format'] !== 'pdf' && ! $this->matches($client, $document['counterparty'])) {
                        throw ValidationException::withMessages(['clientId' => 'I dati fiscali del cliente selezionato non corrispondono al destinatario della fattura.']);
                    }
                }
                $invoice = Invoice::create([
                    'client_id' => $client->id, 'created_by' => auth()->id(),
                    'number' => 'IMP-'.substr($document['issue_date'], 0, 4).'-'.$document['number'].'-'.substr($fingerprint, 0, 8),
                    'issue_date' => $document['issue_date'], 'due_date' => $dueDate,
                    'status' => 'issued', 'currency' => $document['currency'], 'subtotal' => $document['subtotal'],
                    'tax_amount' => $document['tax_amount'], 'total' => $document['total'], 'paid_total' => '0.00',
                    'fiscal_status' => InvoiceFiscalStatus::Imported, 'fiscal_document_type' => $document['document_type'],
                    'fiscal_locked_at' => now(), 'import_fingerprint' => $fingerprint, 'import_source' => $source['source'],
                    'import_metadata' => $document + collect($source)->only(['environment', 'aruba_id', 'filename', 'format'])->all(),
                ]);
                $storedName = Str::uuid().'.'.$source['format'];
                $path = 'invoice/'.$invoice->id.'/'.$storedName;
                if (! Storage::disk('attachments')->put($path, $source['content'])) {
                    throw new RuntimeException('Impossibile salvare il documento originale.');
                }
                $invoice->attachments()->create([
                    'type' => 'document', 'uploaded_by' => auth()->id(), 'disk' => 'attachments', 'directory' => 'invoice/'.$invoice->id,
                    'path' => $path, 'stored_name' => $storedName, 'original_name' => $source['filename'],
                    'mime_type' => match ($source['format']) {
                        'pdf' => 'application/pdf', 'p7m' => 'application/pkcs7-mime', default => 'application/xml'
                    }, 'extension' => $source['format'],
                    'size' => strlen($source['content']), 'description' => 'Documento originale importato',
                ]);

                return $invoice;
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('attachments')->delete($path);
            }
            throw $exception;
        }
    }

    public function matchingClients(array $party): Collection
    {
        if (blank($party['vat_number'] ?? '') && blank($party['tax_code'] ?? '')) {
            return collect();
        }

        return Client::query()->where(function ($query) use ($party) {
            $query->whereRaw('1 = 0');
            if (filled($party['vat_number'] ?? '')) {
                $query->orWhereIn('vat_number', [$party['vat_number'], ($party['country_code'] ?? '').$party['vat_number']]);
            }
            if (filled($party['tax_code'] ?? '')) {
                $query->orWhere('tax_code', $party['tax_code']);
            }
        })->get();
    }

    public function matches(Client $client, array $party): bool
    {
        $normalize = fn ($value) => strtoupper(preg_replace('/\s+/', '', (string) $value));
        $vat = $normalize($party['vat_number'] ?? '');
        $tax = $normalize($party['tax_code'] ?? '');
        $country = $normalize($party['country_code'] ?? '');
        $matched = false;
        if ($vat !== '' && filled($client->vat_number)) {
            if (! in_array($normalize($client->vat_number), [$vat, $country.$vat], true)
                || (filled($client->country_code) && $normalize($client->country_code) !== $country)) {
                return false;
            }
            $matched = true;
        }
        if ($tax !== '' && filled($client->tax_code)) {
            if ($normalize($client->tax_code) !== $tax) {
                return false;
            }
            $matched = true;
        }

        return $matched;
    }
}
