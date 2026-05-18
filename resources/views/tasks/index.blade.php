<x-app-layout title="Task">
    <x-page-header
        eyebrow="Modulo · Operativo"
        
        :meta="($viewMode === 'list' ? $taskList->total() : $kanbanTasks->count()) . ' totali'"
    >
    <x-slot:title><strong>Task</strong></x-slot:title>
        <x-slot:actions>
            <div class="tab-switcher u-mr-sm u-flex-center u-inline-flex">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'list']) }}" class="tab-btn {{ $viewMode === 'list' ? 'active' : '' }} u-text-decoration-none">Lista</a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'kanban']) }}" class="tab-btn {{ $viewMode === 'kanban' ? 'active' : '' }} u-text-decoration-none">Kanban</a>
            </div>
            @can('create', App\Models\Task::class)
                <a href="{{ route('tasks.create') }}" class="btn btn-p">+ Nuovo task</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Filtri --}}
    <div class="filter-bar">
        @php
            $currentStatus   = request('status');
            $currentPriority = request('priority');
            $currentProject  = request('project_id');
            $currentUser     = request('assigned_to');
        @endphp

        {{-- Status pills --}}
        <div class="pills u-m-0">
            <a href="{{ route('tasks.index', array_filter(['priority'=>$currentPriority,'project_id'=>$currentProject,'assigned_to'=>$currentUser])) }}"
               class="pill {{ !$currentStatus ? 'on' : '' }}">Tutti</a>
            <a href="{{ route('tasks.index', array_filter(['status'=>'todo','priority'=>$currentPriority,'project_id'=>$currentProject,'assigned_to'=>$currentUser])) }}"
               class="pill {{ $currentStatus==='todo' ? 'on' : '' }}">Da fare</a>
            <a href="{{ route('tasks.index', array_filter(['status'=>'in_progress','priority'=>$currentPriority,'project_id'=>$currentProject,'assigned_to'=>$currentUser])) }}"
               class="pill {{ $currentStatus==='in_progress' ? 'on' : '' }}">In corso</a>
            <a href="{{ route('tasks.index', array_filter(['status'=>'waiting','priority'=>$currentPriority,'project_id'=>$currentProject,'assigned_to'=>$currentUser])) }}"
               class="pill {{ $currentStatus==='waiting' ? 'on' : '' }}">In attesa</a>
            <a href="{{ route('tasks.index', array_filter(['status'=>'done','priority'=>$currentPriority,'project_id'=>$currentProject,'assigned_to'=>$currentUser])) }}"
               class="pill {{ $currentStatus==='done' ? 'on' : '' }}">Completati</a>
        </div>

        {{-- Filtri aggiuntivi select --}}
        <form method="GET" action="{{ route('tasks.index') }}" class="u-flex u-gap-sm filter-form u-ml-auto">
            <input type="hidden" name="view" value="{{ $viewMode }}">
            @if($currentStatus)
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            <select name="project_id" class="form-sel form-sel-sm filter-select js-auto-submit">
                <option value="">Tutti i progetti</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}" {{ $currentProject == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                @endforeach
            </select>
            <select name="assigned_to" class="form-sel form-sel-sm filter-select js-auto-submit">
                <option value="">Tutti gli utenti</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ $currentUser == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            <select name="priority" class="form-sel form-sel-sm filter-select js-auto-submit">
                <option value="">Tutte le priorità</option>
                <option value="urgent" {{ $currentPriority==='urgent' ? 'selected' : '' }}>Urgente</option>
                <option value="high"   {{ $currentPriority==='high'   ? 'selected' : '' }}>Alta</option>
                <option value="medium" {{ $currentPriority==='medium' ? 'selected' : '' }}>Media</option>
                <option value="low"    {{ $currentPriority==='low'    ? 'selected' : '' }}>Bassa</option>
            </select>
            @if($currentStatus || $currentPriority || $currentProject || $currentUser)
                <a href="{{ route('tasks.index') }}" class="btn btn-g btn-sm">Reset</a>
            @endif
        </form>
    </div>

    @if($viewMode === 'kanban')
        <div class="kanban">
            @foreach(App\Http\Controllers\TaskController::STATUSES as $status)
            <div class="k-col">
                <div class="k-col-title">
                    <span>{{ strtoupper(str_replace('_', ' ', $status)) }}</span>
                    <span class="badge badge-subtle">
                        {{ $kanbanTasks->where('status', $status)->count() }}
                    </span>
                </div>
                <div class="k-cards sortable-task-col" data-status="{{ $status }}">
                    @foreach($kanbanTasks->where('status', $status) as $task)
                    <div class="k-card enhanced js-clickable-row u-cursor-pointer {{ $task->status === 'done' ? 'task-row-done' : '' }}" data-href="{{ route('tasks.show', $task) }}" data-task-id="{{ $task->id }}" style="border-left: 6px solid {{ $task->assignee ? $task->assignee->role->color() : 'var(--border)' }};">
                        <div class="k-card-title task-title">
                            {{ $task->title }}
                        </div>
                        <div class="k-card-meta u-mb-xs">
                            {{ $task->project?->name ?? 'Nessun progetto' }}
                        </div>
                        <div class="u-flex-between">
                            <div class="u-flex u-items-center u-gap-xs">
                                <span class="k-card-meta">{{ $task->assignee?->name ?? 'Non assegnato' }}</span>
                            </div>
                            @if($task->due_date)
                                <span class="k-card-meta {{ $task->due_date->isPast() && $task->status !== 'done' ? 'u-text-red' : '' }}">
                                    {{ $task->due_date->format('d/m') }}
                                </span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    @else
        <x-panel>
            <table class="t-table">
                <thead>
                    <tr>
                        <th>Titolo</th>
                        <th>Progetto</th>
                        <th>Assegnato a</th>
                        <th>Priorità</th>
                        <th>Scadenza</th>
                        <th>Stato</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($taskList as $task)
                    <tr data-href="{{ route('tasks.show', $task) }}" class="js-clickable-row u-cursor-pointer hover-bg {{ $task->status === 'done' ? 'task-row-done' : '' }}">
                        <td class="name-col task-title" style="border-left: 6px solid {{ $task->assignee ? $task->assignee->role->color() : 'transparent' }};">
                            {{ $task->title }}
                        </td>
                        <td>
                            @if($task->project)
                                <span class="u-text-mono u-text-main">{{ $task->project->name }}</span>
                                @if($task->project->client)
                                    <div class="u-text-meta u-text-xs">{{ $task->project->client->name }}</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <div class="u-flex u-items-center u-gap-xs">
                                <span>{{ $task->assignee?->name ?? '—' }}</span>
                            </div>
                        </td>
                        <td><x-badge :status="$task->priority" :label="$task->priority_label" /></td>
                        <td class="mono-col {{ $task->due_date && $task->due_date->isPast() && $task->status !== 'done' ? 'u-text-red' : '' }}">
                            {{ $task->due_date?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td><x-badge :status="$task->status" :label="$task->status_label" /></td>
                        <td>
                            @can('update', $task)
                                <a href="{{ route('tasks.edit', $task) }}" class="btn-icon js-stop-propagation">✎</a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="u-empty-state">
                            Nessun task trovato
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            {{ $taskList->links() }}
        </x-panel>
    @endif

    @if($viewMode === 'kanban')
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
            <script>
                document.addEventListener('livewire:navigated', function () {
                    initTaskSortable();
                });

                document.addEventListener('DOMContentLoaded', function () {
                    initTaskSortable();
                });

                function initTaskSortable() {
                    if (typeof Sortable === 'undefined') return;

                    const columns = document.querySelectorAll('.sortable-task-col');
                    
                    columns.forEach(col => {
                        if (col._sortable) col._sortable.destroy();
                        
                        col._sortable = new Sortable(col, {
                            group: 'tasks-kanban',
                            animation: 150,
                            ghostClass: 'k-card-ghost',
                            onEnd: async function (evt) {
                                const itemEl = evt.item;
                                const taskId = itemEl.dataset.taskId;
                                const newStatus = evt.to.dataset.status;
                                const oldStatus = evt.from.dataset.status;
                                
                                if (newStatus === oldStatus) return;
                                
                                try {
                                    const res = await fetch(`/tasks/${taskId}/status`, {
                                        method: 'PATCH',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                            'Accept': 'application/json'
                                        },
                                        body: JSON.stringify({
                                            status: newStatus
                                        })
                                    });
                                    
                                    if (!res.ok) {
                                        throw new Error('Update failed');
                                    }
                                    
                                    const responseData = await res.json();

                                    // Update styling if task is moved to 'done' or from 'done'
                                    if (newStatus === 'done') {
                                        itemEl.classList.add('task-row-done');
                                    } else {
                                        itemEl.classList.remove('task-row-done');
                                    }

                                    // Update counter badges
                                    const oldBadge = evt.from.previousElementSibling.querySelector('.badge');
                                    const newBadge = evt.to.previousElementSibling.querySelector('.badge');
                                    if (oldBadge) oldBadge.textContent = Math.max(0, parseInt(oldBadge.textContent) - 1);
                                    if (newBadge) newBadge.textContent = parseInt(newBadge.textContent) + 1;

                                } catch (e) {
                                    console.error('Failed to update task status', e);
                                    alert('Errore durante l\'aggiornamento dello stato.');
                                    // Rollback visivo
                                    evt.from.insertBefore(itemEl, evt.from.children[evt.oldIndex]);
                                }
                            }
                        });
                    });
                }
            </script>
        @endpush
    @endif
</x-app-layout>
