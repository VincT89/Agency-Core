<?php

namespace App\Http\Controllers;

use App\Models\HostingService;
use App\Http\Requests\StoreHostingServiceRequest;
use App\Http\Requests\UpdateHostingServiceRequest;
use Illuminate\Http\Request;

class HostingServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = HostingService::query()
            ->with('client')
            ->latest();

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        } elseif ($request->get('exclude_type') === 'domain') {
            $query->where('type', '!=', 'domain');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%")
                    ->orWhere('provider', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        $services = $query->paginate(20)->withQueryString();

        return view('hosting-services.index', compact('services'));
    }

    public function create()
    {
        return view('hosting-services.create');
    }

    public function store(StoreHostingServiceRequest $request)
    {
        $hostingService = HostingService::create($request->validated());
        return redirect()->route('hosting-services.index', ['type' => $request->type === 'domain' ? 'domain' : null, 'exclude_type' => $request->type !== 'domain' ? 'domain' : null])
                         ->with('success', 'Servizio creato con successo.');
    }

    public function show(HostingService $hostingService)
    {
        $hostingService->load(['client', 'interventions.user']);
        return view('hosting-services.show', compact('hostingService'));
    }

    public function edit(HostingService $hostingService)
    {
        return view('hosting-services.edit', compact('hostingService'));
    }

    public function update(UpdateHostingServiceRequest $request, HostingService $hostingService)
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $hostingService->update($data);

        return redirect()->route('hosting-services.index', ['type' => $hostingService->type === 'domain' ? 'domain' : null, 'exclude_type' => $hostingService->type !== 'domain' ? 'domain' : null])
                         ->with('success', 'Servizio aggiornato con successo.');
    }

    public function destroy(HostingService $hostingService)
    {
        $type = $hostingService->type;
        $hostingService->delete();

        return redirect()->route('hosting-services.index', ['type' => $type === 'domain' ? 'domain' : null, 'exclude_type' => $type !== 'domain' ? 'domain' : null])
                         ->with('success', 'Servizio eliminato con successo.');
    }
}
