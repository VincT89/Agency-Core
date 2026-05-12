<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
class TicketController extends Controller
{
    public function index(\Illuminate\Http\Request $request, \App\Domain\Core\Queries\TicketQuery $ticketQuery): View
    {
        $this->authorize('viewAny', Ticket::class);

        $user = auth()->user();
        $user->update(['last_tickets_viewed_at' => now()]);
        \Illuminate\Support\Facades\Cache::forget('sidebar_counts_' . $user->id);
        
        $tickets = $ticketQuery->forIndex($request->all())->paginate(15)->withQueryString();

        return view('tickets.index', compact('tickets'));
    }

    public function create(): View
    {
        $this->authorize('create', Ticket::class);
        $clients = Client::query()
            ->where(function ($q) {
                if (!auth()->user()->canBypassProjectScope()) {
                    $q->whereHas('projects');
                }
            })
            ->orderBy('name')
            ->get();

        $users = User::query()
            ->orderBy('name')
            ->get();

        return view('tickets.create', [
            'clients' => $clients,
            'users' => $users,
            'types' => Ticket::TYPES,
            'statuses' => Ticket::STATUSES,
            'priorities' => Ticket::PRIORITIES,
        ]);
    }

    public function store(StoreTicketRequest $request, \App\Domain\Core\Actions\CreateTicketAction $action): RedirectResponse
    {
        $ticket = $action->execute($request->validated());

        return redirect()
            ->route('tickets.index')
            ->with('success', 'Ticket creato correttamente.');
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);
        $ticket->load([
            'client', 
            'project', 
            'creator', 
            'assignee', 
            'attachments.uploader', 
            'auditLogs.user',
            'tasks.project',
            'tasks.assignee',
            'comments.user',
            'checklistItems.completedBy',
        ]);

        return view('tickets.show', compact('ticket'));
    }

    public function edit(Ticket $ticket): View
    {
        $this->authorize('update', $ticket);
        $clients = Client::query()
            ->where(function ($q) {
                if (!auth()->user()->canBypassProjectScope()) {
                    $q->whereHas('projects');
                }
            })
            ->orderBy('name')
            ->get();

        $users = User::query()
            ->orderBy('name')
            ->get();

        return view('tickets.edit', [
            'ticket' => $ticket->load(['client', 'project']),
            'clients' => $clients,
            'users' => $users,
            'types' => Ticket::TYPES,
            'statuses' => Ticket::STATUSES,
            'priorities' => Ticket::PRIORITIES,
        ]);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);
        $data = $request->validated();

        if (($data['status'] ?? null) === 'closed') {
            $data['closed_at'] = $ticket->closed_at ?? now();
        } else {
            $data['closed_at'] = null;
        }

        if (empty($ticket->opened_at) && empty($data['opened_at'])) {
            $data['opened_at'] = now();
        }

        $oldAssignedTo = $ticket->assigned_to;
        $ticket->update($data);

        if (
            !empty($data['assigned_to']) &&
            (int) $data['assigned_to'] !== (int) $oldAssignedTo
        ) {
            event(new \App\Domain\Core\Events\TicketAssigned($ticket));
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Ticket aggiornato correttamente.');
    }

    public function updateStatus(\Illuminate\Http\Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'status' => ['required', 'string', \Illuminate\Validation\Rule::in(Ticket::STATUSES)],
        ]);

        if ($validated['status'] === 'closed') {
            $validated['closed_at'] = $ticket->closed_at ?? now();
        } else {
            $validated['closed_at'] = null;
        }

        $ticket->update($validated);

        return redirect()->back()->with('success', 'Stato aggiornato.');
    }

    public function destroy(Ticket $ticket): RedirectResponse
    {
        $this->authorize('delete', $ticket);
        $ticket->delete();

        return redirect()
            ->route('tickets.index')
            ->with('success', 'Ticket eliminato correttamente.');
    }
}