<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Actions\ImportIssuedInvoice;
use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InvoiceImportBatch
{
    public function preview(array $files): array
    {
        Gate::authorize('create', Invoice::class);
        $rows = [];
        foreach ($files as $index => $file) {
            try {
                $xml = app(InvoiceImportFile::class)->xml($file['content'], $file['format']);
                $documents = app(InvoiceImportReader::class)->read($xml, 'out');
                foreach ($documents as $document) {
                    if (count($rows) >= 100) {
                        throw ValidationException::withMessages(['file' => 'Importa al massimo 100 documenti per volta.']);
                    }
                    $matches = app(ImportIssuedInvoice::class)->matchingClients($document['counterparty']);
                    $rows[] = ['file_index' => $index, 'body_index' => $document['body_index'], 'filename' => $file['filename'],
                        'number' => $document['number'], 'document_type' => $document['document_type'],
                        'client' => $document['counterparty']['name'], 'total' => $document['total'],
                        'payable_total' => $document['payable_total'], 'status' => 'pending',
                        'message' => $matches->isEmpty() ? 'Nuovo cliente da creare' : ($matches->count() === 1 ? 'Cliente presente: anagrafica conservata' : 'Più anagrafiche corrispondenti: da verificare')];
                }
            } catch (ValidationException $exception) {
                if (isset($exception->errors()['file'])) {
                    throw $exception;
                }
                $rows[] = ['file_index' => $index, 'filename' => $file['filename'], 'status' => 'error',
                    'message' => $exception->validator->errors()->first()];
            }
        }

        return $rows;
    }

    public function import(array $files): array
    {
        Gate::authorize('create', Invoice::class);
        $rows = $this->preview($files);
        foreach ($rows as &$row) {
            if ($row['status'] === 'error') {
                continue;
            }
            try {
                $source = $files[$row['file_index']];
                $documents = app(InvoiceImportReader::class)->read(app(InvoiceImportFile::class)->xml($source['content'], $source['format']), 'out');
                $document = $documents[$row['body_index']];
                $importer = app(ImportIssuedInvoice::class);
                $matches = $importer->matchingClients($document['counterparty']);
                if ($matches->count() > 1) {
                    throw ValidationException::withMessages(['clientId' => 'Più clienti hanno questi dati fiscali. Verifica le anagrafiche e importa il documento singolarmente.']);
                }
                $record = $importer->execute($document, $source, $matches->first()?->id, $matches->isEmpty(), $document['due_date']);
                $row['status'] = $record->wasRecentlyCreated ? 'imported' : 'skipped';
                $row['message'] = $record->wasRecentlyCreated ? ($record instanceof CreditNote ? 'Nota di credito importata: collega le fatture' : 'Fattura importata da incassare') : 'Già presente: nessuna modifica';
                $row['url'] = route($record instanceof CreditNote ? 'credit-notes.show' : 'invoices.show', $record);
            } catch (ValidationException $exception) {
                $row['status'] = 'error';
                $row['message'] = $exception->validator->errors()->first();
            }
        }
        unset($row);

        return $rows;
    }
}
