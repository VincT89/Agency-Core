<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
class InvoiceController extends Controller
{
    public function index(Request $request, \App\Domain\Finance\Queries\InvoiceQuery $invoiceQuery): View
    {
        $this->authorize('viewAny', Invoice::class);

        // Calcola i KPI globali applicando automaticamente il ProjectSupremacyScope
        $globalQuery = $invoiceQuery->forIndex([]);
        
        $overdueCount = (clone $globalQuery)->where('status', 'overdue')->count();
        $draftCount   = (clone $globalQuery)->where('status', 'draft')->count();
        $unpaidTotal  = (clone $globalQuery)
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->get()
            ->sum(fn($i) => $i->residual);

        // Genera la lista fatture paginata applicando i filtri di ricerca
        $invoices = $invoiceQuery->forIndex($request->all())->paginate(15)->withQueryString();

        return view('invoices.index', compact('invoices', 'overdueCount', 'draftCount', 'unpaidTotal'));
    }

    public function create(): View
    {
        $this->authorize('create', Invoice::class);
        $clients = Client::query()
            ->with('projects')
            ->orderBy('name')
            ->get();

        return view('invoices.create', [
            'clients' => $clients,
            'statuses' => Invoice::STATUSES,
        ]);
    }

    public function store(StoreInvoiceRequest $request, \App\Domain\Finance\Actions\CreateInvoiceAction $action): RedirectResponse
    {
        $invoice = $action->execute($request->validated());

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Fattura creata correttamente.');
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);
        $invoice->load(['client', 'project', 'creator', 'payments', 'auditLogs.user', 'attachments.uploader']);

        return view('invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice): View
    {
        $this->authorize('update', $invoice);
        $clients = Client::query()
            ->with('projects')
            ->orderBy('name')
            ->get();

        return view('invoices.edit', [
            'invoice' => $invoice,
            'clients' => $clients,
            'statuses' => Invoice::STATUSES,
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $data = $request->validated();

        $data['total'] = (float) $data['subtotal'] + (float) $data['tax_amount'];

        $invoice->update($data);

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Fattura aggiornata correttamente.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);
        $invoice->delete();

        return redirect()
            ->route('invoices.index')
            ->with('success', 'Fattura eliminata correttamente.');
    }
}