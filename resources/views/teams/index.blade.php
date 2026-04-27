<x-app-layout title="Team">
    <x-page-header
        eyebrow="Modulo · Operativo"
        
        :meta="$teams->total() . ' totali'"
    >
    <x-slot:title><strong>Team</strong></x-slot:title>
        <x-slot:actions>
            @can('system.admin')
                <a href="{{ route('teams.create') }}" class="btn btn-p">+ Nuovo team</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="g-cards">
        @forelse($teams as $team)
            <div class="panel" style="cursor:pointer" onclick="window.location='{{ route('teams.show', $team) }}'">
                <div class="panel-header">
                    <div class="panel-title">
                        <div class="dot" style="background:{{ $team->is_active ? 'var(--teal)' : 'var(--text3)' }}"></div>
                        {{ $team->name }}
                    </div>
                    @can('system.admin')
                        <a href="{{ route('teams.edit', $team) }}" class="btn-icon"
                           style="font-size:12px" onclick="event.stopPropagation()">✎</a>
                    @endcan
                </div>
                <div class="panel-body pad">
                    @if($team->description)
                        <div style="color:var(--text3);font-size:12px;margin-bottom:12px;line-height:1.5">
                            {{ Str::limit($team->description, 80) }}
                        </div>
                    @endif
                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <span style="font-family:var(--mono);font-size:10px;color:var(--text3)">
                            {{ $team->users_count }} {{ $team->users_count === 1 ? 'membro' : 'membri' }}
                        </span>
                        <x-badge :status="$team->is_active ? 'active' : 'inactive'" :label="$team->is_active ? 'Attivo' : 'Inattivo'" />
                    </div>
                </div>
            </div>
        @empty
            <div style="grid-column:1/-1;text-align:center;color:var(--text3);padding:48px">
                Nessun team creato.
            </div>
        @endforelse
    </div>
    {{ $teams->links() }}
</x-app-layout>
