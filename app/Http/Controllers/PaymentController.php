<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoicePaymentSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
class PaymentController extends Controller
{
    public function __construct(
        protected InvoicePaymentSyncService $invoicePaymentSyncService
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Payment::class);
        $query = Payment::query()
            ->with(['invoice', 'client', 'project', 'creator'])
            ->latest('payment_date');

        $payments = $query->paginate(15);

        return view('payments.index', compact('payments'));
    }

    public function create(\Illuminate\Http\Request $request): View
    {
        $this->authorize('create', Payment::class);

        $invoices = Invoice::visibleTo(auth()->user())
            ->with(['client', 'project'])
            ->orderByDesc('issue_date')
            ->get();

        // Precompila l'ID fattura se passato via querystring
        $preselectedInvoice = null;
        if ($request->invoice_id) {
            $preselectedInvoice = Invoice::find($request->invoice_id);
        }

        return view('payments.create', [
            'invoices' => $invoices,
            'methods' => Payment::METHODS,
            'preselectedInvoice' => $preselectedInvoice,
        ]);
    }

    public function store(StorePaymentRequest $request, \App\Domain\Finance\Actions\RegisterPaymentAction $action): RedirectResponse
    {
        $payment = $action->execute($request->validated());

        return redirect()
            ->route('invoices.show', $payment->invoice)
            ->with('success', 'Pagamento registrato correttamente sulla fattura.');
    }

    public function show(Payment $payment): View
    {
        $this->authorize('view', $payment);
        $payment->load(['invoice', 'client', 'project', 'creator']);

        return view('payments.show', compact('payment'));
    }

    public function edit(Payment $payment): View
    {
        $this->authorize('update', $payment);

        return view('payments.edit', [
            'payment' => $payment,
            'methods' => Payment::METHODS,
        ]);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): RedirectResponse
    {
        $this->authorize('update', $payment);

        $data = $request->validated();
        \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $data) {
            $invoice = Invoice::lockForUpdate()->findOrFail($payment->invoice_id);
            $payment->refresh();
            $alreadyPaid = \App\Domain\Finance\Services\CashAmount::cents($invoice->payments()->whereKeyNot($payment->id)->sum('amount'));
            /** A later credit may exceed the remaining debt; existing receipts can still be corrected without increasing them. */
            if ($alreadyPaid + \App\Domain\Finance\Services\CashAmount::cents($data['amount']) > \App\Domain\Finance\Services\CashAmount::cents($invoice->receivable_total)
                && \App\Domain\Finance\Services\CashAmount::cents($data['amount']) > \App\Domain\Finance\Services\CashAmount::cents($payment->amount)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'Il pagamento supera il totale residuo della fattura.']);
            }
            $payment->update($data);
            $this->invoicePaymentSyncService->sync($invoice);
        });

        return redirect()
            ->route('invoices.show', $payment->invoice)
            ->with('success', 'Pagamento aggiornato correttamente.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);
        $invoice = \Illuminate\Support\Facades\DB::transaction(function () use ($payment) {
            $invoice = Invoice::lockForUpdate()->find($payment->invoice_id);
            $payment->delete();
            if ($invoice) {
                $this->invoicePaymentSyncService->sync($invoice);
            }

            return $invoice;
        });

        if ($invoice) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('success', 'Pagamento eliminato correttamente.');
        }

        return redirect()
            ->route('payments.index')
            ->with('success', 'Pagamento eliminato correttamente.');
    }
}
