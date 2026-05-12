<div>
    <div class="kpi-strip g-3col">
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
                    <div class="u-text-center u-text-muted u-p-xl">
                        <i data-lucide="check-circle" class="u-icon-lg u-mb-sm u-opacity-50"></i>
                        <div class="u-text-strong u-text-md u-text-secondary">Tutto in regola</div>
                        <div class="u-text-sm">Non ci sono shooting che richiedono la tua attenzione al momento.</div>
                    </div>
                @else
                    <table class="t-table">
                        <thead>
                            <tr>
                                <th>Shooting / Progetto</th>
                                <th>Stato</th>
                                <th class="u-text-right">Azione</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_merge($data->queue_da_rispondere, $data->queue_oggi, $data->queue_in_attesa_cliente) as $item)
                                <tr x-data @click="window.Livewire.navigate('{{ $item->action_url }}')" class="u-cursor-pointer hover-bg">
                                    <td class="name-col">
                                        {{ $item->shoot_name }}
                                        <div class="u-text-sm u-text-muted u-font-normal u-mt-xs">{{ $item->project_name }} • {{ $item->shoot_code }}</div>
                                    </td>
                                    <td>
                                        @php
                                            $colorClass = $item->priority === 1 ? 'u-text-warning' : ($item->priority === 2 ? 'u-text-secondary' : 'u-text-blue');
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

        <div>
            <x-panel title="Task in scadenza" dot="var(--blue)" padded class="u-mb-lg">
                @if(count($data->upcoming_tasks) > 0)
                    @foreach($data->upcoming_tasks as $task)
                        <div class="u-mb-sm u-pb-sm u-border-b">
                            <div class="u-flex u-items-center u-gap-xs">
                                <div class="u-dot-small"></div>
                                <a href="{{ route('tasks.show', $task->id) }}" class="u-text-strong u-text-base u-no-underline">{{ $task->title }}</a>
                                <span class="u-text-muted">—</span>
                                <span class="u-text-sm u-text-secondary">
                                    @if($task->due_date->isToday())
                                        <span class="u-text-warning u-text-strong">oggi</span>
                                    @elseif($task->due_date->isTomorrow())
                                        domani
                                    @elseif($task->due_date->isPast())
                                        <span class="u-text-danger u-text-strong">scaduto</span>
                                    @else
                                        {{ $task->due_date->format('d M') }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="u-text-center u-text-muted u-p-md">Nessun task in scadenza.</div>
                @endif
            </x-panel>
        </div>
    </div>
</div>
