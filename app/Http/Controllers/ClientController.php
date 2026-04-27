<?php

namespace App\Http\Controllers;

use App\Http\Requests\{StoreClientRequest, UpdateClientRequest};
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(\Illuminate\Http\Request $request): View
    {
        $this->authorize('viewAny', Client::class);

        $query = Client::query()
            ->withCount(['projects', 'tickets', 'invoices'])
            ->orderBy('name');

        if ($search = $request->get('search')) {
            $searchStr = '%' . strtolower($search) . '%';
            $query->where(function($q) use ($searchStr) {
                $q->whereRaw('LOWER(name) LIKE ?', [$searchStr])
                  ->orWhereRaw('LOWER(email) LIKE ?', [$searchStr])
                  ->orWhereRaw('LOWER(vat_number) LIKE ?', [$searchStr]);
            });
        }

        $clients = $query->paginate(20)->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        $this->authorize('create', Client::class);
        return view('clients.create');
    }

    public function store(StoreClientRequest $request, \App\Actions\Clients\CreateClientAction $action): RedirectResponse
    {
        $client = $action->execute($request->validated());

        return redirect()->route('clients.show', $client)
            ->with('success', 'Cliente creato correttamente.');
    }

    public function show(Client $client): View
    {
        $this->authorize('view', $client);

        $client->load(['projects', 'tickets' => fn($q) => $q->latest()->limit(5),
                        'invoices' => fn($q) => $q->latest()->limit(5), 'attachments.uploader']);

        return view('clients.show', compact('client'));
    }

    public function edit(Client $client): View
    {
        $this->authorize('update', $client);
        return view('clients.edit', compact('client'));
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()->route('clients.show', $client)
            ->with('success', 'Cliente aggiornato correttamente.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);
        $client->delete();

        return redirect()->route('clients.index')
            ->with('success', 'Cliente eliminato correttamente.');
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $search = $request->get('q', '');

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $searchStr = '%' . strtolower($search) . '%';

        $clients = Client::query()
            ->where(function($q) use ($searchStr) {
                $q->whereRaw('LOWER(name) LIKE ?', [$searchStr])
                  ->orWhereRaw('LOWER(company_name) LIKE ?', [$searchStr])
                  ->orWhereRaw('LOWER(email) LIKE ?', [$searchStr])
                  ->orWhereRaw('LOWER(phone) LIKE ?', [$searchStr])
                  ->orWhereRaw('LOWER(vat_number) LIKE ?', [$searchStr]);
            })
            ->limit(10)
            ->get(['id', 'name', 'company_name', 'email', 'vat_number']);

        return response()->json($clients);
    }

    public function quickStore(StoreClientRequest $request, \App\Actions\Clients\CreateClientAction $action): JsonResponse
    {
        $client = $action->execute($request->validated());

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'company_name' => $client->company_name,
            'email' => $client->email,
            'vat_number' => $client->vat_number,
            'phone' => $client->phone,
        ], 201);
    }
}
