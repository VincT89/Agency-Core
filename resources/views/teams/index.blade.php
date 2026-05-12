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
            <div x-data @click="window.Livewire.navigate('{{ route('teams.show', $team) }}')" class="panel u-cursor-pointer hover-bg">
                <div class="panel-header">
                    <div class="panel-title">
                        <div class="dot {{ $team->is_active ? 'u-bg-teal' : 'u-bg-muted' }}"></div>
                        {{ $team->name }}
                    </div>
                    @can('system.admin')
                        <a href="{{ route('teams.edit', $team) }}" class="btn-icon u-text-sm" @click.stop>✎</a>
                    @endcan
                </div>
                <div class="panel-body pad">
                    @if($team->description)
                        <div class="u-text-sm u-text-muted u-mb-sm u-leading-relaxed">
                            {{ Str::limit($team->description, 80) }}
                        </div>
                    @endif
                    <div class="u-flex-between">
                        <span class="u-font-mono u-text-10 u-text-muted">
                            {{ $team->users_count }} {{ $team->users_count === 1 ? 'membro' : 'membri' }}
                        </span>
                        <x-badge :status="$team->is_active ? 'active' : 'inactive'" :label="$team->is_active ? 'Attivo' : 'Inattivo'" />
                    </div>
                </div>
            </div>
        @empty
            <div class="g-col-full u-text-center u-text-muted u-p-xl">
                Nessun team creato.
            </div>
        @endforelse
    </div>
    {{ $teams->links() }}
</x-app-layout>
