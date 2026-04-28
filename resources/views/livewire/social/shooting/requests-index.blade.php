<div>
    <x-page-header eyebrow="Social" title="Richieste Shooting">
        <x-slot name="actions">
            <a href="{{ route('social.shooting.create') }}" class="btn btn-p" style="display:inline-flex; align-items:center; gap:6px;">
                <i data-lucide="plus" style="width:16px; height:16px;"></i> Nuova Richiesta
            </a>
        </x-slot>
    </x-page-header>

    <x-panel padded>
        <div style="display:flex; gap:16px; margin-bottom:24px;">
            <input type="text" wire:model.live.debounce.300ms="search" class="form-in" placeholder="Cerca per titolo o codice..." style="max-width:300px;">
            <select wire:model.live="status" class="form-in" style="max-width:200px;">
                <option value="">Tutti gli stati</option>
                @foreach($statuses as $st)
                    <option value="{{ $st->value }}">{{ $st->labelForContext('social') }}</option>
                @endforeach
            </select>
        </div>

        <table class="t-table" style="width:100%;">
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
                        <td style="font-weight:600; color:var(--purple);">{{ $shoot->code }}</td>
                        <td>
                            <div style="font-weight:500; color:var(--text1);">{{ $shoot->title }}</div>
                            <div style="font-size:12px; color:var(--text3);">{{ $shoot->project->name }}</div>
                        </td>
                        <td>
                            @if($shoot->photographer)
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div class="avatar-sm">{{ substr($shoot->photographer->name, 0, 1) }}</div>
                                    <span style="font-size:13px; color:var(--text2);">{{ $shoot->photographer->name }}</span>
                                </div>
                            @else
                                <span style="font-size:12px; color:var(--text3);">Non assegnato</span>
                            @endif
                        </td>
                        <td>
                            <x-shooting.status-badge :status="$shoot->status" context="social" />
                        </td>
                        <td style="font-size:13px; color:var(--text2);">{{ $shoot->created_at->format('d/m/Y') }}</td>
                        <td>
                            <a href="{{ route('social.shooting.show', $shoot) }}" class="btn btn-outline" style="padding:4px 8px; font-size:12px;">Dettaglio</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center; padding:32px; color:var(--text3);">Nessuno shooting trovato.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div style="margin-top:24px;">
            {{ $shoots->links() }}
        </div>
    </x-panel>
</div>
