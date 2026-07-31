<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Actions\PrepareElectronicInvoiceAction;
use App\Domain\Finance\Actions\ReopenElectronicInvoiceDraftAction;
use App\Exceptions\Finance\InvoiceFiscalPreparationException;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;

class InvoiceFiscalController extends Controller
{
    public function prepare(
        Invoice $invoice,
        PrepareElectronicInvoiceAction $action,
    ): RedirectResponse {
        $this->authorize('prepareFiscal', $invoice);

        try {
            $action->execute($invoice);
        } catch (InvoiceFiscalPreparationException $exception) {
            return back()->withErrors([
                'fiscal' => $exception->issues,
            ]);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with(
                'success',
                'Fattura fiscale preparata e bloccata. Non è stata inviata ad Aruba né allo SdI.'
            );
    }

    public function reopen(
        Invoice $invoice,
        ReopenElectronicInvoiceDraftAction $action,
    ): RedirectResponse {
        $this->authorize('reopenFiscal', $invoice);

        try {
            $action->execute($invoice);
        } catch (InvoiceFiscalPreparationException $exception) {
            return back()->withErrors([
                'fiscal' => $exception->issues,
            ]);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with(
                'success',
                'Bozza fiscale riaperta. Il numero già riservato è stato mantenuto.'
            );
    }
}
