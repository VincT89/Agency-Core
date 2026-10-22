<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\HostingService;
use App\Models\MarketingCampaign;
use App\Models\Project;
use Illuminate\Http\Request;

class FinanceLookupController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()->canAccessFinance(), 403);
        $data = $request->validate([
            'type' => 'required|in:clients,projects,campaigns,services', 'q' => 'nullable|string|max:100',
            'client_id' => 'nullable|integer|exists:clients,id',
            'service_type' => 'nullable|in:domain,hosting',
        ]);
        $model = match ($data['type']) { 'clients' => Client::class, 'projects' => Project::class, 'campaigns' => MarketingCampaign::class, 'services' => HostingService::class };
        $query = $model::query();
        if (in_array($data['type'], ['services', 'campaigns'], true)) {
            $query->where('client_id', $data['client_id'] ?? 0);
            $query->when($data['type'] === 'services' && filled($data['service_type'] ?? null), fn ($q) => $q->withServiceType($data['service_type']));
        } elseif ($data['type'] === 'projects' && filled($data['client_id'] ?? null)) {
            $query->where(fn ($q) => $q->where('client_id', $data['client_id'])->orWhere('system_key', Project::INVOICE_CONTAINER_KEY));
        }
        $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($data['q'] ?? '')).'%';
        $query->where(fn ($q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$search])
            ->when($data['type'] === 'services', fn ($q) => $q->orWhereRaw("LOWER(domain) LIKE ? ESCAPE '!'", [$search])));
        /** Only labels and identifiers leave this endpoint; hosting credentials are never serialized. */
        return response()->json($query->orderBy('name')->limit(30)->get(['id', 'name']));
    }
}
