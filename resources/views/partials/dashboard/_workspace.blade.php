<div class="dash-grid">
    <div>
        @if($overdueTasks->isNotEmpty())
        <x-panel title="Task Scaduti" dot="var(--red)">
            <table class="t-table">
                <thead><tr><th>Task</th><th>Progetto</th><th>Scadenza</th><th>Stato</th></tr></thead>
                <tbody>
                    @foreach($overdueTasks as $task)
                    <tr onclick="window.location='{{ route('tasks.show', $task) }}'" style="cursor:pointer">
                        <td class="name-col" style="color:var(--red);font-weight:600">{{ $task->title }}</td>
                        <td>{{ $task->project?->name ?? '—' }}</td>
                        <td class="mono-col" style="color:var(--red)">{{ $task->due_date ? $task->due_date->format('d/m/Y') : '—' }}</td>
                        <td><x-badge :status="$task->status" :label="$task->status_label" /></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </x-panel>
        <div class="mt-panel"></div>
        @endif

        @if($dueSoonTasks->isNotEmpty())
        <x-panel title="Task in Scadenza a Breve" dot="var(--orange)">
            <table class="t-table">
                <thead><tr><th>Task</th><th>Progetto</th><th>Scadenza</th><th>Stato</th></tr></thead>
                <tbody>
                    @foreach($dueSoonTasks as $task)
                    <tr onclick="window.location='{{ route('tasks.show', $task) }}'" style="cursor:pointer">
                        <td class="name-col">{{ $task->title }}</td>
                        <td>{{ $task->project?->name ?? '—' }}</td>
                        <td class="mono-col" style="color:var(--orange)">{{ $task->due_date ? $task->due_date->format('d/m/Y') : '—' }}</td>
                        <td><x-badge :status="$task->status" :label="$task->status_label" /></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </x-panel>
        <div class="mt-panel"></div>
        @endif

        <x-panel title="Altri Task in Lavorazione" dot="var(--line2)">
            <table class="t-table">
                <thead><tr><th>Task</th><th>Progetto</th><th>Scadenza</th><th>Stato</th></tr></thead>
                <tbody>
                    @forelse($otherTasks as $task)
                    <tr onclick="window.location='{{ route('tasks.show', $task) }}'" style="cursor:pointer">
                        <td class="name-col">{{ $task->title }}</td>
                        <td>{{ $task->project?->name ?? '—' }}</td>
                        <td class="mono-col">{{ $task->due_date ? $task->due_date->format('d/m/Y') : '—' }}</td>
                        <td><x-badge :status="$task->status" :label="$task->status_label" /></td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="text-align:center;color:var(--text3);padding:16px">Nessun task attivo. Ottimo lavoro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>

        @if(!auth()->user()->isMarketing())
        <div class="mt-panel">
            <x-panel title="Ticket nel Mio Perimetro" dot="var(--blue)">
                <table class="t-table">
                    <thead><tr><th>Ticket</th><th>Progetto</th><th>Stato</th></tr></thead>
                    <tbody>
                        @forelse($openTickets as $ticket)
                        <tr onclick="window.location='{{ route('tickets.show', $ticket) }}'" style="cursor:pointer">
                            <td class="name-col">{{ $ticket->title }}</td>
                            <td>{{ $ticket->project?->name ?? '—' }}</td>
                            <td><x-badge :status="$ticket->status" :label="$ticket->status_label" /></td>
                        </tr>
                        @empty
                        <tr><td colspan="3" style="text-align:center;color:var(--text3);padding:16px">Nessun ticket in corso nel tuo perimetro</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-panel>
        </div>
        @endif
    </div>

    <div>
        <x-panel title="Prossimi Eventi" dot="var(--orange)" padded>
            @forelse($upcomingEvents as $event)
                <div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--line)">
                    <div style="font-weight:500;color:var(--text)">{{ $event->title }}</div>
                    <div style="font-size:12px;color:var(--text3);margin-top:4px">{{ $event->start_at->format('d M Y H:i') }} - {{ $event->end_at->format('H:i') }}</div>
                </div>
            @empty
                <div style="color:var(--text3);text-align:center;padding:16px">Nessun evento a calendario.</div>
            @endforelse
        </x-panel>

        <div class="mt-panel">
            <x-panel title="I Miei Allegati Recenti" dot="var(--green)" padded>
                @forelse($recentAttachments as $att)
                    <div style="margin-bottom:12px;display:flex;align-items:center;gap:10px">
                        <i data-lucide="paperclip" style="width:14px;color:var(--text3)"></i>
                        <a href="{{ route('attachments.download', $att) }}" style="font-size:13px;color:var(--text2);text-decoration:none">{{ $att->original_name }}</a>
                    </div>
                @empty
                    <div style="color:var(--text3);text-align:center;padding:16px">Nessun file caricato di recente.</div>
                @endforelse
            </x-panel>
        </div>
        
        @if(auth()->user()->isMarketing())
        <div class="mt-panel">
            <x-panel title="Le Mie Richieste Shooting" dot="var(--purple)">
                <table class="t-table">
                    <thead><tr><th>Titolo</th><th>Progetto</th><th>Stato</th></tr></thead>
                    <tbody>
                        @forelse($recentShoots as $shoot)
                        <tr onclick="window.location='{{ route('social.shooting.index') }}'" style="cursor:pointer">
                            <td class="name-col">{{ $shoot->title }}</td>
                            <td>{{ $shoot->project?->name ?? '—' }}</td>
                            <td><x-badge :status="$shoot->status->value" :label="$shoot->status->labelForContext('social')" /></td>
                        </tr>
                        @empty
                        <tr><td colspan="3" style="text-align:center;color:var(--text3);padding:16px">Nessuna richiesta shooting effettuata</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-panel>
        </div>
        @endif
    </div>
</div>
