<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Actions\ChangeInvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceStatusController extends Controller
{
    public function edit(Invoice $invoice): View
    {
        $this->authorize('changeStatus', $invoice);
        $invoice->load(['client', 'payments']);

        return view('invoices.status', ['invoice' => $invoice, 'version' => ChangeInvoiceStatus::version($invoice)]);
    }

    public function update(Request $request, Invoice $invoice, ChangeInvoiceStatus $action): RedirectResponse
    {
        $this->authorize('changeStatus', $invoice);
        $receipt = in_array($request->input('status'), ['paid', 'partially_paid'], true) && $invoice->residual > 0;
        $data = $request->validate([
            'status' => ['required', Rule::in(ChangeInvoiceStatus::STATUSES)],
            'version' => ['required', 'string', 'size:64'],
            'amount' => ['required_if:status,partially_paid', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'payment_date' => [Rule::requiredIf($receipt), 'nullable', 'date_format:Y-m-d'],
            'method' => [Rule::requiredIf($receipt), 'nullable', Rule::in(Payment::METHODS)],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);
        $invoice = $action->execute($invoice, $data);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Stato della fattura aggiornato.');
    }
}
