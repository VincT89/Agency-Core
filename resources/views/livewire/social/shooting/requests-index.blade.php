<div>
    <x-page-header eyebrow="Social" title="Richieste Shooting">
        <x-slot name="actions">
            <a href="{{ route('social.shooting.create') }}" class="btn btn-p shooting-btn-icon">
                <i data-lucide="plus" class="shooting-icon-sm"></i> Nuova Richiesta
            </a>
        </x-slot>
    </x-page-header>

    <x-panel padded>
        <div class="shooting-filter-bar">
            <input type="text" wire:model.live.debounce.300ms="search" class="form-in shooting-input-md" placeholder="Cerca per titolo o codice...">
            <select wire:model.live="status" class="form-in shooting-select-sm">
                <option value="">Tutti gli stati</option>
                @foreach($statuses as $st)
                    <option value="{{ $st->value }}">{{ $st->labelForContext('social') }}</option>
                @endforeach
            </select>
        </div>

        <table class="t-table shooting-table-full">
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
                        <td class="shooting-code">{{ $shoot->code }}</td>
                        <td>
                            <div class="shooting-title">{{ $shoot->title }}</div>
                            <div class="shooting-project">{{ $shoot->project->name }}</div>
                        </td>
                        <td>
                            @if($shoot->photographer)
                                <div class="shooting-flex-center-gap8">
                                    <div class="avatar-sm">{{ substr($shoot->photographer->name, 0, 1) }}</div>
                                    <span class="shooting-photographer-name">{{ $shoot->photographer->name }}</span>
                                </div>
                            @else
                                <span class="shooting-unassigned">Non assegnato</span>
                            @endif
                        </td>
                        <td>
                            <x-shooting.status-badge :status="$shoot->status" context="social" />
                        </td>
                        <td class="shooting-date">{{ $shoot->created_at->format('d/m/Y') }}</td>
                        <td>
                            <a href="{{ route('social.shooting.show', $shoot) }}" class="btn btn-outline shooting-btn-xs">Dettaglio</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="shooting-empty-table">Nessuno shooting trovato.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="shooting-pagination">
            {{ $shoots->links() }}
        </div>
    </x-panel>
</div>
