<?php

namespace App\Http\Controllers;

use App\Models\HostingService;
use App\Models\HostingServiceIntervention;
use App\Http\Requests\StoreHostingServiceInterventionRequest;

class HostingServiceInterventionController extends Controller
{
    public function store(StoreHostingServiceInterventionRequest $request, HostingService $hostingService)
    {
        $intervention = $hostingService->interventions()->create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        $hostingService->update([
            'last_intervention_at' => $intervention->intervention_date,
        ]);

        return back()->with('success', 'Intervento aggiunto con successo.');
    }

    public function destroy(HostingService $hostingService, HostingServiceIntervention $intervention)
    {
        abort_unless($intervention->hosting_service_id === $hostingService->id, 404);
        $intervention->delete();
        return back()->with('success', 'Intervento eliminato con successo.');
    }
}
