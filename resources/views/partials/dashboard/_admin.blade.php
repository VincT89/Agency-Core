<div class="kpi-strip">
    <div class="kpi-cell">
        <div class="kpi-label-t">Clienti Attivi</div>
        <div class="kpi-val-t">{{ $activeClients }}</div>
        <div class="kpi-delta-t">Nel gestionale</div>
    </div>
    <div class="kpi-cell {{ $openTicketsCount > 0 ? 'accent-line' : '' }}">
        <div class="kpi-label-t">Ticket Aperti</div>
        <div class="kpi-val-t">{{ $openTicketsCount }}</div>
        <div class="kpi-delta-t {{ $openTicketsCount > 0 ? 'down' : '' }}">Richiedono attenzione</div>
    </div>
    <div class="kpi-cell {{ $overdueInvoices > 0 ? 'accent-line' : '' }}">
        <div class="kpi-label-t">Fatture Scadute</div>
        <div class="kpi-val-t">{{ $overdueInvoices }}</div>
        <div class="kpi-delta-t {{ $overdueInvoices > 0 ? 'down' : '' }}">Da sollecitare</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-label-t">Task in Scad.</div>
        <div class="kpi-val-t">{{ $expiringTasks->count() }}</div>
        <div class="kpi-delta-t">Prossimi 7 gg</div>
    </div>
</div>

<div class="dash-grid">
    <div x-data="{ tab: 'tasks' }">
        <x-panel title="Panoramica Operativa" dot="var(--accent)">
            <x-slot:headerActions>
                <div class="tab-switcher" style="margin-bottom:0">
                    <button @click="tab = 'tasks'" :class="tab === 'tasks' ? 'active' : ''" class="tab-btn">Task In
                        Scadenza</button>
                    <button @click="tab = 'tickets'" :class="tab === 'tickets' ? 'active' : ''" class="tab-btn">Ticket
                        Recenti</button>
                    <button @click="tab = 'events'" :class="tab === 'events' ? 'active' : ''" class="tab-btn">Prossimi
                        Eventi</button>
                </div>
            </x-slot:headerActions>

            <div x-show="tab === 'tasks'" x-cloak>
                <table class="t-table" style="border-top:1px solid var(--line); margin-top:-1px;">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Assegnato a</th>
                            <th>Data Scadenza</th>
                            <th>Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expiringTasks as $task)
                            <tr onclick="window.location='{{ route('tasks.show', $task) }}'" style="cursor:pointer">
                                <td class="name-col">{{ $task->title }}</td>
                                <td>{{ $task->assignee?->name ?? '—' }}</td>
                                <td class="mono-col"
                                    style="{{ $task->due_date && $task->due_date < now() ? 'color:var(--red)' : '' }}">
                                    {{ $task->due_date ? $task->due_date->format('d/m/Y') : '—' }}</td>
                                <td><x-badge :status="$task->status" :label="$task->status_label" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align:center;color:var(--text3);padding:32px">Nessun task in
                                    scadenza nei prossimi 7 giorni.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div x-show="tab === 'tickets'" x-cloak style="display: none;">
                <table class="t-table" style="border-top:1px solid var(--line); margin-top:-1px;">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Commessa</th>
                            <th>Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTickets as $ticket)
                            <tr onclick="window.location='{{ route('tickets.show', $ticket) }}'" style="cursor:pointer">
                                <td class="name-col">{{ $ticket->title }}</td>
                                <td>{{ $ticket->project?->name ?? '—' }}</td>
                                <td><x-badge :status="$ticket->status" :label="$ticket->status_label" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" style="text-align:center;color:var(--text3);padding:32px">Nessun ticket
                                    recente</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div x-show="tab === 'events'" x-cloak style="display: none;">
                <table class="t-table" style="border-top:1px solid var(--line); margin-top:-1px;">
                    <thead>
                        <tr>
                            <th>Evento</th>
                            <th>Data</th>
                            <th>Luogo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($upcomingEvents as $event)
                            <tr onclick="window.location='{{ route('calendar-events.show', $event) }}'"
                                style="cursor:pointer">
                                <td class="name-col">{{ $event->title }}</td>
                                <td class="mono-col">{{ $event->start_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $event->location ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" style="text-align:center;color:var(--text3);padding:32px">Nessun evento in
                                    programma.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>

        {{-- Appuntamenti della Settimana --}}
        <div class="mt-panel" style="margin-bottom:20px;">
            <x-panel title="Appuntamenti della Settimana" dot="var(--blue)">
                @if($weeklyEvents->isEmpty())
                    <div style="text-align:center;color:var(--text3);padding:24px 16px;">Nessun appuntamento nei prossimi 7
                        giorni.</div>
                @else
                    <table class="t-table">
                        <thead>
                            <tr>
                                <th>Evento</th>
                                <th>Data e Ora</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($weeklyEvents as $event)
                                <tr onclick="window.location='{{ route('calendar-events.show', $event) }}'"
                                    style="cursor:pointer">
                                    <td class="name-col">
                                        {{ $event->title }}
                                        @if($event->meeting_url)
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:6px; color:var(--accent); vertical-align:middle"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>
                                        @endif
                                    </td>
                                    <td class="mono-col">{{ $event->start_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-panel>
        </div>

        <div class="mt-panel">
            {{-- Shooting Overview --}}
            @livewire('admin.dashboard.shooting-overview')
        </div>

    </div>

    <div>
        <x-panel title="Attività Recenti" dot="var(--purple)" padded>
            @forelse($recentActivity as $log)
                <x-audit-item :log="$log" />
            @empty
                <div style="color:var(--text3);text-align:center;padding:16px">Nessuna attività registrata.</div>
            @endforelse
        </x-panel>
    </div>
</div>