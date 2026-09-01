<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHostingServiceRequest;
use App\Http\Requests\UpdateHostingServiceRequest;
use App\Models\HostingService;
use Illuminate\Http\Request;

class HostingServiceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', HostingService::class);

        $query = HostingService::query()
            ->with('client')
            ->orderByRaw('renewal_date IS NULL, renewal_date ASC');

        if ($request->filled('type') && $request->type !== 'all') {
            $query->withServiceType($request->type);
        } elseif ($request->get('exclude_type') === 'domain') {
            $query->withAnyServiceTypeExcept('domain');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $canManageCredentials = $request->user()->can('manageCredentials', HostingService::class);
            $query->where(function ($q) use ($search, $canManageCredentials) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%")
                    ->orWhere('provider', 'like', "%{$search}%");

                if ($canManageCredentials) {
                    $q->orWhere('username', 'like', "%{$search}%");
                }

                $q->orWhereHas('client', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status_filter')) {
            $now = now()->startOfDay();
            if ($request->status_filter === 'expired') {
                $query->where('renewal_date', '<', $now);
            } elseif ($request->status_filter === 'expiring') {
                $query->where('renewal_date', '>=', $now)
                    ->where('renewal_date', '<=', $now->copy()->addDays(30));
            } elseif ($request->status_filter === 'active') {
                $query->where(function ($q) use ($now) {
                    $q->whereNull('renewal_date')
                        ->orWhere('renewal_date', '>=', $now);
                });
            }
        }

        $services = $query->paginate(20)->withQueryString();

        return view('hosting-services.index', compact('services'));
    }

    public function create()
    {
        $this->authorize('create', HostingService::class);

        return view('hosting-services.create');
    }

    public function store(StoreHostingServiceRequest $request)
    {
        $data = $request->validated();
        $context = $data['context'] ?? null;
        unset($data['context']);

        $data['service_types'] = HostingService::normalizeServiceTypes($data['service_types']);
        $data['type'] = $data['service_types'][0];

        $hostingService = HostingService::create($data);

        return redirect()->route('hosting-services.index', $this->indexParameters($hostingService, $context))
            ->with('success', 'Servizio creato con successo.');
    }

    public function show(HostingService $hostingService)
    {
        $this->authorize('view', $hostingService);
        $hostingService->load(['client', 'interventions.user']);

        return view('hosting-services.show', compact('hostingService'));
    }

    public function edit(HostingService $hostingService)
    {
        $this->authorize('update', $hostingService);

        return view('hosting-services.edit', compact('hostingService'));
    }

    public function update(UpdateHostingServiceRequest $request, HostingService $hostingService)
    {
        $data = $request->validated();
        $context = $data['context'] ?? null;
        unset($data['context']);

        $data['service_types'] = HostingService::normalizeServiceTypes($data['service_types']);
        $data['type'] = $data['service_types'][0];

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $hostingService->update($data);

        return redirect()->route('hosting-services.index', $this->indexParameters($hostingService, $context))
            ->with('success', 'Servizio aggiornato con successo.');
    }

    public function destroy(HostingService $hostingService)
    {
        $this->authorize('delete', $hostingService);

        $parameters = $this->indexParameters($hostingService, request('context'));
        $hostingService->delete();

        return redirect()->route('hosting-services.index', $parameters)
            ->with('success', 'Servizio eliminato con successo.');
    }

    private function indexParameters(HostingService $hostingService, ?string $context): array
    {
        $serviceTypes = $hostingService->resolved_service_types;
        $hasDomain = in_array('domain', $serviceTypes, true);
        $hasNonDomainType = count(array_diff($serviceTypes, ['domain'])) > 0;

        if ($context === 'domain' && $hasDomain) {
            return ['type' => 'domain'];
        }

        if ($context === 'hosting' && $hasNonDomainType) {
            return ['exclude_type' => 'domain'];
        }

        return $hasDomain && ! $hasNonDomainType
            ? ['type' => 'domain']
            : ['exclude_type' => 'domain'];
    }
}
