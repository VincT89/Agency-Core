<div>
    <div class="kpi-strip" style="grid-template-columns: repeat(3, 1fr);">
        <div class="kpi-cell {{ $data->kpi_da_rispondere > 0 ? 'accent-line' : '' }}">
            <div class="kpi-label-t">Da Rispondere</div>
            <div class="kpi-val-t {{ $data->kpi_da_rispondere > 0 ? 'orange' : '' }}">{{ $data->kpi_da_rispondere }}</div>
            <div class="kpi-delta-t {{ $data->kpi_da_rispondere > 0 ? 'down' : '' }}">Nuove richieste</div>
        </div>
        
        <div class="kpi-cell">
            <div class="kpi-label-t">In Attesa Cliente</div>
            <div class="kpi-val-t">{{ $data->kpi_in_attesa_cliente }}</div>
            <div class="kpi-delta-t">Slot proposti</div>
        </div>
        
        <div class="kpi-cell">
            <div class="kpi-label-t">Pianificati</div>
            <div class="kpi-val-t">{{ $data->kpi_pianificati }}</div>
            <div class="kpi-delta-t">Confermati a calendario</div>
        </div>
    </div>

    <div class="dash-grid mt-panel">
        <div>
            <x-panel title="Il tuo lavoro adesso" dot="var(--accent)">
                @php
                    $hasWork = count($data->queue_da_rispondere) > 0 || count($data->queue_oggi) > 0 || count($data->queue_in_attesa_cliente) > 0;
                @endphp

                @if(!$hasWork)
                    <div style="text-align:center;color:var(--text3);padding:32px">
                        <i data-lucide="check-circle" style="width:32px; height:32px; margin-bottom:12px; opacity:0.5;"></i>
                        <div style="font-weight:500; font-size:14px; color:var(--text2);">Tutto in regola</div>
                        <div style="font-size:12px;">Non ci sono shooting che richiedono la tua attenzione al momento.</div>
                    </div>
                @else
                    <table class="t-table">
                        <thead>
                            <tr>
                                <th>Shooting / Progetto</th>
                                <th>Stato</th>
                                <th style="text-align: right">Azione</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_merge($data->queue_da_rispondere, $data->queue_oggi, $data->queue_in_attesa_cliente) as $item)
                                <tr onclick="window.location='{{ $item->action_url }}'" style="cursor:pointer">
                                    <td class="name-col">
                                        {{ $item->shoot_name }}
                                        <div style="font-size:12px;color:var(--text3);font-weight:normal;margin-top:4px">{{ $item->project_name }} • {{ $item->shoot_code }}</div>
                                    </td>
                                    <td>
                                        @php
                                            $color = $item->priority === 1 ? 'var(--orange)' : ($item->priority === 2 ? 'var(--text2)' : 'var(--blue)');
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

        <div>
            <x-panel title="Task in scadenza" dot="var(--blue)" padded style="margin-bottom: 20px;">
                @if(count($data->upcoming_tasks) > 0)
                    @foreach($data->upcoming_tasks as $task)
                        <div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--line);">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="width:4px; height:4px; border-radius:50%; background:var(--text3);"></div>
                                <a href="{{ route('tasks.show', $task->id) }}" style="font-weight:500;color:var(--text); text-decoration:none;">{{ $task->title }}</a>
                                <span style="color:var(--text3);">—</span>
                                <span style="font-size:13px; color:var(--text2);">
                                    @if($task->due_date->isToday())
                                        <span style="color:var(--orange); font-weight:600;">oggi</span>
                                    @elseif($task->due_date->isTomorrow())
                                        domani
                                    @elseif($task->due_date->isPast())
                                        <span style="color:var(--red); font-weight:600;">scaduto</span>
                                    @else
                                        {{ $task->due_date->format('d M') }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div style="color:var(--text3);text-align:center;padding:16px">Nessun task in scadenza.</div>
                @endif
            </x-panel>
        </div>
    </div>
</div>
