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
        </div>

        <div>
            <x-panel title="Info Base" dot="var(--yellow)" padded>
                <div class="form-g mb-2">
                    <div class="form-lbl">Codice Ticket</div>
                    <div class="ticket-info-mono">{{ $ticket->code ?? '—' }}</div>
                </div>
                <div class="form-g mb-2">
                    <div class="form-lbl">Cliente</div>
                    <div class="ticket-info-value">
                        @if($ticket->client)
                            <a href="{{ route('clients.show', $ticket->client) }}" class="ticket-info-link">{{ $ticket->client->name }}</a>
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="form-g mb-2">
                    <div class="form-lbl">Progetto</div>
                    <div class="ticket-info-value">
                        @if($ticket->project)
                            <a href="{{ route('projects.show', $ticket->project) }}" class="ticket-info-link">{{ $ticket->project->name }}</a>
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="form-g mb-2">
                    <div class="form-lbl">Assegnato a</div>
                    <div class="ticket-info-value">{{ $ticket->assignee?->name ?? 'Non assegnato' }}</div>
                </div>
                <div class="form-g mb-2">
                    <div class="form-lbl">Creato da</div>
                    <div class="ticket-info-value">{{ $ticket->creator?->name ?? 'Sistema' }}</div>
                </div>
                <div class="form-g mb-2">
                    <div class="form-lbl">Aperto il</div>
                    <div class="ticket-info-mono">{{ $ticket->opened_at?->isoFormat('D MMMM YYYY') ?? $ticket->created_at->isoFormat('D MMMM YYYY') }}</div>
                </div>
                @if($ticket->due_date)
                <div class="form-g mb-2">
                    <div class="form-lbl">Scadenza</div>
                    <div class="ticket-info-mono">{{ $ticket->due_date->isoFormat('D MMMM YYYY') }}</div>
                </div>
                @endif
            </x-panel>
        </div>
    </div>

    {{-- Commenti --}}
    <div class="u-mt-lg">
        <x-panel title="Commenti / Note operative" dot="var(--blue)" padded>
            @can('update', $ticket)
                <form action="{{ route('tickets.comments.store', $ticket) }}" method="POST" class="u-mb-md">
                    @csrf
                    <textarea name="body"
                              class="form-ta @error('body') is-invalid @enderror"
                              rows="3"
                              placeholder="Aggiungi un aggiornamento, una nota o un'azione intrapresa..."
                              required>{{ old('body') }}</textarea>
                    @error('body')
                        <div class="u-text-red u-mt-sm">{{ $message }}</div>
                    @enderror
                    <div class="u-mt-sm u-text-right">
                        <button type="submit" class="btn btn-p">Aggiungi commento</button>
                    </div>
                </form>
            @endcan

            @forelse($ticket->comments as $comment)
                <div class="ticket-comment-item">
                    <div class="u-flex-between u-mb-sm">
                        <span class="u-flex u-items-center u-gap-xs">
                            <strong class="u-text-strong">
                                @if($comment->source === \App\Enums\Social\CommentSource::Client)
                                    [Cliente]
                                @else
                                    {{ $comment->user?->name ?? 'Sistema' }}
                                @endif
                            </strong>
                            @if($comment->source === \App\Enums\Social\CommentSource::Client)
                                <span class="cmp-client-badge">Risposta cliente</span>
                            @endif
                        </span>
                        <span class="u-text-meta">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="ticket-comment-body">{{ $comment->body }}</div>
                </div>
            @empty
                <div class="u-empty-state-sm">Nessun commento ancora presente.</div>
            @endforelse
        </x-panel>
    </div>
    <x-audit-timeline :logs="$ticket->auditLogs" />

    {{-- Allegati --}}
    <livewire:shared.attachment-manager :model="$ticket" />
</x-app-layout>