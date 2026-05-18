<x-app-layout title="{{ $task->title }}">
    <div style="margin: -var(--space-md); padding: var(--space-md); border-radius: var(--radius-md);">
        <div class="u-mb-lg u-flex-end">
            <a href="{{ route('tasks.index') }}" wire:navigate class="btn btn-g u-flex-center u-gap-xs">
                <i data-lucide="arrow-left" class="u-icon-sm"></i> Torna alla lista
            </a>
        </div>
        <x-page-header
            eyebrow="Task · {{ $task->project?->name ?? '—' }}"
            
        >
        <x-slot:title><strong class="task-title">{{ $task->title }}</strong></x-slot:title>
        <x-slot:actions>
            <x-badge :status="$task->status" :label="$task->status_label" />
            <x-badge :status="$task->priority" :label="$task->priority_label" />
            @can('update', $task)
                <a href="{{ route('tasks.edit', $task) }}" class="btn btn-g">Modifica</a>
            @endcan
            @can('delete', $task)
                <x-delete-modal 
                    action="{{ route('tasks.destroy', $task) }}" 
                    title="Elimina Task" 
                    message="Sei sicuro di voler eliminare questo task? L'azione è irreversibile."
                    confirmText="elimina">
                    <button type="button" class="btn btn-g btn-danger">Elimina</button>
                </x-delete-modal>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="u-mb-lg">
        @php
            $progressMap = [
                'todo' => 15,
                'in_progress' => 50,
                'waiting' => 80,
                'done' => 100,
            ];
            $progress = $progressMap[$task->status] ?? 0;
        @endphp
        <div class="u-flex-between u-mb-xs u-align-center">
            <div class="u-text-strong u-text-sm" style="text-transform: uppercase; letter-spacing: 0.5px;">Avanzamento: {{ $task->status_label }}</div>
            <div class="u-text-strong u-text-muted u-font-mono">{{ $progress }}%</div>
        </div>
        <div class="project-progress-bar-wrap" style="height: 8px; border-radius: 4px; background: var(--line); overflow: hidden;">
            <div class="project-progress-bar-inner task-progress-{{ $task->status }}" style="width: {{ $progress }}%; height: 100%; transition: width 0.4s ease, background-color 0.4s ease;"></div>
        </div>
    </div>

    <div class="task-detail-grid">
        <div>
            <x-panel title="Descrizione" dot="var(--blue)" padded>
                @if($task->description)
                    <div class="u-text-strong task-description">{{ $task->description }}</div>
                @else
                    <div class="u-text-muted task-empty">Nessuna descrizione.</div>
                @endif
                @if($task->notes)
                    <div class="u-section-sep">
                        <div class="u-text-label">Note interne</div>
                        <div class="u-text-muted task-notes">{{ $task->notes }}</div>
                    </div>
                @endif
            </x-panel>

            {{-- Cambio rapido status --}}
            <div class="u-mt-md">
                <x-panel title="Aggiornamento rapido" dot="var(--teal)" padded>
                    <div class="u-flex u-gap-sm task-status-grid">
                        @foreach(\App\Http\Controllers\TaskController::STATUSES as $s)
                            <form action="{{ route('tasks.update-status', $task) }}" method="POST">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $s }}">
                                <button type="submit" class="btn {{ $task->status === $s ? 'btn-p' : 'btn-g' }} btn-sm">
                                    {{ (new \App\Models\Task(['status' => $s]))->status_label }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </x-panel>
            </div>

            {{-- Checklist --}}
            <div class="u-mt-md">
                <x-panel title="Checklist" dot="var(--green)" padded>
                    @php
                        $totalChecklist = $task->checklistItems->count();
                        $doneChecklist = $task->checklistItems->where('is_completed', true)->count();
                    @endphp

                    <div class="u-text-meta u-mb-md" data-checklist-counter>
                        {{ $doneChecklist }}/{{ $totalChecklist }} completati
                    </div>

                    <div class="js-checklist-container">
                    @forelse($task->checklistItems as $item)
                        @include('shared.checklist-item', ['item' => $item, 'type' => 'task'])
                    @empty
                        <div class="u-empty-state-sm js-checklist-empty">Nessuna voce checklist.</div>
                    @endforelse
                    </div>

                    @can('update', $task)
                        <form action="{{ route('tasks.checklist-items.store', $task) }}" method="POST" class="u-flex u-gap-sm u-mt-md js-checklist-store">
                            @csrf
                            <input name="title" class="form-in" placeholder="Nuova voce checklist..." required>

                            <button type="submit" class="btn btn-g">Aggiungi</button>
                        </form>
                        @error('title')
                            <div class="u-text-red u-mt-sm">{{ $message }}</div>
                        @enderror
                    @endcan
                </x-panel>
            </div>
        </div>

        <div>
            <x-panel title="Dettagli" dot="var(--yellow)" padded>
                <div class="form-g mb-3">
                    <div class="u-text-label">Progetto</div>
                    <div class="u-text-strong">
                        @if($task->project)
                            <a href="{{ route('projects.show', $task->project) }}" class="u-text-accent-link">{{ $task->project->name }}</a>
                        @else —
                        @endif
                    </div>
                </div>
                @if($task->ticket)
                <div class="form-g mb-3">
                    <div class="u-text-label">Origine</div>
                    <div class="u-text-strong">
                        <a href="{{ route('tickets.show', $task->ticket) }}" class="u-text-accent-link">Generato dal Ticket #{{ $task->ticket->id }}</a>
                    </div>
                </div>
                @endif
                @if($task->project?->client)
                <div class="form-g mb-3">
                    <div class="u-text-label">Cliente</div>
                    <div>
                        <a href="{{ route('clients.show', $task->project->client) }}" class="u-text-accent-link">{{ $task->project->client->name }}</a>
                    </div>
                </div>
                @endif
                <div class="form-g mb-3">
                    <div class="u-text-label">Assegnato a</div>
                    <div class="u-text-strong">{{ $task->assignee?->name ?? 'Non assegnato' }}</div>
                </div>
                <div class="form-g mb-3">
                    <div class="u-text-label">Creato da</div>
                    <div class="u-text-strong">{{ $task->creator?->name ?? 'Sistema' }}</div>
                </div>
                @if($task->start_date)
                <div class="form-g mb-3">
                    <div class="u-text-label">Data inizio</div>
                    <div class="u-text-mono u-color-text">{{ $task->start_date->format('d/m/Y') }}</div>
                </div>
                @endif
                <div class="form-g mb-3">
                    <div class="u-text-label">Scadenza</div>
                    <div class="u-text-mono {{ $task->due_date?->isPast() && $task->status !== 'done' ? 'u-color-red' : 'u-color-text' }}">
                        {{ $task->due_date?->format('d/m/Y') ?? '—' }}
                    </div>
                </div>
                @if($task->completed_at)
                <div class="form-g mb-3">
                    <div class="u-text-label">Completato il</div>
                    <div class="u-text-mono u-color-green">{{ $task->completed_at->format('d/m/Y H:i') }}</div>
                </div>
                @endif
                <div class="form-g mb-3">
                    <div class="u-text-label">Creato il</div>
                    <div class="u-text-mono">{{ $task->created_at->isoFormat('D MMMM YYYY') }}</div>
                </div>
            </x-panel>

            {{-- Bottone crea task da progetto --}}
            @can('create', App\Models\Task::class)
            <div class="u-mt-md">
                <a href="{{ route('tasks.create', ['project_id' => $task->project_id]) }}" class="btn btn-g u-w-full u-text-center u-flex-center u-flex-col">
                    + Nuovo task nello stesso progetto
                </a>
            </div>
            @endcan

            @if($projectTasks->isNotEmpty())
                <div class="u-mt-md">
                    <x-panel title="Ultime task del progetto" dot="var(--purple)">
                        <table class="t-table task-project-table">
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>Stato</th>
                                    <th>Assegnata a</th>
                                    <th>Scadenza</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($projectTasks as $projectTask)
                                    <tr
                                        class="{{ $projectTask->id === $task->id ? 'is-current' : '' }}"
                                        onclick="window.location='{{ route('tasks.show', $projectTask) }}'"
                                    >
                                            <td class="task-project-table-title">
                                                <div class="u-text-truncate" title="{{ $projectTask->title }}">
                                                    {{ $projectTask->title }}
                                                </div>
                                            </td>
                                            <td><x-badge :status="$projectTask->status" :label="$projectTask->status_label" /></td>
                                            <td>{{ $projectTask->assignee?->name ?? '—' }}</td>
                                            <td>{{ $projectTask->due_date?->format('d/m/Y') ?? '—' }}</td>
                                        </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-panel>
                </div>
            @endif
        </div>
    </div>

    {{-- Commenti --}}
    <livewire:tasks.task-comments :task="$task" />

    {{-- Allegati --}}
    <livewire:shared.attachment-manager :model="$task" />

    {{-- Audit log --}}
    @if(auth()->user()->canViewAuditLogs())
    <div class="u-mt-md">
        <x-audit-timeline :logs="$task->auditLogs" />
    </div>
    @endif
    </div>
</x-app-layout>
