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
        $invoice->load(['client', 'project', 'creator', 'items', 'payments', 'auditLogs.user', 'attachments.uploader']);

        return view('invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice): View
    {
        $this->authorize('update', $invoice);
        $invoice->load(['client', 'project', 'items']);
        $clients = Client::query()
            ->with('projects')
            ->orderBy('name')
            ->get();

        return view('invoices.edit', [
            'invoice'       => $invoice,
            'clients'       => $clients,
            'statuses'      => Invoice::STATUSES,
            'existingItems' => $invoice->items->whereNull('billable_type')->values(),
            'linkedTotal'   => $invoice->items->whereNotNull('billable_type')->sum('total'),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $data = $request->validated();

        $data['total'] = (float) $data['subtotal'] + (float) $data['tax_amount'];

        $invoice->update($data);

        $incomingItems = collect($data['items'] ?? []);
        $incomingIds   = $incomingItems->pluck('id')->filter()->map(fn($id) => (int) $id);

        // Elimina le voci manuali che non sono più nel payload
        $invoice->items()
            ->whereNull('billable_type')
            ->whereNotIn('id', $incomingIds)
            ->delete();

        // Aggiorna o crea
        foreach ($incomingItems as $line) {
            $qty   = (float) $line['quantity'];
            $price = (float) $line['unit_price'];
            $attrs = [
                'description' => $line['description'],
                'quantity'    => $qty,
                'unit_price'  => $price,
                'total'       => $qty * $price,
            ];

            if (!empty($line['id'])) {
                // Aggiorna solo se la voce è manuale e appartiene a questa fattura
                $invoice->items()
                    ->whereNull('billable_type')
                    ->where('id', (int) $line['id'])
                    ->update($attrs);
            } else {
                $invoice->items()->create(array_merge($attrs, [
                    'billable_type' => null,
                    'billable_id'   => null,
                ]));
            }
        }

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