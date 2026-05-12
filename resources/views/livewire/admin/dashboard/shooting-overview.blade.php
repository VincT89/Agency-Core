<x-panel title="Shooting da Gestire" dot="var(--purple)">
    <x-slot:headerActions>
        <a href="{{ route('admin.shooting.index') }}" class="social-header-link">
            Vedi tutti <i data-lucide="arrow-right" class="u-icon-xs"></i>
        </a>
    </x-slot:headerActions>
    
    <div class="g-3col shoot-kpi-row">
        <div class="shoot-stat-card neutral">
            <div class="shoot-stat-val">{{ $waitingPhotographer }}</div>
            <div class="shoot-stat-lbl">In attesa fotografo</div>
        </div>
        <div class="shoot-stat-card warn">
            <div class="shoot-stat-val">{{ $waitingClient }}</div>
            <div class="shoot-stat-lbl">In attesa cliente</div>
        </div>
        <div class="shoot-stat-card danger">
            <div class="shoot-stat-val">{{ $clientRejected }}</div>
            <div class="shoot-stat-lbl">Cliente Rifiutato</div>
        </div>
    </div>
    
    <div class="u-p-md u-pb-0">
        <div class="u-text-sm u-text-strong u-text-secondary u-mb-sm u-uppercase u-tracking-wide">Richiedono Azione</div>
    </div>
    @if(count($actionShoots) > 0)
        <table class="t-table u-table-seamless">
            <thead>
                <tr>
                    <th>Shooting</th>
                    <th>Progetto</th>
                    <th>Stato</th>
                    <th class="u-text-right">Azione</th>
                </tr>
            </thead>
            <tbody>
                @foreach($actionShoots as $shoot)
                    <tr x-data @click="window.Livewire.navigate('{{ route('admin.shooting.show', $shoot) }}')" class="u-cursor-pointer hover-bg">
                        <td class="name-col">{{ $shoot->title }}</td>
                        <td>{{ $shoot->project->name ?? 'Nessun Progetto' }}</td>
                        <td><x-shooting.status-badge :status="$shoot->status" context="admin" /></td>
                        <td class="u-text-right">
                            @php
                                $cta = 'Apri';
                                if ($shoot->status->value === 'waiting_client') $cta = 'Conferma Cliente';
                                elseif ($shoot->status->value === 'client_rejected') $cta = 'Rivedi';
                            @endphp
                            <span class="u-badge-custom-purple">{{ $cta }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="u-text-center u-text-muted u-text-sm u-p-md u-m-md u-bg-bg3 u-border-radius">
            Nessuno shooting recente.
        </div>
    @endif
</x-panel>
