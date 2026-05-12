<div>
    <x-page-header eyebrow="Amministrazione" title="Gestione Shooting">
        <x-slot name="actions">
            <a href="{{ route('social.shooting.create') }}" class="btn btn-p u-flex u-items-center u-gap-xs">
                <i data-lucide="plus" class="u-icon-sm"></i> Nuova Richiesta
            </a>
        </x-slot>
    </x-page-header>

    <x-panel padded>
        <div class="u-flex u-gap-md u-mb-lg">
            <input type="text" wire:model.live.debounce.300ms="search" class="form-in u-max-w-300" placeholder="Cerca per titolo o codice...">
            <select wire:model.live="status" class="form-in u-max-w-200">
                <option value="">Tutti gli stati</option>
                @foreach($statuses as $st)
                    <option value="{{ $st->value }}">{{ $st->labelForContext('admin') }}</option>
                @endforeach
            </select>
        </div>

        <table class="t-table u-w-full">
            <thead>
                <tr>
                    <th>Codice</th>
                    <th>Titolo / Progetto</th>
                    <th>Fotografo</th>
                    <th>Stato</th>
                    <th>Data Creazione</th>
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
                            @if($shoot->photographer)
                                <div class="u-flex u-items-center u-gap-xs">
                                    <div class="avatar-sm">{{ substr($shoot->photographer->name, 0, 1) }}</div>
                                    <span class="u-text-sm u-text-secondary">{{ $shoot->photographer->name }}</span>
                                </div>
                            @else
                                <span class="u-text-sm u-text-muted">Non assegnato</span>
                            @endif
                        </td>
                        <td>
                            <x-shooting.status-badge :status="$shoot->status" context="admin" />
                        </td>
                        <td class="u-text-sm u-text-secondary">{{ $shoot->created_at->format('d/m/Y') }}</td>
                        <td>
                            <a href="{{ route('admin.shooting.show', $shoot) }}" class="btn btn-outline btn-sm">Gestisci</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="u-text-center u-p-xl u-text-muted">Nessuno shooting trovato.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="u-mt-lg">
            {{ $shoots->links() }}
        </div>
    </x-panel>
</div>
