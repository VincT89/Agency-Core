<div>
    <x-page-header eyebrow="Fotografia" title="I Miei Shooting"></x-page-header>

    <x-panel padded>
        <div class="u-flex u-gap-md u-mb-lg">
            <input type="text" wire:model.live.debounce.300ms="search" class="form-in u-max-w-300" placeholder="Cerca per titolo o codice...">
            <select wire:model.live="status" class="form-in u-max-w-200">
                <option value="">Tutti gli stati</option>
                @foreach($statuses as $st)
                    <option value="{{ $st->value }}">{{ $st->labelForContext('photography') }}</option>
                @endforeach
            </select>
        </div>

        <table class="t-table u-w-full">
            <thead>
                <tr>
                    <th>Codice</th>
                    <th>Titolo / Progetto</th>
                    <th>Stato</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($shoots as $shoot)
                    <tr>
                        <td class="u-text-strong u-text-purple">{{ $shoot->code }}</td>
                        <td>
                            <div class="u-text-strong u-text-primary">{{ $shoot->title }}</div>
                            <div class="u-text-sm u-text-muted">{{ $shoot->project->name }}</div>
                        </td>
                        <td>
                            <x-shooting.status-badge :status="$shoot->status" context="photography" />
                        </td>
                        <td>
                            <a href="{{ route('photography.shooting.show', $shoot) }}" class="btn btn-outline btn-sm">Dettaglio</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="u-text-center u-p-xl u-text-muted">Nessuno shooting trovato.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="u-mt-lg">
            {{ $shoots->links() }}
        </div>
    </x-panel>
</div>
