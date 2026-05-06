<x-app-layout title="Progetti">
    <x-page-header
        eyebrow="Modulo · Core"
        
        :meta="$projects->total() . ' totali'"
    >
    <x-slot:title><strong>Progetti</strong></x-slot:title>
        <x-slot:actions>
            @can('create', App\Models\Project::class)
                <a href="{{ route('projects.create') }}" class="btn btn-p">+ Nuovo progetto</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="filter-bar justify-end">
        <form method="GET" action="{{ route('projects.index') }}" style="display:flex;gap:8px">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cerca progetto o cliente..." class="form-in" style="padding:5px 10px;font-size:11px;width:250px">
            @if(request('search'))
                <a href="{{ route('projects.index') }}" class="btn btn-g" style="padding:5px 10px;font-size:11px">Reset</a>
            @endif
        </form>
    </div>

    <x-panel>
        <table class="t-table">
            <thead>
                <tr>
                    <th>Nome Progetto</th>
                    <th>Cliente</th>
                    <th>Task</th>
                    <th>Ticket</th>
                    <th>Stato</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $project)
                <tr onclick="window.location='{{ route('projects.show', $project) }}'" style="cursor:pointer">
                    <td class="name-col">{{ $project->name }}</td>
                    <td>{{ $project->client?->name ?? '—' }}</td>
                    <td class="mono-col">{{ $project->tasks_count }}</td>
                    <td class="mono-col">{{ $project->tickets_count }}</td>
                    <td><x-badge :status="$project->status" :label="$project->status_label" /></td>
                    <td>
                        @can('update', $project)
                            <a href="{{ route('projects.edit', $project) }}" class="btn-icon" onclick="event.stopPropagation()">✎</a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:var(--text3);padding:32px">Nessun progetto trovato</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $projects->links() }}
    </x-panel>
</x-app-layout>