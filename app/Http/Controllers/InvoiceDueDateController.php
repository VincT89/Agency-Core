<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Actions\ChangeInvoiceDueDate;
use App\Domain\Finance\Actions\ChangeInvoiceStatus;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceDueDateController extends Controller
{
    public function edit(Invoice $invoice): View
    {
        $this->authorize('changeDueDate', $invoice);
        $invoice->load('client');

        return view('invoices.due-date', ['invoice' => $invoice, 'version' => ChangeInvoiceStatus::version($invoice)]);
    }

    public function update(Request $request, Invoice $invoice, ChangeInvoiceDueDate $action): RedirectResponse
    {
        $this->authorize('changeDueDate', $invoice);
        $data = $request->validate(['due_date' => ['required', 'date_format:Y-m-d'], 'version' => ['required', 'string', 'size:64']]);
        $invoice = $action->execute($invoice, $data);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Scadenza gestionale aggiornata. Il documento originale resta invariato.');
    }
}
