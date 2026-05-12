<div class="dash-grid">
    <div>
        @if($overdueTasks->isNotEmpty())
        <x-panel title="Task Scaduti" dot="var(--red)">
            <table class="t-table">
                <thead><tr><th>Task</th><th>Commessa</th><th>Scadenza</th><th>Stato</th></tr></thead>
                <tbody>
                    @foreach($overdueTasks as $task)
                    <tr x-data @click="window.Livewire.navigate('{{ route('tasks.show', $task) }}')" class="u-cursor-pointer hover-bg">
                        <td class="name-col u-text-danger u-text-strong">{{ $task->title }}</td>
                        <td>{{ $task->project?->name ?? '—' }}</td>
                        <td class="mono-col u-text-danger">{{ $task->due_date ? $task->due_date->format('d/m/Y') : '—' }}</td>
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
                <thead><tr><th>Task</th><th>Commessa</th><th>Scadenza</th><th>Stato</th></tr></thead>
                <tbody>
                    @foreach($dueSoonTasks as $task)
                    <tr x-data @click="window.Livewire.navigate('{{ route('tasks.show', $task) }}')" class="u-cursor-pointer hover-bg">
                        <td class="name-col">{{ $task->title }}</td>
                        <td>{{ $task->project?->name ?? '—' }}</td>
                        <td class="mono-col u-text-warning">{{ $task->due_date ? $task->due_date->format('d/m/Y') : '—' }}</td>
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
                <thead><tr><th>Task</th><th>Commessa</th><th>Scadenza</th><th>Stato</th></tr></thead>
                <tbody>
                    @forelse($otherTasks as $task)
                    <tr x-data @click="window.Livewire.navigate('{{ route('tasks.show', $task) }}')" class="u-cursor-pointer hover-bg">
                        <td class="name-col">{{ $task->title }}</td>
                        <td>{{ $task->project?->name ?? '—' }}</td>
                        <td class="mono-col">{{ $task->due_date ? $task->due_date->format('d/m/Y') : '—' }}</td>
                        <td><x-badge :status="$task->status" :label="$task->status_label" /></td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="u-text-center u-text-muted u-p-md">Nessun task attivo. Ottimo lavoro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>

        @if(!auth()->user()->isMarketing())
        <div class="mt-panel">
            <x-panel title="Ticket nel Mio Perimetro" dot="var(--blue)">
                <table class="t-table">
                    <thead><tr><th>Ticket</th><th>Commessa</th><th>Stato</th></tr></thead>
                    <tbody>
                        @forelse($openTickets as $ticket)
                        <tr x-data @click="window.Livewire.navigate('{{ route('tickets.show', $ticket) }}')" class="u-cursor-pointer hover-bg">
                            <td class="name-col">{{ $ticket->title }}</td>
                            <td>{{ $ticket->project?->name ?? '—' }}</td>
                            <td><x-badge :status="$ticket->status" :label="$ticket->status_label" /></td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="u-text-center u-text-muted u-p-md">Nessun ticket in corso nel tuo perimetro</td></tr>
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
                <div class="u-mb-sm u-pb-sm u-border-b">
                    <div class="u-text-strong u-text-base">{{ $event->title }}</div>
                    <div class="u-text-sm u-text-muted u-mt-xs">{{ $event->start_at->format('d M Y H:i') }} - {{ $event->end_at->format('H:i') }}</div>
                </div>
            @empty
                <div class="u-text-center u-text-muted u-p-md">Nessun evento a calendario.</div>
            @endforelse
        </x-panel>

        <div class="mt-panel">
            <x-panel title="I Miei Allegati Recenti" dot="var(--green)" padded>
                @forelse($recentAttachments as $att)
                    <div class="u-mb-sm u-flex u-items-center u-gap-xs">
                        <i data-lucide="paperclip" class="u-icon-xs u-text-muted"></i>
                        <a href="{{ route('attachments.download', $att) }}" class="u-text-sm u-text-secondary u-no-underline">{{ $att->original_name }}</a>
                    </div>
                @empty
                    <div class="u-text-center u-text-muted u-p-md">Nessun file caricato di recente.</div>
                @endforelse
            </x-panel>
        </div>
        
        @if(auth()->user()->isMarketing())
        <div class="mt-panel">
            <x-panel title="Le Mie Richieste Shooting" dot="var(--purple)">
                <table class="t-table">
                    <thead><tr><th>Titolo</th><th>Commessa</th><th>Stato</th></tr></thead>
                    <tbody>
                        @forelse($recentShoots as $shoot)
                        <tr x-data @click="window.Livewire.navigate('{{ route('social.shooting.index') }}')" class="u-cursor-pointer hover-bg">
                            <td class="name-col">{{ $shoot->title }}</td>
                            <td>{{ $shoot->project?->name ?? '—' }}</td>
                            <td><x-badge :status="$shoot->status->value" :label="$shoot->status->labelForContext('social')" /></td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="u-text-center u-text-muted u-p-md">Nessuna richiesta shooting effettuata</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-panel>
        </div>
        @endif
    </div>
</div>
