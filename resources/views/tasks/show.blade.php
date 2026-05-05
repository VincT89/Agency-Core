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
                    <button type="button" class="btn btn-g"
                            style="color:var(--red);border-color:rgba(245,75,75,.3)">Elimina</button>
                </x-delete-modal>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div style="margin-bottom: 24px">
        <div class="step-bar">
            @php
                $statuses = \App\Http\Controllers\TaskController::STATUSES;
                $currentIndex = array_search($task->status, $statuses);
            @endphp
            @foreach($statuses as $index => $s)
                <div class="step-seg {{ $index < $currentIndex ? 'completed' : ($index === $currentIndex ? 'active' : '') }}" title="{{ (new \App\Models\Task(['status' => $s]))->status_label }}"></div>
            @endforeach
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:6px; font-family:var(--mono); font-size:9px; color:var(--text3); text-transform:uppercase;">
            @foreach($statuses as $index => $s)
                <div style="flex:1; text-align:{{ $index === 0 ? 'left' : ($index === count($statuses) - 1 ? 'right' : 'center') }}">
                    {{ (new \App\Models\Task(['status' => $s]))->status_label }}
                </div>
            @endforeach
        </div>
    </div>

    <div class="g-2col-main">
        <div>
            <x-panel title="Descrizione" dot="var(--blue)" padded>
                @if($task->description)
                    <div style="color:var(--text);font-size:14px;line-height:1.7;white-space:pre-wrap">{{ $task->description }}</div>
                @else
                    <div style="color:var(--text3);font-style:italic">Nessuna descrizione.</div>
                @endif
                @if($task->notes)
                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--line)">
                        <div class="form-lbl" style="margin-bottom:6px">Note interne</div>
                        <div style="color:var(--text3);font-size:13px;white-space:pre-wrap">{{ $task->notes }}</div>
                    </div>
                @endif
            </x-panel>

            {{-- Cambio rapido status --}}
            <div style="margin-top:16px">
                <x-panel title="Aggiornamento rapido" dot="var(--teal)" padded>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        @foreach(\App\Http\Controllers\TaskController::STATUSES as $s)
                            <form action="{{ route('tasks.update-status', $task) }}" method="POST">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $s }}">
                                <button type="submit"
                                        class="btn {{ $task->status === $s ? 'btn-p' : 'btn-g' }}"
                                        style="font-size:11px;padding:6px 12px">
                                    {{ (new \App\Models\Task(['status' => $s]))->status_label }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </x-panel>
            </div>

            {{-- Checklist --}}
            <div style="margin-top:16px">
                <x-panel title="Checklist" dot="var(--green)" padded>
                    @php
                        $totalChecklist = $task->checklistItems->count();
                        $doneChecklist = $task->checklistItems->where('is_completed', true)->count();
                    @endphp

                    <div style="font-size:12px;color:var(--text3);margin-bottom:12px">
                        {{ $doneChecklist }}/{{ $totalChecklist }} completati
                    </div>

                    @forelse($task->checklistItems as $item)
                        <div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--line)">
                            <form action="{{ route('task-checklist-items.toggle', $item) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-g" style="padding:4px 8px">
                                    {{ $item->is_completed ? '✓' : '○' }}
                                </button>
                            </form>

                            <div style="flex:1;{{ $item->is_completed ? 'text-decoration:line-through;color:var(--text3)' : 'color:var(--text)' }}">
                                {{ $item->title }}
                            </div>

                            @if($item->is_completed)
                                <div style="font-size:10px;color:var(--text3)">
                                    {{ $item->completedBy?->name }}
                                </div>
                            @endif

                            <form action="{{ route('task-checklist-items.destroy', $item) }}" method="POST"
                                  onsubmit="return confirm('Eliminare questa voce checklist?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-icon" style="color:var(--red)">✕</button>
                            </form>
                        </div>
                    @empty
                        <div style="color:var(--text3);font-size:12px">Nessuna voce checklist.</div>
                    @endforelse

                    @can('update', $task)
                        <form action="{{ route('tasks.checklist-items.store', $task) }}" method="POST"
                              style="display:flex;gap:8px;margin-top:12px">
                            @csrf
                            <input name="title" class="form-in" placeholder="Nuova voce checklist..." required>
                            <button type="submit" class="btn btn-g">Aggiungi</button>
                        </form>
                        @error('title')
                            <div style="color:var(--red);font-size:12px;margin-top:6px">{{ $message }}</div>
                        @enderror
                    @endcan
                </x-panel>
            </div>
        </div>

        <div>
            <x-panel title="Dettagli" dot="var(--yellow)" padded>
                <div class="form-g mb-2">
                    <div class="form-lbl">Progetto</div>
                    <div style="color:var(--text)">
                        @if($task->project)
                            <a href="{{ route('projects.show', $task->project) }}"
                               style="color:var(--accent);text-decoration:none">{{ $task->project->name }}</a>
                        @else —
                        @endif
                    </div>
                </div>
                @if($task->project?->client)
                <div class="form-g mb-2">
                    <div class="form-lbl">Cliente</div>
                    <div>
                        <a href="{{ route('clients.show', $task->project->client) }}"
                           style="color:var(--accent);text-decoration:none">{{ $task->project->client->name }}</a>
                    </div>
                </div>
                @endif
                <div class="form-g mb-2">
                    <div class="form-lbl">Assegnato a</div>
                    <div style="color:var(--text)">{{ $task->assignee?->name ?? 'Non assegnato' }}</div>
                </div>
                <div class="form-g mb-2">
                    <div class="form-lbl">Creato da</div>
                    <div style="color:var(--text)">{{ $task->creator?->name ?? 'Sistema' }}</div>
                </div>
                @if($task->start_date)
                <div class="form-g mb-2">
                    <div class="form-lbl">Data inizio</div>
                    <div style="color:var(--text);font-family:var(--mono)">{{ $task->start_date->format('d/m/Y') }}</div>
                </div>
                @endif
                <div class="form-g mb-2">
                    <div class="form-lbl">Scadenza</div>
                    <div style="font-family:var(--mono);{{ $task->due_date?->isPast() && $task->status !== 'done' ? 'color:var(--red)' : 'color:var(--text)' }}">
                        {{ $task->due_date?->format('d/m/Y') ?? '—' }}
                    </div>
                </div>
                @if($task->completed_at)
                <div class="form-g mb-2">
                    <div class="form-lbl">Completato il</div>
                    <div style="color:var(--green);font-family:var(--mono)">{{ $task->completed_at->format('d/m/Y H:i') }}</div>
                </div>
                @endif
                <div class="form-g">
                    <div class="form-lbl">Creato il</div>
                    <div style="color:var(--text2);font-family:var(--mono)">{{ $task->created_at->isoFormat('D MMMM YYYY') }}</div>
                </div>
            </x-panel>

            {{-- Bottone crea task da progetto --}}
            @can('create', App\Models\Task::class)
            <div style="margin-top:12px">
                <a href="{{ route('tasks.create', ['project_id' => $task->project_id]) }}"
                   class="btn btn-g" style="width:100%;text-align:center;display:block">
                    + Nuovo task nello stesso progetto
                </a>
            </div>
            @endcan
        </div>
    </div>

    {{-- Commenti --}}
    <div style="margin-top:20px">
        <x-panel title="Commenti / Storico operativo" dot="var(--blue)" padded>
            @can('update', $task)
                <form action="{{ route('tasks.comments.store', $task) }}" method="POST"
                      style="margin-bottom:16px">
                    @csrf
                    <textarea name="body"
                              class="form-ta @error('body') is-invalid @enderror"
                              rows="3"
                              placeholder="Scrivi un aggiornamento, una nota o un avanzamento..."
                              required>{{ old('body') }}</textarea>

                    @error('body')
                        <div style="color:var(--red);font-size:12px;margin-top:6px">{{ $message }}</div>
                    @enderror

                    <div style="margin-top:8px;text-align:right">
                        <button type="submit" class="btn btn-p">Aggiungi commento</button>
                    </div>
                </form>
            @endcan

            @forelse($task->comments as $comment)
                <div style="padding:12px 0;border-top:1px solid var(--line)">
                    <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:6px">
                        <strong style="font-size:13px;color:var(--text)">
                            {{ $comment->user?->name ?? 'Sistema' }}
                        </strong>
                        <span style="font-family:var(--mono);font-size:10px;color:var(--text3)">
                            {{ $comment->created_at->format('d/m/Y H:i') }}
                        </span>
                    </div>
                    <div style="font-size:13px;line-height:1.6;color:var(--text2);white-space:pre-wrap">{{ $comment->body }}</div>
                </div>
            @empty
                <div style="color:var(--text3);font-size:12px">Nessun commento ancora presente.</div>
            @endforelse
        </x-panel>
    </div>

    {{-- Allegati --}}
    <div style="margin-top:20px">
        <x-panel title="Allegati ({{ $task->attachments->count() }})" dot="var(--accent)" padded>
            @forelse($task->attachments as $att)
                <div style="display:flex;align-items:center;justify-content:space-between;
                            padding:10px 0;border-bottom:1px solid var(--line)">
                    <div>
                        <div style="font-size:12px;font-weight:600;color:var(--text)">{{ $att->original_name }}</div>
                        <div style="font-family:var(--mono);font-size:10px;color:var(--text3)">
                            {{ strtoupper($att->extension) }} ·
                            {{ number_format($att->size / 1024, 1) }} KB ·
                            {{ $att->uploader?->name }} · {{ $att->created_at->diffForHumans() }}
                        </div>
                    </div>
                    <div style="display:flex;gap:6px">
                        <a href="{{ route('attachments.download', $att) }}" class="btn btn-g"
                           style="font-size:10px;padding:5px 10px">↓</a>
                        @can('delete', $att)
                            <x-delete-modal 
                                action="{{ route('attachments.destroy', $att) }}" 
                                title="Elimina Allegato" 
                                message="Sei sicuro di voler eliminare il file '{{ $att->original_name }}'?">
                                <button type="button" class="btn-icon" style="color:var(--red)">✕</button>
                            </x-delete-modal>
                        @endcan
                    </div>
                </div>
            @empty
                <div style="color:var(--text3);font-size:12px;padding:8px 0">Nessun allegato.</div>
            @endforelse

            @can('create', App\Models\Attachment::class)
                <form action="{{ route('attachments.store') }}" method="POST"
                      enctype="multipart/form-data"
                      style="display:flex;gap:10px;align-items:flex-end;margin-top:14px;padding-top:14px;border-top:1px solid var(--line)">
                    @csrf
                    <input type="hidden" name="attachable_type" value="task">
                    <input type="hidden" name="attachable_id" value="{{ $task->id }}">
                    <div style="flex:1">
                        <div class="form-lbl" style="margin-bottom:5px">Carica allegato</div>
                        <input type="file" name="file" required class="form-in"
                               style="padding:6px 10px;cursor:pointer">
                    </div>
                    <button type="submit" class="btn btn-g">Carica</button>
                </form>
            @endcan
        </x-panel>
    </div>

    {{-- Audit log --}}
    @if(auth()->user()->canViewAuditLogs())
    <div style="margin-top:16px">
        <x-audit-timeline :logs="$task->auditLogs" />
    </div>
    @endif

</x-app-layout>
