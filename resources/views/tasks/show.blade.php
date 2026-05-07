<x-app-layout title="{{ $task->title }}">
    <x-page-header
        eyebrow="Task · {{ $task->project?->name ?? '—' }}"
        
    >
    <x-slot:title><strong>{{ $task->title }}</strong></x-slot:title>
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
        <div class="step-bar">
            @php
                $statuses = \App\Http\Controllers\TaskController::STATUSES;
                $currentIndex = array_search($task->status, $statuses);
            @endphp
            @foreach($statuses as $index => $s)
                <div class="step-seg {{ $index < $currentIndex ? 'completed' : ($index === $currentIndex ? 'active' : '') }}" title="{{ (new \App\Models\Task(['status' => $s]))->status_label }}"></div>
            @endforeach
        </div>
        <div class="u-flex-between u-mt-sm task-step-labels">
            @foreach($statuses as $index => $s)
                <div class="task-step-label {{ $index === 0 ? 'task-step-label-left' : ($index === count($statuses) - 1 ? 'task-step-label-right' : 'task-step-label-center') }}">
                    {{ (new \App\Models\Task(['status' => $s]))->status_label }}
                </div>
            @endforeach
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

                    <div class="u-text-meta u-mb-md">
                        {{ $doneChecklist }}/{{ $totalChecklist }} completati
                    </div>

                    @forelse($task->checklistItems as $item)
                        <div class="u-flex-center u-gap-sm task-checklist-item">
                            <form action="{{ route('task-checklist-items.toggle', $item) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-g btn-sm">
                                    {{ $item->is_completed ? '✓' : '○' }}
                                </button>
                            </form>

                            <div class="u-flex-1 {{ $item->is_completed ? 'u-text-muted task-checklist-completed' : 'u-text-strong' }}">
                                {{ $item->title }}
                            </div>

                            @if($item->is_completed)
                                <div class="u-text-meta">
                                    {{ $item->completedBy?->name }}
                                </div>
                            @endif

                            <form action="{{ route('task-checklist-items.destroy', $item) }}" method="POST"
                                  class="js-confirm-form" data-confirm-message="Eliminare questa voce checklist?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-icon u-text-red">✕</button>
                            </form>
                        </div>
                    @empty
                        <div class="u-empty-state-sm">Nessuna voce checklist.</div>
                    @endforelse

                    @can('update', $task)
                        <form action="{{ route('tasks.checklist-items.store', $task) }}" method="POST" class="u-flex u-gap-sm u-mt-md">
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
        </div>
    </div>

    {{-- Commenti --}}
    <div class="u-mt-lg">
        <x-panel title="Commenti / Storico operativo" dot="var(--blue)" padded>
            @can('update', $task)
                <form action="{{ route('tasks.comments.store', $task) }}" method="POST" class="u-mb-md">
                    @csrf
                    <textarea name="body"
                              class="form-ta @error('body') is-invalid @enderror"
                              rows="3"
                              placeholder="Scrivi un aggiornamento, una nota o un avanzamento..."
                              required>{{ old('body') }}</textarea>

                    @error('body')
                        <div class="u-text-red u-mt-sm">{{ $message }}</div>
                    @enderror

                    <div class="u-mt-sm u-text-right">
                        <button type="submit" class="btn btn-p">Aggiungi commento</button>
                    </div>
                </form>
            @endcan

            @forelse($task->comments as $comment)
                <div class="task-comment-item">
                    <div class="u-flex-between u-mb-sm">
                        <strong class="u-text-strong">{{ $comment->user?->name ?? 'Sistema' }}</strong>
                        <span class="u-text-meta">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="u-text-muted task-comment-body">{{ $comment->body }}</div>
                </div>
            @empty
                <div class="u-empty-state-sm">Nessun commento ancora presente.</div>
            @endforelse
        </x-panel>
    </div>

    {{-- Allegati --}}
    <div class="u-mt-lg">
        <x-panel title="Allegati ({{ $task->attachments->count() }})" dot="var(--accent)" padded>
            @forelse($task->attachments as $att)
                <div class="u-flex-between task-attachment-item">
                    <div>
                        <div class="u-text-strong">{{ $att->original_name }}</div>
                        <div class="u-text-meta">
                            {{ strtoupper($att->extension) }} ·
                            {{ number_format($att->size / 1024, 1) }} KB ·
                            {{ $att->uploader?->name }} · {{ $att->created_at->diffForHumans() }}
                        </div>
                    </div>
                    <div class="u-flex u-gap-sm">
                        <a href="{{ route('attachments.download', $att) }}" class="btn btn-g btn-sm">↓</a>
                        @can('delete', $att)
                            <x-delete-modal 
                                action="{{ route('attachments.destroy', $att) }}" 
                                title="Elimina Allegato" 
                                message="Sei sicuro di voler eliminare il file '{{ $att->original_name }}'?">
                                <button type="button" class="btn-icon u-text-red">✕</button>
                            </x-delete-modal>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="u-empty-state-sm">Nessun allegato.</div>
            @endforelse

            @can('create', App\Models\Attachment::class)
                <form action="{{ route('attachments.store') }}" method="POST"
                      enctype="multipart/form-data"
                      class="u-flex u-gap-sm u-section-sep task-attachment-form">
                    @csrf
                    <input type="hidden" name="attachable_type" value="task">
                    <input type="hidden" name="attachable_id" value="{{ $task->id }}">
                    <div class="u-flex-1">
                        <div class="u-text-label">Carica allegato</div>
                        <input type="file" name="file" required class="form-in task-file-input">
                    </div>
                    <button type="submit" class="btn btn-g">Carica</button>
                </form>
            @endcan
        </x-panel>
    </div>

    {{-- Audit log --}}
    @if(auth()->user()->canViewAuditLogs())
    <div class="u-mt-md">
        <x-audit-timeline :logs="$task->auditLogs" />
    </div>
    @endif

</x-app-layout>
