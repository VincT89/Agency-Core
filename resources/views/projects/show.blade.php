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
                    <button type="button" class="btn btn-g btn-danger-outline">
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

    <div class="project-progress-card u-mb-lg">
        <div class="project-progress-head">
            <div>
                <div class="project-progress-label">Progresso Task</div>
                <div class="project-progress-value {{ $progress === 100 ? 'u-text-green' : '' }}">{{ $progress }}%</div>
            </div>
            <div class="u-text-right">
                <div class="project-progress-meta">{{ $completedTasks }} di {{ $totalTasks }} task completati</div>
                @if($daysLeft !== null)
                    <div class="project-progress-due {{ $daysLeft < 0 ? 'u-text-red' : ($daysLeft <= 7 ? 'u-text-orange' : 'u-text-muted') }}">
                        {{ $daysLeft < 0 ? 'Scaduto da ' . abs($daysLeft) . ' giorni' : ($daysLeft === 0 ? 'Scade oggi' : 'Scade tra ' . $daysLeft . ' giorni') }}
                    </div>
                @endif
            </div>
        </div>
        <div class="project-progress-bar-wrap">
            <div class="project-progress-bar-inner progress-width-{{ round($progress / 5) * 5 }} {{ $progress === 100 ? 'u-bg-success' : 'u-bg-accent' }}"></div>
        </div>
    </div>

    <div class="g-2col u-mb-lg">
        <x-panel title="Info Base" dot="var(--teal)" padded>
            <div class="form-g mb-2">
                <div class="form-lbl">Cliente</div>
                <div class="u-text-strong">
                    @if($project->client)
                        <a href="{{ route('clients.show', $project->client) }}" class="u-text-accent-link">{{ $project->client->name }}</a>
                    @else
                        —
                    @endif
                </div>
            </div>
            @if($project->code)
            <div class="form-g mb-2">
                <div class="form-lbl">Codice Commessa</div>
                <div class="u-text-strong u-font-mono">{{ $project->code }}</div>
            </div>
            @endif
            <div class="form-g mb-2">
                <div class="form-lbl">Tempistiche</div>
                <div class="u-text-strong u-font-mono">
                    Inizio: {{ $project->start_date?->format('d/m/Y') ?? '—' }} <br>
                    Fine: {{ $project->end_date?->format('d/m/Y') ?? '—' }}
                </div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Creato il</div>
                <div class="u-text-strong u-font-mono">{{ $project->created_at->isoFormat('D MMMM YYYY') }}</div>
            </div>
            @if($project->description)
            <div class="form-g">
                <div class="form-lbl">Descrizione</div>
                <div class="u-text-muted">{{ $project->description }}</div>
            </div>
            @endif
        </x-panel>

        <x-panel title="Team di Commessa" dot="var(--purple)" padded>
            @forelse($project->users as $u)
                <div class="u-flex-between u-border-b u-py-sm">
                    <span class="u-text-strong">{{ $u->name }}</span>
                    <x-badge :status="$u->role->value" :label="ucfirst($u->role->value)" />
                </div>
            @empty
                <div class="u-p-md">
                    <x-empty-state message="Nessun membro del team assegnato a questa commessa." icon="users" />
                </div>
            @endforelse
        </x-panel>
    </div>

    <div class="g-2col">
        <x-panel title="Ticket Recenti">
        @if($project->tickets->isEmpty())
            <div class="u-p-md">
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
                    <tr x-data @click="window.Livewire.navigate('{{ route('tickets.show', $t) }}')" class="u-cursor-pointer hover-bg">
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
                   class="btn btn-g btn-xs">+ Task</a>
                @endcan
            </x-slot:headerActions>
        @if($project->tasks->isEmpty())
            <div class="u-p-md">
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
                    <tr x-data @click="window.Livewire.navigate('{{ route('tasks.show', $task) }}')" class="u-cursor-pointer hover-bg">
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