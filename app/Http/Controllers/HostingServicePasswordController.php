<?php

namespace App\Http\Controllers;

use App\Models\HostingService;

class HostingServicePasswordController extends Controller
{
    public function show(HostingService $hostingService)
    {
        \Illuminate\Support\Facades\Gate::authorize('viewPassword', $hostingService);

        return response()
            ->json(['password' => $hostingService->password])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
