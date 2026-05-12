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

    <div class="u-text-md u-text-strong u-text-primary u-mt-xl u-mb-sm">Social Media (Pubblicazioni)</div>
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
        

    </div>

    <div class="dash-grid mt-panel">
        <div>
            <x-panel title="Workflow (Attention List)" dot="var(--accent)">
                @if(count($data->attention_list) === 0)
                    <div class="u-text-center u-text-muted u-p-xl">
                        <i data-lucide="check-circle" class="u-icon-lg u-mb-sm u-opacity-50"></i>
                        <div class="u-text-strong u-text-md u-text-secondary">Nessun blocco rilevato</div>
                        <div class="u-text-sm">Nessuno shooting richiede l'intervento dell'admin.</div>
                    </div>
                @else
                    <table class="t-table">
                        <thead>
                            <tr>
                                <th>Shooting / Progetto</th>
                                <th>Stato Attuale</th>
                                <th class="u-text-right">Azione</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data->attention_list as $item)
                                <tr x-data @click="window.Livewire.navigate('{{ $item->action_url }}')" class="u-cursor-pointer hover-bg">
                                    <td class="name-col">
                                        {{ $item->shoot_name }}
                                        <div class="u-text-sm u-text-muted u-font-normal u-mt-xs">{{ $item->project_name }} • {{ $item->shoot_code }}</div>
                                    </td>
                                    <td>
                                        @php
                                            $colorClass = $item->priority === 1 ? 'u-text-warning' : ($item->priority === 2 ? 'u-text-danger' : 'u-text-blue');
                                        @endphp
                                        <span class="u-badge-custom {{ $colorClass }}">{{ $item->status_label }}</span>
                                    </td>
                                    <td class="u-text-right">
                                        <a href="{{ $item->action_url }}" class="btn btn-sm btn-outline-secondary">{{ $item->action_label }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-panel>
        </div>
    </div>
</div>
