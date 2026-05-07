<x-app-layout title="Ticket">
    <x-page-header
        eyebrow="Modulo · Operativo"
        
        :meta="$tickets->total() . ' totali'"
    >
    <x-slot:title><strong>Ticket</strong> & Richieste</x-slot:title>
        <div class="u-mt-sm u-text-muted">Modulo dedicato esclusivamente a query di assistenza, bug report e istanze aperte per i Clienti.<br>Per la semplice pianificazione di lavoro interno, usa invece i <a href="{{ route('tasks.index') }}" class="u-text-accent-link">Task</a>.</div>
        <x-slot:actions>
            @can('create', App\Models\Ticket::class)
                <a href="{{ route('tickets.create') }}" class="btn btn-p">+ Apri ticket</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="filter-bar">
        @php $currentStatus = request('status'); @endphp
        <div class="pills u-m-0">
            <a href="{{ route('tickets.index', array_filter(['search' => request('search')])) }}" class="pill {{ !$currentStatus ? 'on' : '' }}">Tutti</a>
            <a href="{{ route('tickets.index', array_filter(['status' => 'open', 'search' => request('search')])) }}" class="pill {{ $currentStatus === 'open' ? 'on' : '' }}">Aperti</a>
            <a href="{{ route('tickets.index', array_filter(['status' => 'in_progress', 'search' => request('search')])) }}" class="pill {{ $currentStatus === 'in_progress' ? 'on' : '' }}">In carico</a>
            <a href="{{ route('tickets.index', array_filter(['status' => 'waiting', 'search' => request('search')])) }}" class="pill {{ $currentStatus === 'waiting' ? 'on' : '' }}">In attesa</a>
            <a href="{{ route('tickets.index', array_filter(['status' => 'resolved', 'search' => request('search')])) }}" class="pill {{ $currentStatus === 'resolved' ? 'on' : '' }}">Risolti</a>
            <a href="{{ route('tickets.index', array_filter(['status' => 'closed', 'search' => request('search')])) }}" class="pill {{ $currentStatus === 'closed' ? 'on' : '' }}">Chiusi</a>
        </div>
        <form method="GET" action="{{ route('tickets.index') }}" class="u-flex u-gap-sm filter-form u-ml-auto">
            @if($currentStatus)
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cerca ticket, cliente..." class="form-in form-in-sm filter-search">
            @if(request('search') || $currentStatus)
                <a href="{{ route('tickets.index') }}" class="btn btn-g btn-sm">Reset</a>
            @endif
        </form>
    </div>

    <x-panel>
        <table class="t-table">
            <thead>
                <tr>
                    <th class="w-120">Codice</th>
                    <th>Oggetto</th>
                    <th>Priorità</th>
                    <th>Assegnato</th>
                    <th>Cliente</th>
                    <th>Stato</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $ticket)
                <tr data-href="{{ route('tickets.show', $ticket) }}" class="js-clickable-row u-cursor-pointer hover-bg">
                    <td class="mono-col">{{ $ticket->code }}</td>
                    <td>
                        <div class="name-col {{ in_array($ticket->status, ['resolved', 'closed']) ? 'u-text-strike' : '' }}">{{ $ticket->title }}</div>
                        <div class="u-text-meta u-mt-xs">
                            {{ $ticket->type_label }}
                            @if($ticket->due_date)
                                · <span class="{{ $ticket->due_date->isPast() && !in_array($ticket->status, ['resolved', 'closed']) ? 'u-text-red' : '' }}">Scadenza: {{ $ticket->due_date->format('d/m/Y') }}</span>
                            @endif
                        </div>
                    </td>
                    <td><x-badge :status="$ticket->priority" :label="$ticket->priority_label" /></td>
                    <td>{{ $ticket->assignee?->name ?? '—' }}</td>
                    <td>
                        <span class="u-text-label u-text-normal-case">{{ $ticket->client?->name ?? '—' }}</span>
                        @if($ticket->project)
                            <div class="u-text-meta">{{ $ticket->project->name }}</div>
                        @endif
                    </td>
                    <td><x-badge :status="$ticket->status" :label="$ticket->status_label" /></td>
                    <td>
                        @can('update', $ticket)
                            <a href="{{ route('tickets.edit', $ticket) }}" class="btn-icon js-stop-propagation">✎</a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="u-empty-state">Nessun ticket trovato</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $tickets->links() }}
    </x-panel>
</x-app-layout>
