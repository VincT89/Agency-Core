<x-panel title="Shooting da Gestire" dot="var(--purple)">
    <x-slot:headerActions>
        <a href="{{ route('admin.shooting.index') }}" class="social-header-link">
            Vedi tutti <i data-lucide="arrow-right" style="width:14px; height:14px;"></i>
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
    
    <div style="padding: 16px 16px 0 16px;">
        <div style="font-size:13px; font-weight:600; color:var(--text2); margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px;">Richiedono Azione</div>
    </div>
    @if(count($actionShoots) > 0)
        <table class="t-table" style="border-top:1px solid var(--line); margin-top:-1px;">
            <thead>
                <tr>
                    <th>Shooting</th>
                    <th>Progetto</th>
                    <th>Stato</th>
                    <th style="text-align: right">Azione</th>
                </tr>
            </thead>
            <tbody>
                @foreach($actionShoots as $shoot)
                    <tr onclick="window.location='{{ route('admin.shooting.show', $shoot) }}'" style="cursor:pointer">
                        <td class="name-col">{{ $shoot->title }}</td>
                        <td>{{ $shoot->project->name ?? 'Nessun Progetto' }}</td>
                        <td><x-shooting.status-badge :status="$shoot->status" context="admin" /></td>
                        <td style="text-align: right">
                            @php
                                $cta = 'Apri';
                                if ($shoot->status->value === 'waiting_client') $cta = 'Conferma Cliente';
                                elseif ($shoot->status->value === 'client_rejected') $cta = 'Rivedi';
                            @endphp
                            <span style="font-size:12px; font-weight:600; color:var(--purple); background:color-mix(in srgb, var(--purple) 15%, transparent); padding:4px 8px; border-radius:4px;">{{ $cta }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div style="padding:16px; margin: 0 16px 16px 16px; text-align:center; color:var(--text3); font-size:13px; background:var(--bg3); border-radius:8px;">
            Nessuno shooting recente.
        </div>
    @endif
</x-panel>
