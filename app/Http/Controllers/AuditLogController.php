<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        // Solo Admin
        Gate::authorize('system.admin');

        $query = AuditLog::with('user', 'auditable')->latest();

        // Filtri opzionali (basic)
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', 'App\\Models\\' . $request->auditable_type);
        }

        $logs = $query->paginate(25);

        return view('audit-logs.index', compact('logs'));
    }
}
