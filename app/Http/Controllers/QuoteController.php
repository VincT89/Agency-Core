<?php

namespace App\Http\Controllers;

use App\Domain\Quotes\ChangeQuoteStatusAction;
use App\Domain\Quotes\CreateProjectFromQuoteAction;
use App\Domain\Quotes\ReviseQuoteAction;
use App\Domain\Quotes\SaveQuoteAction;
use App\Http\Requests\SaveQuoteRequest;
use App\Http\Requests\StoreProjectFromQuoteRequest;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quote::class);
        $quotes = Quote::visibleTo($request->user())->with('client:id,name')->commercialOrder()
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->paginate(20)->withQueryString();

        return view('quotes.index', compact('quotes'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Quote::class);
        $ticket = $request->filled('ticket_id') ? Ticket::with('requestedServices')->findOrFail($request->integer('ticket_id')) : null;
        if ($ticket) {
            $this->authorize('view', $ticket);
            abort_unless($ticket->type === 'quote', 422);
        }
        $sourceQuote = null;
        if ($request->filled('from_quote_id')) {
            abort_if($ticket, 422, 'Scegli una richiesta oppure un’offerta da riutilizzare.');
            $sourceQuote = Quote::findOrFail($request->integer('from_quote_id'));
            $this->authorize('view', $sourceQuote);
            $sourceQuote->load('items');
        }
        $client = null;
        if ($ticket) {
            $client = Client::findOrFail($ticket->client_id);
        } elseif ($request->session()->hasOldInput('client_id')) {
            $clientId = filter_var($request->old('client_id'), FILTER_VALIDATE_INT);
            $client = $clientId ? Client::find($clientId) : null;
        } elseif ($request->filled('client_id')) {
            $client = Client::findOrFail($request->integer('client_id'));
        } elseif ($sourceQuote) {
            $client = $sourceQuote->client;
        }
        if ($client) {
            $this->authorize('view', $client);
        }

        return view('quotes.form', ['quote' => null, 'client' => $client, 'ticket' => $ticket, 'sourceQuote' => $sourceQuote]);
    }

    public function store(SaveQuoteRequest $request, SaveQuoteAction $action): RedirectResponse
    {
        $quote = $action->execute($request->validated());

        return redirect()->route($request->input('after_save') === 'preview' ? 'quotes.document' : 'quotes.show', $quote)->with('success', 'Bozza di offerta salvata.');
    }

    public function show(Quote $quote): View
    {
        $this->authorize('view', $quote);
        $quote->load(['items', 'client', 'ticket', 'previousQuote', 'nextQuote', 'attachments.uploader']);

        return view('quotes.show', compact('quote'));
    }

    public function edit(Quote $quote): View
    {
        $this->authorize('update', $quote);

        return view('quotes.form', ['quote' => $quote->load('items'), 'client' => $quote->client, 'ticket' => $quote->ticket]);
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        DB::transaction(function () use ($quote) {
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            $this->authorize('delete', $quote);
            $quote->delete();
        });

        return redirect()->route('quotes.index')->with('success', 'Offerta eliminata. Gli eventuali progetti collegati restano disponibili.');
    }

    public function update(SaveQuoteRequest $request, Quote $quote, SaveQuoteAction $action): RedirectResponse
    {
        $action->execute($request->validated(), $quote);

        return redirect()->route($request->input('after_save') === 'preview' ? 'quotes.document' : 'quotes.show', $quote)->with('success', 'Bozza aggiornata.');
    }

    public function present(Quote $quote, ChangeQuoteStatusAction $action): RedirectResponse
    {
        $action->execute($quote, 'presented');

        return redirect()->route('quotes.show', $quote)->with('success', 'Offerta registrata come presentata al cliente.');
    }

    public function accept(Quote $quote, ChangeQuoteStatusAction $action): RedirectResponse
    {
        $action->execute($quote, 'accepted');

        return redirect()->route('quotes.show', $quote)->with('success', 'Accettazione registrata.');
    }

    public function reject(Quote $quote, ChangeQuoteStatusAction $action): RedirectResponse
    {
        $action->execute($quote, 'rejected');

        return redirect()->route('quotes.show', $quote)->with('success', 'Rifiuto registrato.');
    }

    public function revise(Quote $quote, ReviseQuoteAction $action): RedirectResponse
    {
        return redirect()->route('quotes.edit', $action->execute($quote));
    }

    public function createProject(Quote $quote): View|RedirectResponse
    {
        $this->authorize('createProject', $quote);
        if ($quote->project_id) {
            return redirect()->route('projects.show', $quote->project_id);
        }
        $users = User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'role']);

        return view('quotes.project', compact('quote', 'users'));
    }

    public function storeProject(StoreProjectFromQuoteRequest $request, Quote $quote, CreateProjectFromQuoteAction $action): RedirectResponse
    {
        return redirect()->route('projects.show', $action->execute($quote, $request->validated()))
            ->with('success', 'Progetto collegato all’offerta accettata.');
    }
}
