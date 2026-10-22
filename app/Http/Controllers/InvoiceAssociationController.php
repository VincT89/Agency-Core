<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Actions\UpdateInvoiceAssociations;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceAssociationController extends Controller
{
    public function edit(Invoice $invoice)
    {
        $this->authorize('manageAssociations', $invoice);
        $invoice->load(['client', 'project', 'marketingCampaign', 'hostingService']);
        $assignmentType = $invoice->hostingService
            ? (in_array('domain', $invoice->hostingService->resolved_service_types, true) ? 'domain' : 'hosting')
            : ($invoice->project_id ? 'project' : ($invoice->marketing_campaign_id ? 'campaign' : 'none'));
        return view('invoices.associations', compact('invoice', 'assignmentType'));
    }

    public function update(Request $request, Invoice $invoice, UpdateInvoiceAssociations $action)
    {
        $action->execute($invoice, $request->only(['assignment_type', 'project_id', 'marketing_campaign_id', 'hosting_service_id',
            'new_project_name', 'new_project_description', 'replace_campaign', 'services']));
        return redirect()->route('invoices.show', $invoice)->with('success', 'Collegamento della fattura aggiornato.');
    }
}
