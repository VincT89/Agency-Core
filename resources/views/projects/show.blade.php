<x-app-layout title="{{ $project->name }}">
    <div class="u-mb-lg u-flex-end">
        <a href="{{ route('projects.index') }}" wire:navigate class="btn btn-g u-flex-center u-gap-xs">
            <i data-lucide="arrow-left" class="u-icon-sm"></i> Torna alla lista
        </a>
    </div>
    <x-page-header
        eyebrow="Dettaglio · Commessa"
        
    >
    <x-slot:title><strong>{{ $project->name }}</strong></x-slot:title>
        <x-slot:actions>
            <x-badge :status="$project->status" :label="$project->status_label" />
            @can('update', $project)
                <a href="{{ route('projects.edit', $project) }}" class="btn btn-g">Modifica</a>
            @endcan
        
            @can('delete', $project)
                <x-delete-modal 
                    action="{{ route('projects.destroy', $project) }}" 
                    title="Elimina Commessa" 
                    message="Sei sicuro di voler eliminare la commessa '{{ $project->name }}'? Questa azione non può essere annullata."
                    confirmText="{{ $project->name }}">
                    <button type="button" class="btn btn-g" style="color:var(--red);border-color:rgba(245,75,75,.3)">
                        Elimina
                    </button>
                </x-delete-modal>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @php
        $totalTasks = $project->total_tasks_count ?? 0;
        $completedTasks = $project->completed_tasks_count ?? 0;
        $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
        
        $daysLeft = null;
        if ($project->end_date && !in_array($project->status, ['completed', 'cancelled'])) {
            $daysLeft = (int) now()->startOfDay()->diffInDays($project->end_date->startOfDay(), false);
        }
    @endphp

    <div style="margin-bottom:20px;padding:20px;background:var(--bg2);border:1px solid var(--line2);border-radius:var(--r)">
        <div style="display:flex;justify-content:space-between;margin-bottom:12px;align-items:flex-end">
            <div>
                <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px">Progresso Task</div>
                <div style="font-size:24px;font-weight:600;font-family:var(--sans);line-height:1;color:{{ $progress === 100 ? 'var(--green)' : 'var(--text)' }}">{{ $progress }}%</div>
            </div>
            <div style="text-align:right">
                <div style="font-size:12px;color:var(--text)">{{ $completedTasks }} di {{ $totalTasks }} task completati</div>
                @if($daysLeft !== null)
                    <div style="font-size:11px;color:{{ $daysLeft < 0 ? 'var(--red)' : ($daysLeft <= 7 ? 'var(--yellow)' : 'var(--text3)') }};margin-top:4px">
                        {{ $daysLeft < 0 ? 'Scaduto da ' . abs($daysLeft) . ' giorni' : ($daysLeft === 0 ? 'Scade oggi' : 'Scade tra ' . $daysLeft . ' giorni') }}
                    </div>
                @endif
            </div>
        </div>
        <div style="width:100%;background:var(--bg3);height:8px;border-radius:4px;overflow:hidden">
            <div style="height:100%;background:{{ $progress === 100 ? 'var(--green)' : 'var(--accent)' }};width:{{ $progress }}%;transition:width 0.6s ease"></div>
        </div>
    </div>

    <div class="g-2col" style="margin-bottom:20px;">
        <x-panel title="Info Base" dot="var(--teal)" padded>
            <div class="form-g mb-2">
                <div class="form-lbl">Cliente</div>
                <div style="color:var(--text);font-family:var(--sans)">
                    @if($project->client)
                        <a href="{{ route('clients.show', $project->client) }}" style="color:var(--accent);text-decoration:none">{{ $project->client->name }}</a>
                    @else
                        —
                    @endif
                </div>
            </div>
            @if($project->code)
            <div class="form-g mb-2">
                <div class="form-lbl">Codice Commessa</div>
                <div style="color:var(--text);font-family:var(--mono)">{{ $project->code }}</div>
            </div>
            @endif
            <div class="form-g mb-2">
                <div class="form-lbl">Tempistiche</div>
                <div style="color:var(--text);font-family:var(--mono)">
                    Inizio: {{ $project->start_date?->format('d/m/Y') ?? '—' }} <br>
                    Fine: {{ $project->end_date?->format('d/m/Y') ?? '—' }}
                </div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Creato il</div>
                <div style="color:var(--text);font-family:var(--mono)">{{ $project->created_at->isoFormat('D MMMM YYYY') }}</div>
            </div>
            @if($project->description)
            <div class="form-g">
                <div class="form-lbl">Descrizione</div>
                <div style="color:var(--text3);font-size:13px">{{ $project->description }}</div>
            </div>
            @endif
        </x-panel>

        <x-panel title="Team di Commessa" dot="var(--purple)" padded>
            @forelse($project->users as $u)
                <div style="padding:8px 0;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;">
                    <span style="color:var(--text)">{{ $u->name }}</span>
                    <x-badge :status="$u->role->value" :label="ucfirst($u->role->value)" />
                </div>
            @empty
                <div style="padding:16px;">
                    <x-empty-state message="Nessun membro del team assegnato a questa commessa." icon="users" />
                </div>
            @endforelse
        </x-panel>
    </div>

    <div class="g-2col">
        <x-panel title="Ticket Recenti">
        @if($project->tickets->isEmpty())
            <div style="padding:16px;">
                <x-empty-state message="Nessun ticket registrato per questa commessa." icon="ticket" />
            </div>
        @else
            <table class="t-table">
                <thead>
                    <tr>
                        <th>Oggetto</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($project->tickets as $t)
                    <tr onclick="window.location='{{ route('tickets.show', $t) }}'" style="cursor:pointer">
                        <td class="name-col">{{ $t->title }}</td>
                        <td><x-badge :status="$t->status" :label="$t->status_label" /></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        </x-panel>

        <x-panel title="Task Recenti">
            <x-slot:headerActions>
                @can('create', App\Models\Task::class)
                <a href="{{ route('tasks.create', ['project_id' => $project->id]) }}"
                   class="btn btn-g" style="font-size:10px;padding:4px 10px">+ Task</a>
                @endcan
            </x-slot:headerActions>
        @if($project->tasks->isEmpty())
            <div style="padding:16px;">
                <x-empty-state message="Nessun task per questa commessa." icon="check-square" />
            </div>
        @else
            <table class="t-table">
                <thead>
                    <tr>
                        <th>Titolo Task</th>
                        <th>Assegnato</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($project->tasks as $task)
                    <tr onclick="window.location='{{ route('tasks.show', $task) }}'" style="cursor:pointer">
                        <td class="name-col">{{ $task->title }}</td>
                        <td>{{ $task->assignee?->name ?? '—' }}</td>
                        <td><x-badge :status="$task->status" :label="$task->status_label" /></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        </x-panel>
    </div>

    
    <x-audit-timeline :logs="$project->auditLogs" />
    
    {{-- Allegati --}}
    <x-attachments-panel :model="$project" />
</x-app-layout>