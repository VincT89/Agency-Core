<x-app-layout title="{{ $ticket->title }}">
    <div class="u-mb-lg u-flex-end">
        <a href="{{ route('tickets.index') }}" wire:navigate class="btn btn-g u-flex-center u-gap-xs">
            <i data-lucide="arrow-left" class="u-icon-sm"></i> Torna alla lista
        </a>
    </div>
    <x-page-header
        eyebrow="Ticket #{{ $ticket->id }}"
        
    >
    <x-slot:title><strong>{{ $ticket->title }}</strong></x-slot:title>
        <x-slot:actions>
            <x-badge :status="$ticket->status" :label="$ticket->status_label" />
            @can('update', $ticket)
                <a href="{{ route('tickets.edit', $ticket) }}" class="btn btn-g">Modifica</a>
            @endcan
            @can('create', \App\Models\Task::class)
                <a href="{{ route('tasks.create', ['ticket_id' => $ticket->id]) }}" class="btn btn-p">Crea Task Collegato</a>
            @endcan
        
            @can('delete', $ticket)
                <form action="{{ route('tickets.destroy', $ticket) }}" method="POST"
                      class="js-confirm-form" data-confirm-message="Eliminare il ticket #{{ $ticket->id }}?">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-g btn-danger">
                        Elimina
                    </button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="g-2col-main">
        <div>
            <x-panel title="Dettagli Ticket" padded>
                <div class="ticket-badges">
                    <x-badge :status="$ticket->type" :label="$ticket->type_label" />
                    <x-badge :status="$ticket->priority" :label="$ticket->priority_label" />
                </div>
                
                @if($ticket->description)
                <div class="ticket-description">{{ $ticket->description }}</div>
                @else
                <div class="ticket-description-empty">Nessuna descrizione fornita.</div>
                @endif

                @if($ticket->resolution_notes)
                <div class="ticket-resolution">
                    <div class="ticket-resolution-header">
                        <i data-lucide="check-circle" class="ticket-resolution-icon"></i> Note di Risoluzione
                    </div>
                    <div class="ticket-resolution-body">{{ $ticket->resolution_notes }}</div>
                </div>
                @endif
            </x-panel>

            @if($ticket->tasks->count() > 0)
            <div class="u-mt-md">
                <x-panel title="Task Generati" dot="var(--blue)" padded>
                    @foreach($ticket->tasks as $task)
                        <div class="u-flex-between u-mb-sm u-section-sep">
                            <div>
                                <a href="{{ route('tasks.show', $task) }}" class="u-text-strong u-text-accent-link">{{ $task->title }}</a>
                                <div class="u-text-meta">
                                    {{ $task->project?->name }} • Assegnato a: {{ $task->assignee?->name ?? 'Nessuno' }}
                                </div>
                            </div>
                            <div class="u-text-right">
                                <x-badge :status="$task->status" :label="$task->status_label" />
                                <div class="u-text-meta">
                                    Scadenza: {{ $task->due_date?->format('d/m/Y') ?? '—' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </x-panel>
            </div>
            @endif

            {{-- Cambio rapido status --}}
            <div class="u-mt-md">
                <x-panel title="Aggiornamento rapido" dot="var(--teal)" padded>
                    <div class="u-flex u-gap-sm task-status-grid">
                        @foreach(\App\Models\Ticket::STATUSES as $s)
                            <form action="{{ route('tickets.update-status', $ticket) }}" method="POST">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $s }}">
                                <button type="submit" class="btn {{ $ticket->status === $s ? 'btn-p' : 'btn-g' }} btn-sm">
                                    {{ (new \App\Models\Ticket(['status' => $s]))->status_label }}
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
                        $totalChecklist = $ticket->checklistItems->count();
                        $doneChecklist = $ticket->checklistItems->where('is_completed', true)->count();
                    @endphp

                    <div class="u-text-meta u-mb-md" data-checklist-counter>
                        {{ $doneChecklist }}/{{ $totalChecklist }} completati
                    </div>

                    @forelse($ticket->checklistItems as $item)
                        <div class="u-flex-center u-gap-sm task-checklist-item" data-checklist-item="{{ $item->id }}">
                            <form action="{{ route('ticket-checklist-items.toggle', $item) }}" method="POST" class="js-checklist-toggle">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-g btn-sm" data-checklist-toggle-button>
                                    {{ $item->is_completed ? '✓' : '○' }}
                                </button>
                            </form>

                            <div class="u-flex-1 {{ $item->is_completed ? 'u-text-muted task-checklist-completed' : 'u-text-strong' }}" data-checklist-title>
                                {{ $item->title }}
                            </div>

                            <div class="u-text-meta" data-checklist-completed-by>
                                {{ $item->is_completed ? $item->completedBy?->name : '' }}
                            </div>

                            <form action="{{ route('ticket-checklist-items.destroy', $item) }}" method="POST"
                                  class="js-confirm-form" data-confirm-message="Eliminare questa voce checklist?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-icon u-text-red">✕</button>
                            </form>
                        </div>
                    @empty
                        <div class="u-empty-state-sm">Nessuna voce checklist.</div>
                    @endforelse

                    @can('update', $ticket)
                        <form action="{{ route('tickets.checklist-items.store', $ticket) }}" method="POST" class="u-flex u-gap-sm u-mt-md">
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
            <x-panel title="Info Base" dot="var(--yellow)" padded>
                <div class="form-g mb-3">
                    <div class="u-text-label">Codice Ticket</div>
                    <div class="u-text-mono u-color-text">{{ $ticket->code ?? '—' }}</div>
                </div>
                <div class="form-g mb-3">
                    <div class="u-text-label">Cliente</div>
                    <div class="u-text-strong">
                        @if($ticket->client)
                            <a href="{{ route('clients.show', $ticket->client) }}" class="u-text-accent-link">{{ $ticket->client->name }}</a>
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="form-g mb-3">
                    <div class="u-text-label">Progetto</div>
                    <div class="u-text-strong">
                        @if($ticket->project)
                            <a href="{{ route('projects.show', $ticket->project) }}" class="u-text-accent-link">{{ $ticket->project->name }}</a>
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="form-g mb-3">
                    <div class="u-text-label">Assegnato a</div>
                    <div class="u-text-strong">{{ $ticket->assignee?->name ?? 'Non assegnato' }}</div>
                </div>
                <div class="form-g mb-3">
                    <div class="u-text-label">Creato da</div>
                    <div class="u-text-strong">{{ $ticket->creator?->name ?? 'Sistema' }}</div>
                </div>
                <div class="form-g mb-3">
                    <div class="u-text-label">Aperto il</div>
                    <div class="u-text-mono u-color-text">{{ $ticket->opened_at?->isoFormat('D MMMM YYYY') ?? $ticket->created_at->isoFormat('D MMMM YYYY') }}</div>
                </div>
                @if($ticket->due_date)
                <div class="form-g mb-3">
                    <div class="u-text-label">Scadenza</div>
                    <div class="u-text-mono u-color-text">{{ $ticket->due_date->isoFormat('D MMMM YYYY') }}</div>
                </div>
                @endif
            </x-panel>
        </div>
    </div>

    {{-- Commenti --}}
    <livewire:tickets.ticket-comments :ticket="$ticket" />
    <x-audit-timeline :logs="$ticket->auditLogs" />

    {{-- Allegati --}}
    <livewire:shared.attachment-manager :model="$ticket" />
</x-app-layout>