<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Actions\AllocateCreditNote;
use App\Domain\Finance\Services\CreditNoteSuggestions;
use App\Domain\Finance\Services\InvoicePartyIdentity;
use App\Models\CreditNote;
use App\Models\CreditNoteAllocation;
use App\Models\Invoice;
use Illuminate\Http\Request;

class CreditNoteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', CreditNote::class);
        $filters = $request->validate(['search' => 'nullable|string|max:100']);
        $notes = CreditNote::with(['client', 'allocations'])->latest('issue_date');
        if (filled($filters['search'] ?? '')) {
            $search = '%'.$filters['search'].'%';
            $notes->where(fn ($query) => $query->where('number', 'like', $search)
                ->orWhereHas('client', fn ($clients) => $clients->where('name', 'like', $search)->orWhere('company_name', 'like', $search)));
        }

        return view('credit-notes.index', ['notes' => $notes->paginate(15)->withQueryString()]);
    }

    public function show(CreditNote $creditNote, InvoicePartyIdentity $identity, CreditNoteSuggestions $suggestions)
    {
        $this->authorize('view', $creditNote);
        $creditNote->load(['client', 'allocations.invoice', 'attachments']);
        $invoices = Invoice::where('client_id', $creditNote->client_id)->where('currency', $creditNote->currency)
            ->whereNotIn('status', ['draft', 'cancelled'])->with('client')->withSum('creditAllocations', 'amount')->latest('issue_date')->get()
            ->filter(fn ($invoice) => $identity->compatible($creditNote, $invoice));
        $customerIdentity = $identity->customer($creditNote);
        $references = $suggestions->references($creditNote, $invoices);

        return view('credit-notes.show', compact('creditNote', 'invoices', 'references', 'customerIdentity'));
    }

    public function allocate(Request $request, CreditNote $creditNote, AllocateCreditNote $action)
    {
        $this->authorize('allocate', $creditNote);
        $data = $request->validate(['invoice_id' => 'required|integer|exists:invoices,id', 'amount' => 'required|numeric|decimal:0,2|gt:0|max:99999999.99']);
        $action->execute($creditNote, (int) $data['invoice_id'], (string) $data['amount']);

        return back()->with('success', 'Fattura collegata alla nota di credito. Gli incassi registrati restano invariati.');
    }

    public function remove(CreditNote $creditNote, CreditNoteAllocation $allocation, AllocateCreditNote $action)
    {
        abort_unless($allocation->credit_note_id === $creditNote->id, 404);
        $this->authorize('allocate', $creditNote);
        $action->remove($allocation);

        return back()->with('success', 'Collegamento rimosso. La fattura e la nota di credito restano conservate.');
    }
}
