<x-app-layout title="Task">
    <x-page-header
        eyebrow="Modulo · Operativo"
        
        :meta="($viewMode === 'list' ? $taskList->total() : $kanbanTasks->count()) . ' totali'"
    >
    <x-slot:title><strong>Task</strong></x-slot:title>
        <x-slot:actions>
            <div class="tab-switcher" style="margin-right: 12px; display: inline-flex; align-items:center">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'list']) }}" class="tab-btn {{ $viewMode === 'list' ? 'active' : '' }}" style="text-decoration:none">Lista</a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'kanban']) }}" class="tab-btn {{ $viewMode === 'kanban' ? 'active' : '' }}" style="text-decoration:none">Kanban</a>
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
        <div class="pills" style="margin:0">
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
        <form method="GET" action="{{ route('tasks.index') }}" style="display:flex;gap:8px;margin-left:auto">
            <input type="hidden" name="view" value="{{ $viewMode }}">
            @if($currentStatus)
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            <select name="project_id" class="form-sel" style="padding:5px 10px;font-size:11px" onchange="this.form.submit()">
                <option value="">Tutti i progetti</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}" {{ $currentProject == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                @endforeach
            </select>
            <select name="assigned_to" class="form-sel" style="padding:5px 10px;font-size:11px" onchange="this.form.submit()">
                <option value="">Tutti gli utenti</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ $currentUser == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            <select name="priority" class="form-sel" style="padding:5px 10px;font-size:11px" onchange="this.form.submit()">
                <option value="">Tutte le priorità</option>
                <option value="urgent" {{ $currentPriority==='urgent' ? 'selected' : '' }}>Urgente</option>
                <option value="high"   {{ $currentPriority==='high'   ? 'selected' : '' }}>Alta</option>
                <option value="medium" {{ $currentPriority==='medium' ? 'selected' : '' }}>Media</option>
                <option value="low"    {{ $currentPriority==='low'    ? 'selected' : '' }}>Bassa</option>
            </select>
            @if($currentStatus || $currentPriority || $currentProject || $currentUser)
                <a href="{{ route('tasks.index') }}" class="btn btn-g" style="padding:5px 10px;font-size:11px">Reset</a>
            @endif
        </form>
    </div>

    @if($viewMode === 'kanban')
        <div class="kanban">
            @foreach(App\Http\Controllers\TaskController::STATUSES as $status)
            <div class="k-col">
                <div class="k-col-title">
                    <span>{{ strtoupper(str_replace('_', ' ', $status)) }}</span>
                    <span class="badge" style="background:var(--bg3);color:var(--text2)">
                        {{ $kanbanTasks->where('status', $status)->count() }}
                    </span>
                </div>
                <div class="k-cards">
                    @foreach($kanbanTasks->where('status', $status) as $task)
                    <div class="k-card enhanced" onclick="window.location='{{ route('tasks.show', $task) }}'">
                        <div class="k-card-title" style="{{ $task->status === 'done' ? 'text-decoration:line-through;color:var(--text3)' : '' }}">
                            {{ $task->title }}
                        </div>
                        <div class="k-card-meta" style="margin-bottom:6px">
                            {{ $task->project?->name ?? 'Nessun progetto' }}
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span class="k-card-meta">{{ $task->assignee?->name ?? 'Non assegnato' }}</span>
                            @if($task->due_date)
                                <span class="k-card-meta" style="{{ $task->due_date->isPast() && $task->status !== 'done' ? 'color:var(--red)' : '' }}">
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
                    <tr onclick="window.location='{{ route('tasks.show', $task) }}'" style="cursor:pointer">
                        <td class="name-col" style="{{ $task->status === 'done' ? 'text-decoration:line-through;color:var(--text3)' : '' }}">
                            {{ $task->title }}
                        </td>
                        <td>
                            @if($task->project)
                                <span style="font-size:11px;color:var(--text2)">{{ $task->project->name }}</span>
                                @if($task->project->client)
                                    <div style="font-family:var(--mono);font-size:9px;color:var(--text3)">{{ $task->project->client->name }}</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $task->assignee?->name ?? '—' }}</td>
                        <td><x-badge :status="$task->priority" :label="$task->priority_label" /></td>
                        <td class="mono-col" style="{{ $task->due_date && $task->due_date->isPast() && $task->status !== 'done' ? 'color:var(--red)' : '' }}">
                            {{ $task->due_date?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td><x-badge :status="$task->status" :label="$task->status_label" /></td>
                        <td>
                            @can('update', $task)
                                <a href="{{ route('tasks.edit', $task) }}" class="btn-icon" onclick="event.stopPropagation()">✎</a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="text-align:center;color:var(--text3);padding:32px">
                            Nessun task trovato
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            {{ $taskList->links() }}
        </x-panel>
    @endif
</x-app-layout>
