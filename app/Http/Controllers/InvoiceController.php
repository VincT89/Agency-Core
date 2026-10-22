<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Project;
use App\Domain\Finance\Queries\InvoiceDocumentQuery;
use App\Enums\Finance\InvoiceFiscalStatus;
use App\Domain\Finance\Services\InvoiceAmountsCalculator;
use App\Domain\Finance\Services\FatturaPaXmlBuilder;
use App\Domain\Finance\Services\InvoiceFiscalReadinessService;
use App\Enums\Finance\ElectronicInvoiceTransmissionStatus;
use App\Exceptions\Finance\ArubaConfigurationException;
use App\Exceptions\Finance\ElectronicInvoiceXmlException;
use App\Services\Integrations\Aruba\ArubaConfiguration;
use App\Services\FinanceReportQuery;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
class InvoiceController extends Controller
{
    public function index(Request $request, \App\Domain\Finance\Queries\InvoiceQuery $invoiceQuery, InvoiceDocumentQuery $documentQuery, FinanceReportQuery $reports): View|JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);
        $this->authorize('viewAny', CreditNote::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'document_type' => ['nullable', Rule::in(['TD01', 'TD04'])],
            'status' => ['nullable', Rule::in([...Invoice::STATUSES, 'pending', 'credited'])],
            'fiscal_status' => ['nullable', Rule::enum(InvoiceFiscalStatus::class)],
            'client_id' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', ...($request->filled('start_date') ? ['after_or_equal:start_date'] : [])],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', Rule::in(['date_desc', 'date_asc', 'number_asc', 'number_desc', 'type_asc', 'type_desc', 'amount_asc', 'amount_desc'])],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'service_type' => ['nullable', Rule::in(['domain', 'hosting'])],
            'hosting_service_id' => ['nullable', 'integer', 'exists:hosting_services,id'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0', ...($request->filled('min_amount') ? ['gte:min_amount'] : [])],
        ]);

        $documents = $documentQuery->paginate($filters);
        if ($request->header('X-Finance-Partial') === 'invoice-list') {
            return response()->json(['html' => view('invoices.partials.document-list', compact('documents'))->render(), 'document_count' => $documents->total()]);
        }

        // Calcola i KPI globali applicando automaticamente il ProjectSupremacyScope
        $globalQuery = $invoiceQuery->forIndex([]);
        
        $overdueCount = (clone $globalQuery)->pastDue()->count();
        $outstandingCount = (clone $globalQuery)->awaitingPayment()->count();
        $draftCount   = (clone $globalQuery)->where('status', 'draft')->count();
        $readyFiscalCount = (clone $globalQuery)->where('fiscal_status', 'ready')->count();
        /** Il totale generale coincide con il residuo effettivo mostrato nel Riepilogo. */
        $unpaidTotal = $reports->totals()['actual_outstanding'];

        return view('invoices.index', compact(
            'documents',
            'overdueCount',
            'outstandingCount',
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
            'invoiceContainer' => Project::invoiceContainer(),
            'statuses' => Invoice::STATUSES,
            'vatNatures' => \App\Enums\Finance\VatNature::cases(),
        ]);
    }

    public function clientProjects(Client $client): JsonResponse
    {
        $this->authorize('create', Invoice::class);
        $this->authorize('view', $client);

        return response()->json(Project::query()->where('status', 'active')
            ->where(fn ($query) => $query->where('client_id', $client->id)
                ->orWhere('system_key', Project::INVOICE_CONTAINER_KEY))
            ->orderBy('name')->get(['id', 'name']));
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
        FatturaPaXmlBuilder $xmlBuilder,
        ArubaConfiguration $arubaConfiguration,
    ): View
    {
        $this->authorize('view', $invoice);
        $invoice->load([
            'client',
            'project',
            'hostingService',
            'marketingCampaign',
            'creator',
            'items.hostingService',
            'importedServiceLinks.hostingService',
            'payments',
            'creditAllocations.creditNote',
            'auditLogs.user',
            'attachments.uploader',
            'electronicInvoiceTransmissions' => fn ($query) => $query
                ->with('submitter')
                ->with(['events' => fn ($events) => $events->latest('occurred_at')])
                ->latest('attempt_number'),
        ]);

        $fiscalReadiness = $readinessService->check($invoice);

        try {
            $arubaStatus = $arubaConfiguration->status();
        } catch (ArubaConfigurationException $exception) {
            $arubaStatus = [
                'enabled' => false,
                'environment' => null,
                'environment_label' => 'Configurazione non valida',
                'credentials_configured' => false,
                'callback_configured' => false,
                'allow_send' => false,
                'ready_for_validation' => false,
                'ready_for_send' => false,
                'configuration_error' => $exception->getMessage(),
            ];
        }

        $currentXmlHash = null;

        if (is_array($invoice->fiscal_snapshot)) {
            try {
                $currentXmlHash = $xmlBuilder->build($invoice->fiscal_snapshot)->hash;
            } catch (ElectronicInvoiceXmlException) {
                $currentXmlHash = null;
            }
        }

        $matchingValidation = $currentXmlHash !== null
            && $invoice->electronicInvoiceTransmissions->contains(
                fn ($transmission) => $transmission->mode === 'dry_run'
                    && $transmission->environment === ($arubaStatus['environment'] ?? null)
                    && $transmission->xml_hash === $currentXmlHash
                    && $transmission->status === ElectronicInvoiceTransmissionStatus::Validated
            );
        $latestLiveTransmission = $invoice->electronicInvoiceTransmissions
            ->firstWhere('mode', 'live');

        return view('invoices.show', compact(
            'invoice',
            'fiscalReadiness',
            'arubaStatus',
            'matchingValidation',
            'latestLiveTransmission',
        ));
    }

    public function edit(Invoice $invoice, \App\Domain\Core\Queries\ClientQuery $clientQuery): View
    {
        $this->authorize('update', $invoice);
        $invoice->load(['client', 'project', 'hostingService', 'marketingCampaign', 'items.hostingService:id,name']);
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

        return \Illuminate\Support\Facades\DB::transaction(function () use ($invoice, $data, $amounts) {
        $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
        $this->authorize('update', $invoice);
        $data['total'] = (float) $data['subtotal'] + (float) $data['tax_amount'];

        $invoice->update($data);
        $invoice->payments()->update(['project_id' => $invoice->project_id]);

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

                $attrs['hosting_service_id'] = array_key_exists('hosting_service_id', $line) ? ($line['hosting_service_id'] ?: null) : $item?->hosting_service_id;
                $item?->update($attrs);
            } else {
                $invoice->items()->create(array_merge($attrs, [
                    'billable_type' => null,
                    'billable_id'   => null,
                    'hosting_service_id' => $line['hosting_service_id'] ?? null,
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
        });
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
