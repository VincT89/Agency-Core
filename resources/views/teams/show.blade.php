<x-app-layout title="{{ $team->name }}">
    <div class="u-mb-lg u-flex-end">
        <a href="{{ route('teams.index') }}" wire:navigate class="btn btn-g u-flex-center u-gap-xs">
            <i data-lucide="arrow-left" class="u-icon-sm"></i> Torna alla lista
        </a>
    </div>
    <x-page-header
        eyebrow="Dettaglio · Team"
        
    >
    <x-slot:title><strong>{{ $team->name }}</strong></x-slot:title>
        <x-slot:actions>
            <x-badge :status="$team->is_active ? 'active' : 'inactive'" :label="$team->is_active ? 'Attivo' : 'Inattivo'" />
            @can('system.admin')
                <a href="{{ route('teams.edit', $team) }}" class="btn btn-g">Modifica</a>
            @endcan
            @can('system.admin')
                <x-delete-modal 
                    action="{{ route('teams.destroy', $team) }}" 
                    title="Elimina Team" 
                    message="Eliminare definitivamente il team '{{ $team->name }}'?"
                    confirmText="{{ $team->name }}">
                    <button type="button" class="btn btn-g btn-danger-outline">Elimina</button>
                </x-delete-modal>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="g-2col-main">
        <x-panel title="Membri del Team ({{ $team->users->count() }})" dot="var(--purple)">
            <table class="t-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Specializzazione</th>
                        <th>Ruolo in team</th>
                        <th>Stato</th>
                        <th>Entrato il</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($team->users as $member)
                    <tr>
                        <td class="name-col">
                            {{ $member->name }}
                            <div class="u-font-mono u-text-tiny u-text-muted">
                                {{ $member->role->value }}
                            </div>
                        </td>
                        <td class="u-text-sm u-text-muted">
                            {{ $member->primary_specialization ?? '—' }}
                        </td>
                        <td>
                            <x-badge :status="$member->pivot->role" :label="$member->pivot->role === 'lead' ? 'Responsabile' : 'Membro'" />
                        </td>
                        <td><x-badge :status="$member->status" :label="$member->status_label" /></td>
                        <td class="mono-col">
                            {{ $member->pivot->joined_at ? \Carbon\Carbon::parse($member->pivot->joined_at)->format('d/m/Y') : '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="u-text-center u-text-muted u-p-lg">
                            Nessun membro nel team.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>

        <div>
            <x-panel title="Info Team" dot="var(--teal)" padded>
                @if($team->description)
                <div class="form-g mb-2">
                    <div class="form-lbl">Descrizione</div>
                    <div class="u-text-sm u-text-muted u-leading-relaxed">{{ $team->description }}</div>
                </div>
                @endif
                <div class="form-g mb-2">
                    <div class="form-lbl">Stato</div>
                    <div><x-badge :status="$team->is_active ? 'active' : 'inactive'" :label="$team->is_active ? 'Attivo' : 'Inattivo'" /></div>
                </div>
                <div class="form-g mb-2">
                    <div class="form-lbl">Membri totali</div>
                    <div class="u-font-mono u-text-h4 u-text-strong">
                        {{ $team->users->count() }}
                    </div>
                </div>
                <div class="form-g">
                    <div class="form-lbl">Creato il</div>
                    <div class="u-font-mono u-text-muted">
                        {{ $team->created_at->isoFormat('D MMMM YYYY') }}
                    </div>
                </div>
            </x-panel>
        </div>
    </div>
</x-app-layout>
