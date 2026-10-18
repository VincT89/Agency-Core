<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Services\ImportedInvoiceDocument;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class InvoiceDocumentController extends Controller
{
    public function invoice(Invoice $invoice, ImportedInvoiceDocument $reader): Response|RedirectResponse
    {
        $this->authorize('view', $invoice);
        abort_unless($invoice->fiscal_status === InvoiceFiscalStatus::Imported, 404);

        return $this->render($invoice, $reader, 'Fattura', route('invoices.show', $invoice));
    }

    public function creditNote(CreditNote $creditNote, ImportedInvoiceDocument $reader): Response|RedirectResponse
    {
        $this->authorize('view', $creditNote);

        return $this->render($creditNote, $reader, 'Nota di credito', route('credit-notes.show', $creditNote));
    }

    private function render(Invoice|CreditNote $record, ImportedInvoiceDocument $reader, string $title, string $backUrl): Response|RedirectResponse
    {
        try {
            $document = $reader->read($record);
        } catch (ValidationException $exception) {
            return redirect($backUrl)->with('error', $exception->validator->errors()->first());
        }
        $this->authorize('download', $document['attachment']);

        return response()->view('invoices.document', compact('document', 'title', 'backUrl'))
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
