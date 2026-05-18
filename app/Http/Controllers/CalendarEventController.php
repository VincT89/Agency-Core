<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCalendarEventRequest;
use App\Http\Requests\UpdateCalendarEventRequest;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CalendarEventController extends Controller
{
    public function index(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', CalendarEvent::class);

        $query = CalendarEvent::query()
            ->with(['client', 'project', 'creator', 'assignee']);

        // Applica il filtro per reparto se l'utente è amministratore
        if ($request->filled('department') && $request->user()->role === \App\Enums\UserRole::Admin) {
            $query->whereHas('assignee', function ($q) use ($request) {
                $q->where('role', $request->department);
            });
        }

        if ($request->query('scope') === 'personal') {
            $query->where('type', 'personal')
                ->where(function ($q) use ($request) {
                    $q->where('created_by', $request->user()->id)
                      ->orWhere('assigned_to', $request->user()->id);
                });
        }

        // Restituisci la risposta JSON formattata per FullCalendar
        if ($request->wantsJson() || $request->query('format') === 'json') {
            $events = $query
                ->when($request->start, fn($q) => $q->where('start_at', '>=', $request->start))
                ->when($request->end,   fn($q) => $q->where('start_at', '<=', $request->end))
                ->get();

            return response()->json($events->map(function($e) use ($request) {
                $isPersonal = $e->type === 'personal';
                $isOwner = $isPersonal && ($e->created_by === $request->user()->id || $e->assigned_to === $request->user()->id);

                if ($isPersonal && !$isOwner) {
                    return [
                        'id'              => $e->id,
                        'title'           => 'Occupato',
                        'start'           => $e->start_at?->toIso8601String(),
                        'end'             => $e->end_at?->toIso8601String(),
                        'allDay'          => $e->is_all_day,
                        'url'             => null,
                        'backgroundColor' => '#4e4d52',
                        'borderColor'     => 'transparent',
                        'classNames'      => ['is-locked-event'],
                        'extendedProps'   => [
                            'type'    => 'personal',
                            'status'  => 'scheduled',
                            'client'  => null,
                            'assignee'=> $e->assignee?->name,
                            'has_call'=> false,
                        ],
                    ];
                }

                return [
                    'id'              => $e->id,
                    'title'           => $e->title,
                    'start'           => $e->start_at?->toIso8601String(),
                    'end'             => $e->end_at?->toIso8601String(),
                    'allDay'          => $e->is_all_day,
                    'url'             => route('calendar-events.show', $e),
                    'backgroundColor' => match($e->type) {
                        'client_meeting'   => '#c8102e',
                        'internal_meeting' => '#5b8ef5',
                        'deadline'         => '#f54b4b',
                        'review'           => '#f5c842',
                        'delivery'         => '#3ecf8e',
                        'personal'         => '#6b7280', // grigio (bd in badge)
                        default            => '#4e4d52',
                    },
                    'borderColor'     => 'transparent',
                    'extendedProps'   => [
                        'type'    => $e->type,
                        'status'  => $e->status,
                        'client'  => $e->client?->name,
                        'assignee'=> $e->assignee?->name,
                        'has_call'=> (bool) $e->meeting_url,
                    ],
                ];
            }));
        }

        // Restituisci la vista a tabella paginata
        $calendarEvents = $query
            ->when(request('status'), fn($q, $status) => $q->where('status', $status))
            ->orderBy('start_at')
            ->paginate(15)
            ->withQueryString();

        return view('calendar-events.index', compact('calendarEvents'));
    }

    public function create(): View
    {
        $this->authorize('create', CalendarEvent::class);
        $user = auth()->user();

        $clients = Client::query()
            ->where(function ($q) use ($user) {
                if (!$user->canBypassProjectScope()) {
                    $q->whereHas('projects');
                }
            })
            ->with('projects')
            ->orderBy('name')
            ->get();

        $users = User::query()
            ->orderBy('name')
            ->get();

        return view('calendar-events.create', [
            'clients' => $clients,
            'users' => $users,
            'types' => CalendarEvent::TYPES,
            'statuses' => CalendarEvent::STATUSES,
        ]);
    }

    public function store(StoreCalendarEventRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['created_by'] = auth()->id();
        $data['is_all_day'] = $data['is_all_day'] ?? false;

        if (($data['type'] ?? '') === 'personal') {
            $data['client_id'] = null;
            $data['project_id'] = null;
            $data['assigned_to'] = auth()->id();
        }

        $calendarEvent = CalendarEvent::create($data);

        return redirect()
            ->route('calendar-events.show', $calendarEvent)
            ->with('success', 'Evento creato correttamente.');
    }

    public function show(CalendarEvent $calendarEvent): View
    {
        $this->authorize('view', $calendarEvent);
        $calendarEvent->load(['client', 'project', 'creator', 'assignee']);

        return view('calendar-events.show', compact('calendarEvent'));
    }

    public function edit(CalendarEvent $calendarEvent): View
    {
        $this->authorize('update', $calendarEvent);
        $user = auth()->user();

        $clients = Client::query()
            ->where(function ($q) use ($user) {
                if (!$user->canBypassProjectScope()) {
                    $q->whereHas('projects');
                }
            })
            ->with('projects')
            ->orderBy('name')
            ->get();

        $users = User::query()
            ->orderBy('name')
            ->get();

        return view('calendar-events.edit', [
            'calendarEvent' => $calendarEvent,
            'clients' => $clients,
            'users' => $users,
            'types' => CalendarEvent::TYPES,
            'statuses' => CalendarEvent::STATUSES,
        ]);
    }

    public function update(UpdateCalendarEventRequest $request, CalendarEvent $calendarEvent): RedirectResponse
    {
        $this->authorize('update', $calendarEvent);
        $data = $request->validated();

        $data['is_all_day'] = $data['is_all_day'] ?? false;

        if (($data['type'] ?? $calendarEvent->type) === 'personal') {
            $data['client_id'] = null;
            $data['project_id'] = null;
            $data['assigned_to'] = auth()->id();
        }

        $calendarEvent->update($data);

        return redirect()
            ->route('calendar-events.show', $calendarEvent)
            ->with('success', 'Evento aggiornato correttamente.');
    }

    public function updateDate(Request $request, CalendarEvent $calendarEvent): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $calendarEvent);
        
        $request->validate([
            'start_at' => 'required|date',
            'end_at' => 'nullable|date',
        ]);

        $calendarEvent->update([
            'start_at' => $request->start_at,
            'end_at' => $request->end_at,
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(CalendarEvent $calendarEvent): RedirectResponse
    {
        $this->authorize('delete', $calendarEvent);
        $calendarEvent->delete();

        return redirect()
            ->route('calendar-events.index')
            ->with('success', 'Evento eliminato correttamente.');
    }
}