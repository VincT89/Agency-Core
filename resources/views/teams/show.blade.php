<x-app-layout title="{{ $team->name }}">
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
                <form action="{{ route('teams.destroy', $team) }}" method="POST"
                      onsubmit="return confirm('Eliminare questo team? Questa azione non può essere annullata.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-g"
                            style="color:var(--red);border-color:rgba(245,75,75,.3)">Elimina</button>
                </form>
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
                            <div style="font-family:var(--mono);font-size:9px;color:var(--text3)">
                                {{ $member->role->value }}
                            </div>
                        </td>
                        <td style="color:var(--text2);font-size:12px">
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
                        <td colspan="5" style="text-align:center;color:var(--text3);padding:24px">
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
                    <div style="color:var(--text2);font-size:13px;line-height:1.6">{{ $team->description }}</div>
                </div>
                @endif
                <div class="form-g mb-2">
                    <div class="form-lbl">Stato</div>
                    <div><x-badge :status="$team->is_active ? 'active' : 'inactive'" :label="$team->is_active ? 'Attivo' : 'Inattivo'" /></div>
                </div>
                <div class="form-g mb-2">
                    <div class="form-lbl">Membri totali</div>
                    <div style="font-family:var(--mono);font-size:16px;font-weight:700;color:var(--text)">
                        {{ $team->users->count() }}
                    </div>
                </div>
                <div class="form-g">
                    <div class="form-lbl">Creato il</div>
                    <div style="font-family:var(--mono);color:var(--text2)">
                        {{ $team->created_at->isoFormat('D MMMM YYYY') }}
                    </div>
                </div>
            </x-panel>
        </div>
    </div>
</x-app-layout>
