<?php

namespace App\Http\Controllers;

use App\Http\Requests\{StoreClientRequest, UpdateClientRequest};
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\Integrations\Nextcloud\NextcloudService;

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
        $data = $request->validated();
        
        $oldLogo = $client->logo_path;
        $logo = $request->file('logo');
        unset($data['logo']);

        if ($logo) {
            $data['logo_path'] = $logo->store('clients/logos', 'public');
        }

        if (array_key_exists('nextcloud_folder_name', $data) && $data['nextcloud_folder_name'] !== $client->nextcloud_folder_name) {
            if (empty($data['nextcloud_folder_name']) && !empty($client->nextcloud_folder_name)) {
                return back()->withInput()->withErrors([
                    'nextcloud_folder_name' => 'Non è possibile scollegare la cartella Nextcloud una volta impostata. Seleziona un nuovo nome o mantieni quello attuale.'
                ]);
            }

            if (!empty($data['nextcloud_folder_name'])) {
                $nextcloudService = app(NextcloudService::class);
                $root = rtrim($nextcloudService->mediaRoot('photo'), '/');
                $data['nextcloud_photos_path'] = $root . '/' . $data['nextcloud_folder_name'];

                if (!$nextcloudService->ensureDirectoryExists($data['nextcloud_photos_path'])) {
                    \Illuminate\Support\Facades\Log::warning('Unable to update client Nextcloud folder', [
                        'folder' => $data['nextcloud_folder_name'],
                        'path' => $data['nextcloud_photos_path'],
                    ]);
                    return back()->withInput()->withErrors([
                        'nextcloud_folder_name' => 'Impossibile creare la nuova cartella su Nextcloud. Verifica la connessione o prova con un altro nome.'
                    ]);
                }
            } else {
                $data['nextcloud_photos_path'] = null;
            }
        }

        $client->update($data);

        if (isset($data['logo_path']) && $oldLogo) {
            Storage::disk('public')->delete($oldLogo);
        }

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

        if (strlen($search) < 1) {
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
        $this->authorize('create', Client::class);
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
