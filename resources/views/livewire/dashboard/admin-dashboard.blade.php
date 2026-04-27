<div>
    <div class="kpi-strip">
        <div class="kpi-cell">
            <div class="kpi-label-t">Shooting Attivi</div>
            <div class="kpi-val-t">{{ $data->kpi_shooting_attivi }}</div>
            <div class="kpi-delta-t">In produzione</div>
        </div>
        
        <div class="kpi-cell {{ $data->kpi_waiting_client > 0 ? 'accent-line' : '' }}">
            <div class="kpi-label-t">In attesa Cliente</div>
            <div class="kpi-val-t {{ $data->kpi_waiting_client > 0 ? 'orange' : '' }}">{{ $data->kpi_waiting_client }}</div>
            <div class="kpi-delta-t {{ $data->kpi_waiting_client > 0 ? 'down' : '' }}">Da confermare</div>
        </div>
        
        <div class="kpi-cell {{ $data->kpi_client_rejected > 0 ? 'accent-line' : '' }}">
            <div class="kpi-label-t">Cliente Rifiutato</div>
            <div class="kpi-val-t {{ $data->kpi_client_rejected > 0 ? 'red' : '' }}">{{ $data->kpi_client_rejected }}</div>
            <div class="kpi-delta-t {{ $data->kpi_client_rejected > 0 ? 'down' : '' }}">Da rivedere</div>
        </div>

        <div class="kpi-cell">
            <div class="kpi-label-t">In attesa Fotografo</div>
            <div class="kpi-val-t">{{ $data->kpi_waiting_photographer }}</div>
            <div class="kpi-delta-t">In attesa risposta</div>
        </div>
        
        <div class="kpi-cell">
            <div class="kpi-label-t">Pianificati</div>
            <div class="kpi-val-t">{{ $data->kpi_scheduled }}</div>
            <div class="kpi-delta-t">Confermati e in calendario</div>
        </div>
    </div>

    <div style="font-size: 14px; font-weight: 600; color: var(--text1); margin: 24px 0 12px 0;">Social Media (Pubblicazioni)</div>
    <div class="kpi-strip">
        <div class="kpi-cell {{ $data->kpi_social_approved_not_scheduled > 0 ? 'accent-line' : '' }}">
            <div class="kpi-label-t">Da Pianificare</div>
            <div class="kpi-val-t {{ $data->kpi_social_approved_not_scheduled > 0 ? 'orange' : '' }}">{{ $data->kpi_social_approved_not_scheduled }}</div>
            <div class="kpi-delta-t {{ $data->kpi_social_approved_not_scheduled > 0 ? 'down' : '' }}">Approvati senza slot</div>
        </div>
        
        <div class="kpi-cell">
            <div class="kpi-label-t">Pianificati questa Sett.</div>
            <div class="kpi-val-t">{{ $data->kpi_social_scheduled_this_week }}</div>
            <div class="kpi-delta-t">In uscita nei prossimi giorni</div>
        </div>
        
        <div class="kpi-cell">
            <div class="kpi-label-t">Da Pubblicare Oggi</div>
            <div class="kpi-val-t {{ $data->kpi_social_publish_today > 0 ? 'blue' : '' }}">{{ $data->kpi_social_publish_today }}</div>
            <div class="kpi-delta-t">Azione manuale richiesta</div>
        </div>
    </div>

    <div class="mt-panel">
        <x-panel title="Workflow (Attention List)" dot="var(--accent)">
            @if(count($data->attention_list) === 0)
                <div style="text-align:center;color:var(--text3);padding:32px">
                    <i data-lucide="check-circle" style="width:32px; height:32px; margin-bottom:12px; opacity:0.5;"></i>
                    <div style="font-weight:500; font-size:14px; color:var(--text2);">Nessun blocco rilevato</div>
                    <div style="font-size:12px;">Nessuno shooting richiede l'intervento dell'admin.</div>
                </div>
            @else
                <table class="t-table">
                    <thead>
                        <tr>
                            <th>Shooting / Progetto</th>
                            <th>Stato Attuale</th>
                            <th style="text-align: right">Azione</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data->attention_list as $item)
                            <tr onclick="window.location='{{ $item->action_url }}'" style="cursor:pointer">
                                <td class="name-col">
                                    {{ $item->shoot_name }}
                                    <div style="font-size:12px;color:var(--text3);font-weight:normal;margin-top:4px">{{ $item->project_name }} • {{ $item->shoot_code }}</div>
                                </td>
                                <td>
                                    @php
                                        $color = $item->priority === 1 ? 'var(--orange)' : ($item->priority === 2 ? 'var(--red)' : 'var(--blue)');
                                    @endphp
                                    <span style="font-size:11px; font-weight:600; padding:4px 8px; border-radius:4px; background:var(--bg3); color:{{ $color }};">{{ $item->status_label }}</span>
                                </td>
                                <td style="text-align: right">
                                    <a href="{{ $item->action_url }}" class="btn btn-sm" style="background:var(--bg); border:1px solid var(--line); color:var(--text2); text-decoration:none;">{{ $item->action_label }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-panel>
    </div>
</div>
