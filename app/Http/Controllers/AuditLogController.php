<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Shooting\Shoot;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    private const ENTITY_FILTERS = [
        'clients' => ['label' => 'Clienti', 'model' => Client::class],
        'projects' => ['label' => 'Progetti', 'model' => Project::class],
        'tickets' => ['label' => 'Ticket', 'model' => Ticket::class],
        'tasks' => ['label' => 'Task', 'model' => Task::class],
        'shootings' => ['label' => 'Shooting', 'model' => Shoot::class],
        'calendar_events' => ['label' => 'Calendario', 'model' => CalendarEvent::class],
        'invoices' => ['label' => 'Fatture', 'model' => Invoice::class],
        'payments' => ['label' => 'Pagamenti', 'model' => Payment::class],
        'users' => ['label' => 'Utenti', 'model' => User::class],
    ];

    private const ACTION_FILTERS = [
        'login' => ['label' => 'Accessi', 'actions' => ['login']],
        'logout' => ['label' => 'Uscite', 'actions' => ['logout']],
        'created' => ['label' => 'Creazioni', 'actions' => ['created']],
        'updated' => ['label' => 'Modifiche', 'actions' => ['updated']],
        'status_changed' => ['label' => 'Cambi di stato', 'actions' => ['status_changed']],
        'assignments' => ['label' => 'Assegnazioni', 'actions' => ['assigned', 'unassigned']],
        'payments' => [
            'label' => 'Operazioni sui pagamenti',
            'actions' => ['payment_registered', 'registered_payment', 'deleted_payment'],
        ],
        'attachments' => [
            'label' => 'Operazioni sugli allegati',
            'actions' => ['uploaded_attachment', 'deleted_attachment'],
        ],
        'password_reset' => ['label' => 'Reimpostazioni password', 'actions' => ['password_reset']],
        'deleted' => ['label' => 'Eliminazioni', 'actions' => ['deleted']],
    ];

    public function index(Request $request): View
    {
        Gate::authorize('system.admin');

        $filters = $request->validate([
            'auditable_type' => ['nullable', Rule::in(array_keys(self::ENTITY_FILTERS))],
            'action' => ['nullable', Rule::in(array_keys(self::ACTION_FILTERS))],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $query = AuditLog::with('user', 'auditable')->latest();

        if ($action = $filters['action'] ?? null) {
            $query->whereIn('action', self::ACTION_FILTERS[$action]['actions']);
        }

        if ($userId = $filters['user_id'] ?? null) {
            $query->where('user_id', $userId);
        }

        if ($entity = $filters['auditable_type'] ?? null) {
            $query->where('auditable_type', self::ENTITY_FILTERS[$entity]['model']);
        }

        if ($dateFrom = $filters['date_from'] ?? null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $filters['date_to'] ?? null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->paginate(25)->withQueryString();
        $users = User::query()
            ->whereIn('id', AuditLog::query()->select('user_id')->whereNotNull('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('audit-logs.index', [
            'logs' => $logs,
            'users' => $users,
            'entityFilters' => self::ENTITY_FILTERS,
            'actionFilters' => self::ACTION_FILTERS,
            'activeFiltersCount' => collect($filters)->filter(fn ($value) => filled($value))->count(),
        ]);
    }
}
