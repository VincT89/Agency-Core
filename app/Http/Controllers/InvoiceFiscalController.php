<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Actions\PrepareElectronicInvoiceAction;
use App\Domain\Finance\Actions\ReopenElectronicInvoiceDraftAction;
use App\Domain\Finance\Actions\SubmitElectronicInvoiceAction;
use App\Domain\Finance\Actions\SyncElectronicInvoiceTransmissionAction;
use App\Domain\Finance\Services\FatturaPaXmlBuilder;
use App\Enums\Finance\ElectronicInvoiceTransmissionStatus;
use App\Exceptions\Finance\ArubaApiException;
use App\Exceptions\Finance\ArubaConfigurationException;
use App\Exceptions\Finance\ElectronicInvoiceSubmissionException;
use App\Exceptions\Finance\ElectronicInvoiceXmlException;
use App\Exceptions\Finance\InvoiceFiscalPreparationException;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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

    public function validateWithAruba(
        Request $request,
        Invoice $invoice,
        SubmitElectronicInvoiceAction $action,
    ): RedirectResponse {
        $this->authorize('validateFiscal', $invoice);

        try {
            $action->execute($invoice, $request->user(), true);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with(
                'success',
                'Verifica Aruba completata. La fattura non è stata inviata allo SdI.'
            );
    }

    public function send(
        Request $request,
        Invoice $invoice,
        SubmitElectronicInvoiceAction $action,
    ): RedirectResponse {
        $this->authorize('sendFiscal', $invoice);

        try {
            $action->execute($invoice, $request->user(), false);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with(
                'success',
                'Fattura affidata ad Aruba. Gli aggiornamenti dello SdI verranno registrati automaticamente.'
            );
    }

    public function sync(
        Invoice $invoice,
        SyncElectronicInvoiceTransmissionAction $action,
    ): RedirectResponse {
        $this->authorize('syncFiscal', $invoice);

        $transmission = $invoice->electronicInvoiceTransmissions()
            ->where('mode', 'live')
            ->whereNotNull('upload_filename')
            ->latest('id')
            ->first();

        if ($transmission === null) {
            return back()->withErrors([
                'fiscal' => 'Non è disponibile un invio Aruba da aggiornare.',
            ]);
        }

        try {
            $action->execute($transmission);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Stato della fattura aggiornato da Aruba.');
    }

    public function downloadXml(
        Invoice $invoice,
        FatturaPaXmlBuilder $xmlBuilder,
    ): Response|RedirectResponse {
        $this->authorize('downloadFiscalXml', $invoice);

        try {
            $xml = $xmlBuilder->build((array) $invoice->fiscal_snapshot);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return response($xml->content, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$xml->filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function failure(Throwable $exception): RedirectResponse
    {
        if ($exception instanceof ElectronicInvoiceXmlException) {
            return back()->withErrors(['fiscal' => $exception->issues]);
        }

        if ($exception instanceof ArubaApiException) {
            return back()->withErrors(['fiscal' => $exception->userMessage]);
        }

        if ($exception instanceof ArubaConfigurationException) {
            return back()->withErrors([
                'fiscal' => 'Collegamento Aruba non disponibile.',
            ]);
        }

        if ($exception instanceof ElectronicInvoiceSubmissionException) {
            return back()->withErrors(['fiscal' => $exception->getMessage()]);
        }

        report($exception);

        return back()->withErrors([
            'fiscal' => 'L’operazione non è stata completata. Riprova o contatta l’amministratore.',
        ]);
    }
}
