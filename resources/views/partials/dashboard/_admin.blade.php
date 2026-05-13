<div class="kpi-strip">
    <a href="{{ route('clients.index') }}" class="kpi-cell">
        <div class="kpi-label-t">Clienti Attivi</div>
        <div class="kpi-val-t">{{ $activeClients }}</div>
        <div class="kpi-delta-t">Nel gestionale</div>
    </a>
    <a href="{{ route('hosting-services.index', ['status_filter' => 'expiring']) }}" class="kpi-cell {{ $expiringHosting > 0 ? 'accent-line' : '' }}">
        <div class="kpi-label-t">Rinnovi (30gg)</div>
        <div class="kpi-val-t {{ $expiredHosting > 0 ? 'red' : '' }}">
            {{ $expiringHosting }}
            @if($expiredHosting > 0)
                <span class="kpi-expired-text">+ {{ $expiredHosting }} scaduti</span>
            @endif
        </div>
        <div class="kpi-delta-t {{ $expiringHosting > 0 || $expiredHosting > 0 ? 'down' : '' }}">Domini & Hosting</div>
    </a>
    <a href="{{ route('tickets.index') }}" class="kpi-cell {{ $openTicketsCount > 0 ? 'accent-line' : '' }}">
        <div class="kpi-label-t">Ticket Aperti</div>
        <div class="kpi-val-t">{{ $openTicketsCount }}</div>
        <div class="kpi-delta-t {{ $openTicketsCount > 0 ? 'down' : '' }}">Richiedono attenzione</div>
    </a>
    <a href="{{ route('invoices.index', ['status' => 'overdue']) }}" class="kpi-cell {{ $overdueInvoices > 0 ? 'accent-line' : '' }}">
        <div class="kpi-label-t">Fatture Scadute</div>
        <div class="kpi-val-t">{{ $overdueInvoices }}</div>
        <div class="kpi-delta-t {{ $overdueInvoices > 0 ? 'down' : '' }}">Da sollecitare</div>
    </a>
    <a href="{{ route('tasks.index') }}" class="kpi-cell">
        <div class="kpi-label-t">Task in Scad.</div>
        <div class="kpi-val-t">{{ $expiringTasks->count() }}</div>
        <div class="kpi-delta-t">Prossimi 7 gg</div>
    </a>
</div>

<div class="g-2col u-mb-lg">
    <div>
        <div class="mt-panel">
            <x-panel title="Andamento Finanziario" dot="var(--green)" padded>
                <div class="dashboard-chart-card">
                    <div id="financial-chart" class="dashboard-chart-body"></div>
                </div>
            </x-panel>
        </div>
    </div>
    <div>
        <div class="mt-panel">
            <x-panel title="Andamento Operativo" dot="var(--accent)" padded>
                <div class="dashboard-chart-card">
                    <div id="operational-chart" class="dashboard-chart-body"></div>
                </div>
            </x-panel>
        </div>
    </div>
</div>

<div class="dash-grid">
    <div x-data="{ tab: 'tasks' }">
        <x-panel title="Panoramica Operativa" dot="var(--accent)">
            <x-slot:headerActions>
                <div class="tab-switcher no-margin">
                    <button @click="tab = 'tasks'" :class="tab === 'tasks' ? 'active' : ''" class="tab-btn">Task In
                        Scadenza</button>
                    <button @click="tab = 'tickets'" :class="tab === 'tickets' ? 'active' : ''" class="tab-btn">Ticket
                        Recenti</button>
                    <button @click="tab = 'domains'" :class="tab === 'domains' ? 'active' : ''" class="tab-btn">Domini in
                        Scadenza</button>
                </div>
            </x-slot:headerActions>

            <div x-show="tab === 'tasks'" x-cloak>
                <table class="t-table u-table-seamless">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Assegnato a</th>
                            <th>Data Scadenza</th>
                            <th>Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expiringTasks as $task)
                            <tr x-data @click="window.Livewire.navigate('{{ route('tasks.show', $task) }}')" class="u-cursor-pointer hover-bg">
                                <td class="name-col">{{ $task->title }}</td>
                                <td>{{ $task->assignee?->name ?? '—' }}</td>
                                <td class="mono-col {{ $task->due_date && $task->due_date < now() ? 'u-text-danger' : '' }}">
                                    {{ $task->due_date ? $task->due_date->format('d/m/Y') : '—' }}</td>
                                <td><x-badge :status="$task->status" :label="$task->status_label" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="u-text-center u-text-muted u-p-xl">Nessun task in
                                    scadenza nei prossimi 7 giorni.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div x-show="tab === 'tickets'" x-cloak>
                <table class="t-table u-table-seamless">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Commessa</th>
                            <th>Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTickets as $ticket)
                            <tr x-data @click="window.Livewire.navigate('{{ route('tickets.show', $ticket) }}')" class="u-cursor-pointer hover-bg">
                                <td class="name-col">{{ $ticket->title }}</td>
                                <td>{{ $ticket->project?->name ?? '—' }}</td>
                                <td><x-badge :status="$ticket->status" :label="$ticket->status_label" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="u-text-center u-text-muted u-p-xl">Nessun ticket
                                    recente</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div x-show="tab === 'domains'" x-cloak>
                <table class="t-table u-table-seamless">
                    <thead>
                        <tr>
                            <th>Dominio</th>
                            <th>Cliente</th>
                            <th>Scadenza</th>
                            <th>Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expiredHostingList as $hosting)
                            <tr x-data @click="window.Livewire.navigate('{{ route('hosting-services.show', $hosting) }}')" class="u-cursor-pointer hover-bg">
                                <td class="name-col">{{ $hosting->domain_name ?? $hosting->name }}</td>
                                <td>{{ $hosting->client?->name ?? '—' }}</td>
                                <td class="mono-col u-text-red">{{ $hosting->renewal_date?->format('d/m/Y') ?? '—' }}</td>
                                <td><x-badge status="expired" label="SCADUTO" /></td>
                            </tr>
                        @endforeach
                        @foreach($expiringHostingList as $hosting)
                            <tr x-data @click="window.Livewire.navigate('{{ route('hosting-services.show', $hosting) }}')" class="u-cursor-pointer hover-bg">
                                <td class="name-col">{{ $hosting->domain_name ?? $hosting->name }}</td>
                                <td>{{ $hosting->client?->name ?? '—' }}</td>
                                <td class="mono-col">{{ $hosting->renewal_date?->format('d/m/Y') ?? '—' }}</td>
                                <td><x-badge status="expiring" label="IN SCADENZA" /></td>
                            </tr>
                        @endforeach
                        @if($expiredHostingList->isEmpty() && $expiringHostingList->isEmpty())
                            <tr>
                                <td colspan="4" class="u-text-center u-text-muted u-p-xl">Nessun dominio in scadenza nei prossimi 30 giorni.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

        </x-panel>

        {{-- Appuntamenti della Settimana --}}
        <div class="mt-panel u-mb-lg">
            <x-panel title="Appuntamenti della Settimana" dot="var(--blue)">
                @if($weeklyEvents->isEmpty())
                    <div class="u-text-center u-text-muted u-p-lg">Nessun appuntamento nei prossimi 7
                        giorni.</div>
                @else
                    <table class="t-table">
                        <thead>
                            <tr>
                                <th>Evento</th>
                                <th>Data e Ora</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($weeklyEvents as $event)
                                <tr x-data @click="window.Livewire.navigate('{{ route('calendar-events.show', $event) }}')" class="u-cursor-pointer hover-bg">
                                    <td class="name-col">
                                        <div>{{ $event->title }}</div>
                                        @if($event->meeting_url)
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="u-ml-xs u-text-accent u-align-middle"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>
                                        @endif
                                        @if($event->location)
                                            <div class="u-text-xs u-text-muted u-mt-xs">
                                                <i data-lucide="map-pin" class="u-icon-xs u-align-middle"></i> {{ $event->location }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="mono-col">{{ $event->start_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-panel>
        </div>

        <div class="mt-panel">
            {{-- Shooting Overview --}}
            @livewire('admin.dashboard.shooting-overview')
        </div>

    </div>

    <div>

        <x-panel title="Attività Recenti" dot="var(--purple)" padded>
            @forelse($recentActivity as $log)
                <x-audit-item :log="$log" />
            @empty
                <div class="u-text-center u-text-muted u-p-md">Nessuna attività registrata.</div>
            @endforelse
        </x-panel>
    </div>
</div>

@push('scripts')
<script>
    function initDashboardCharts() {
        if (typeof ApexCharts === 'undefined') return;

        // --- Finanziario ---
        const financialEl = document.getElementById('financial-chart');
        if (financialEl) {
            const rect = financialEl.getBoundingClientRect();
            if (rect.width < 100) {
                requestAnimationFrame(() => initDashboardCharts());
                return;
            }
            if (window.financialChartInstance) {
                window.financialChartInstance.destroy();
                window.financialChartInstance = null;
            }
            const data = {!! $financialChartData !!};
            const tooltipFormatter = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });
            const options = {
                chart: { 
                    type: 'bar', 
                    height: 300, 
                    width: '100%',
                    toolbar: { show: false }, 
                    zoom: { enabled: false },
                    selection: { enabled: false },
                    background: 'transparent',
                    redrawOnParentResize: true,
                    redrawOnWindowResize: true,
                    parentHeightOffset: 0
                },
                series: data.series,
                xaxis: { categories: data.labels, tickPlacement: 'on', labels: { trim: false, hideOverlappingLabels: false, style: { colors: 'var(--text3)', fontFamily: 'var(--sans)' }, formatter: (val) => val ? val.split(' ')[0] : val } },
                yaxis: { labels: { style: { colors: 'var(--text3)', fontFamily: 'var(--mono)' }, formatter: (value) => value >= 1000 ? (value / 1000) + 'k €' : value + ' €' } },
                colors: ['var(--purple)', '#14b8a6', 'var(--orange)'],
                plotOptions: { bar: { columnWidth: '55%', borderRadius: 6, borderRadiusApplication: 'end' } },
                dataLabels: { enabled: false },
                stroke: { show: true, width: 2, colors: ['transparent'] },
                tooltip: { theme: 'dark', y: { formatter: function (val) { return tooltipFormatter.format(val) } } },
                grid: { borderColor: 'var(--line)', strokeDashArray: 4, opacity: 0.5, padding: { left: 8, right: 100 } }
            };
            window.financialChartInstance = new ApexCharts(financialEl, options);
            window.financialChartInstance.render();
            requestAnimationFrame(() => {
                window.financialChartInstance?.resize();
            });
        }

        // --- Operativo ---
        const operationalEl = document.getElementById('operational-chart');
        if (operationalEl) {
            const rect = operationalEl.getBoundingClientRect();
            if (rect.width < 100) {
                requestAnimationFrame(() => initDashboardCharts());
                return;
            }
            if (window.operationalChartInstance) {
                window.operationalChartInstance.destroy();
                window.operationalChartInstance = null;
            }
            const data = {!! $operationalChartData !!};
            const options = {
                chart: { 
                    type: 'area', 
                    height: 300, 
                    width: '100%',
                    toolbar: { show: false }, 
                    zoom: { enabled: false },
                    selection: { enabled: false },
                    background: 'transparent',
                    redrawOnParentResize: true,
                    redrawOnWindowResize: true,
                    parentHeightOffset: 0
                },
                series: data.series,
                xaxis: { categories: data.labels, tickPlacement: 'on', labels: { trim: false, hideOverlappingLabels: false, style: { colors: 'var(--text3)', fontFamily: 'var(--sans)' }, formatter: (val) => val ? val.split(' ')[0] : val } },
                yaxis: { labels: { style: { colors: 'var(--text3)', fontFamily: 'var(--mono)' }, formatter: (value) => value >= 1000 ? (value / 1000) + 'k' : value }, min: 0, forceNiceScale: true },
                colors: ['var(--purple)', '#ec4899'],
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
                stroke: { curve: 'smooth', width: 3, dashArray: [0, 5] },
                markers: { size: 4, colors: ['var(--purple)', '#ec4899'], strokeColors: '#ffffff', strokeWidth: 2, hover: { size: 6 } },
                dataLabels: { enabled: false },
                tooltip: { theme: 'dark' },
                grid: { borderColor: 'var(--line)', strokeDashArray: 4, opacity: 0.5, padding: { left: 8, right: 120 } }
            };
            window.operationalChartInstance = new ApexCharts(operationalEl, options);
            window.operationalChartInstance.render();
            requestAnimationFrame(() => {
                window.operationalChartInstance?.resize();
            });
        }
    }

    if (!window.dashboardChartsEventHandlersAdded) {
        window.dashboardChartsEventHandlersAdded = true;

        document.addEventListener('livewire:navigating', () => {
            if (window.financialChartInstance) {
                window.financialChartInstance.destroy();
                window.financialChartInstance = null;
            }
            if (window.operationalChartInstance) {
                window.operationalChartInstance.destroy();
                window.operationalChartInstance = null;
            }
        });

        document.addEventListener('livewire:navigated', () => {
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    initDashboardCharts();
                });
            });
        });
    }
</script>
@endpush