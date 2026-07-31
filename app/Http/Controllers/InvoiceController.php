<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Domain\Finance\Services\InvoiceAmountsCalculator;
use App\Domain\Finance\Services\InvoiceFiscalReadinessService;
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
        $readyFiscalCount = (clone $globalQuery)->where('fiscal_status', 'ready')->count();
        $unpaidTotal  = (clone $globalQuery)
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->get()
            ->sum(fn($i) => $i->residual);

        // Genera la lista fatture paginata applicando i filtri di ricerca
        $invoices = $invoiceQuery->forIndex($request->all())->paginate(15)->withQueryString();

        return view('invoices.index', compact(
            'invoices',
            'overdueCount',
            'draftCount',
            'readyFiscalCount',
            'unpaidTotal',
        ));
    }

    public function create(\App\Domain\Core\Queries\ClientQuery $clientQuery): View
    {
        $this->authorize('create', Invoice::class);
        $clients = $clientQuery->forInvoiceDropdown()->get();

        return view('invoices.create', [
            'clients' => $clients,
            'statuses' => Invoice::STATUSES,
            'vatNatures' => \App\Enums\Finance\VatNature::cases(),
        ]);
    }

    public function store(StoreInvoiceRequest $request, \App\Domain\Finance\Actions\CreateInvoiceAction $action): RedirectResponse
    {
        $invoice = $action->execute($request->validated());

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Fattura creata correttamente.');
    }

    public function show(
        Invoice $invoice,
        InvoiceFiscalReadinessService $readinessService,
    ): View
    {
        $this->authorize('view', $invoice);
        $invoice->load(['client', 'project', 'marketingCampaign', 'creator', 'items', 'payments', 'auditLogs.user', 'attachments.uploader']);

        $fiscalReadiness = $readinessService->check($invoice);

        return view('invoices.show', compact('invoice', 'fiscalReadiness'));
    }

    public function edit(Invoice $invoice, \App\Domain\Core\Queries\ClientQuery $clientQuery): View
    {
        $this->authorize('update', $invoice);
        $invoice->load(['client', 'project', 'marketingCampaign', 'items']);
        $clients = $clientQuery->forInvoiceDropdown()->get();

        return view('invoices.edit', [
            'invoice'       => $invoice,
            'clients'       => $clients,
            'statuses'      => Invoice::STATUSES,
            'existingItems' => $invoice->items->values(),
            'vatNatures'    => \App\Enums\Finance\VatNature::cases(),
        ]);
    }

    public function update(
        UpdateInvoiceRequest $request,
        Invoice $invoice,
        InvoiceAmountsCalculator $amounts,
    ): RedirectResponse
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
            $attrs = $amounts->lineAttributes($line);

            if (!empty($line['id'])) {
                $item = $invoice->items()
                    ->where('id', (int) $line['id'])
                    ->first();

                if ($item && $item->billable_type !== null) {
                    $attrs = $amounts->lineAttributes(array_merge($line, [
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                    ]));
                    $attrs = array_intersect_key($attrs, array_flip([
                        'unit_of_measure',
                        'vat_rate',
                        'vat_nature',
                        'vat_reference',
                        'tax_amount',
                        'total_with_tax',
                    ]));
                }

                $item?->update($attrs);
            } else {
                $invoice->items()->create(array_merge($attrs, [
                    'billable_type' => null,
                    'billable_id'   => null,
                ]));
            }
        }

        if (
            $invoice->items()->exists()
            && ! $invoice->items()->whereNull('vat_rate')->exists()
        ) {
            $amounts->recalculate($invoice);
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
