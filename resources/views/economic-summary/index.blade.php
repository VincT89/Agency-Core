<x-app-layout title="Riepiloghi Economici">
    <x-page-header
        eyebrow="Finance"
        
        meta="Dashboard sintetica dei flussi di cassa"
    >
    <x-slot:title>Riepiloghi Economici</x-slot:title>
        <x-slot:actions>
            <form method="GET" action="{{ route('economic-summary.index') }}" class="finance-filter-form">
                <label for="economic-from" class="sr-only">Data iniziale</label>
                <input id="economic-from" type="date" name="from" value="{{ old('from', $from ?? '') }}"
                       class="form-in finance-date-input @error('from') is-invalid @enderror">
                <span class="finance-filter-sep">-</span>
                <label for="economic-to" class="sr-only">Data finale</label>
                <input id="economic-to" type="date" name="to" value="{{ old('to', $to ?? '') }}"
                       class="form-in finance-date-input @error('to') is-invalid @enderror">
                <button type="submit" class="btn btn-p finance-btn-sm">Applica</button>
                @if($from || $to)
                    <a href="{{ route('economic-summary.index') }}" class="btn btn-g finance-btn-sm">Reset</a>
                @endif
            </form>
        </x-slot:actions>
    </x-page-header>

    @if($errors->any())
        <div class="u-alert-error u-mb-md" role="alert">
            Controlla il periodo: la data finale non può precedere quella iniziale.
        </div>
    @endif

    <div class="g-3col u-mb-lg">
        <x-panel padded>
            <x-stat-card 
                label="Fatturato (IVA esclusa)"
                value="€ {{ number_format($globalSummary['total_invoiced'], 2, ',', '.') }}" 
                sub="Da {{ $globalSummary['invoices_count'] }} fatture valide" 
                color="var(--purple)"
            />
        </x-panel>
        
        <x-panel padded>
            <x-stat-card 
                label="Incassato" 
                value="€ {{ number_format($globalSummary['total_collected'], 2, ',', '.') }}" 
                sub="Flusso cassa reale"
                color="var(--teal)"
            />
        </x-panel>
        
        <x-panel padded>
            <x-stat-card 
                label="Da Incassare" 
                value="€ {{ number_format($globalSummary['total_outstanding'], 2, ',', '.') }}" 
                sub="Residuo attuale delle fatture nel periodo"
                color="var(--orange)"
                :subAlert="$globalSummary['total_outstanding'] > 0"
            />
        </x-panel>
    </div>

    <div class="g-3col u-mb-lg">
        <x-panel title="Trend Fatturato (IVA esclusa)" dot="var(--purple)" padded>
            <div x-data="{
                initChart() {
                    const data = {{ $sparklineData }};
                    if (!window.ApexCharts) { setTimeout(() => this.initChart(), 200); return; }
                    const chartEl = this.$refs.fatturato;
                    const isCompactChart = chartEl.getBoundingClientRect().width < 520;
                    
                    const formatter = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
                    const tooltipFormatter = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });
                    
                    const opt0 = {
                        chart: { type: 'area', height: isCompactChart ? 230 : 250, width: '100%', parentHeightOffset: 0, toolbar: { show: false }, zoom: { enabled: false }, selection: { enabled: false }, background: 'transparent', redrawOnParentResize: true, redrawOnWindowResize: true },
                        series: [{ name: 'Fatturato (IVA esclusa)', data: data.invoiced }],
                        xaxis: {
                            categories: data.labels,
                            tickPlacement: 'on',
                            tickAmount: isCompactChart ? 6 : undefined,
                            labels: {
                                trim: true,
                                hideOverlappingLabels: true,
                                rotate: isCompactChart ? -45 : 0,
                                rotateAlways: isCompactChart,
                                style: { colors: 'var(--text3)', fontFamily: 'var(--sans)', fontSize: isCompactChart ? '9px' : '11px' },
                                formatter: (val) => val
                            }
                        },
                        yaxis: { labels: { style: { colors: 'var(--text3)', fontFamily: 'var(--mono)' }, formatter: (value) => Math.abs(value) >= 1000 ? (value / 1000) + 'k €' : value + ' €' }, forceNiceScale: true },
                        colors: ['var(--purple)'],
                        stroke: { curve: 'smooth', width: 3 },
                        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
                        markers: { size: 4, colors: ['var(--purple)'], strokeColors: '#ffffff', strokeWidth: 2, hover: { size: 6 } },
                        dataLabels: { enabled: false },
                        grid: { borderColor: 'var(--line)', strokeDashArray: 4, opacity: 0.5, padding: { left: isCompactChart ? 0 : 15, right: isCompactChart ? 8 : 28 } },
                        tooltip: { theme: 'dark', y: { formatter: function (val) { return tooltipFormatter.format(val) } } }
                    };
                    new window.ApexCharts(chartEl, opt0).render();
                }
            }" x-init="initChart()">
                <div x-ref="fatturato" class="u-min-h-250"></div>
            </div>
        </x-panel>

        <x-panel title="Trend Incassi" dot="var(--teal)" padded>
            <div x-data="{
                initChart() {
                    const data = {{ $sparklineData }};
                    if (!window.ApexCharts) { setTimeout(() => this.initChart(), 200); return; }
                    const chartEl = this.$refs.spark1;
                    const isCompactChart = chartEl.getBoundingClientRect().width < 520;
                    
                    const formatter = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
                    const tooltipFormatter = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });
                    
                    const opt1 = {
                        chart: { type: 'area', height: isCompactChart ? 230 : 250, width: '100%', parentHeightOffset: 0, toolbar: { show: false }, zoom: { enabled: false }, selection: { enabled: false }, background: 'transparent', redrawOnParentResize: true, redrawOnWindowResize: true },
                        series: [{ name: 'Incassato', data: data.collected }],
                        xaxis: {
                            categories: data.labels,
                            tickPlacement: 'on',
                            tickAmount: isCompactChart ? 6 : undefined,
                            labels: {
                                trim: true,
                                hideOverlappingLabels: true,
                                rotate: isCompactChart ? -45 : 0,
                                rotateAlways: isCompactChart,
                                style: { colors: 'var(--text3)', fontFamily: 'var(--sans)', fontSize: isCompactChart ? '9px' : '11px' },
                                formatter: (val) => val
                            }
                        },
                        yaxis: { labels: { style: { colors: 'var(--text3)', fontFamily: 'var(--mono)' }, formatter: (value) => value >= 1000 ? (value / 1000) + 'k €' : value + ' €' }, min: 0, forceNiceScale: true },
                        colors: ['var(--teal)'],
                        stroke: { curve: 'smooth', width: 3 },
                        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
                        markers: { size: 4, colors: ['var(--teal)'], strokeColors: '#ffffff', strokeWidth: 2, hover: { size: 6 } },
                        dataLabels: { enabled: false },
                        grid: { borderColor: 'var(--line)', strokeDashArray: 4, opacity: 0.5, padding: { left: isCompactChart ? 0 : 15, right: isCompactChart ? 8 : 28 } },
                        tooltip: { theme: 'dark', y: { formatter: function (val) { return tooltipFormatter.format(val) } } }
                    };
                    new window.ApexCharts(chartEl, opt1).render();
                }
            }" x-init="initChart()">
                <div x-ref="spark1" class="u-min-h-250"></div>
            </div>
        </x-panel>

        <x-panel title="Trend Da Incassare" dot="var(--orange)" padded>
            <div x-data="{
                initChart() {
                    const data = {{ $sparklineData }};
                    if (!window.ApexCharts) { setTimeout(() => this.initChart(), 200); return; }
                    const chartEl = this.$refs.spark2;
                    const isCompactChart = chartEl.getBoundingClientRect().width < 520;
                    
                    const formatter = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
                    const tooltipFormatter = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });
                    
                    const opt2 = {
                        chart: { type: 'area', height: isCompactChart ? 230 : 250, width: '100%', parentHeightOffset: 0, toolbar: { show: false }, zoom: { enabled: false }, selection: { enabled: false }, background: 'transparent', redrawOnParentResize: true, redrawOnWindowResize: true },
                        series: [{ name: 'Da Incassare', data: data.pending }],
                        xaxis: {
                            categories: data.labels,
                            tickPlacement: 'on',
                            tickAmount: isCompactChart ? 6 : undefined,
                            labels: {
                                trim: true,
                                hideOverlappingLabels: true,
                                rotate: isCompactChart ? -45 : 0,
                                rotateAlways: isCompactChart,
                                style: { colors: 'var(--text3)', fontFamily: 'var(--sans)', fontSize: isCompactChart ? '9px' : '11px' },
                                formatter: (val) => val
                            }
                        },
                        yaxis: { labels: { style: { colors: 'var(--text3)', fontFamily: 'var(--mono)' }, formatter: (value) => value >= 1000 ? (value / 1000) + 'k €' : value + ' €' }, min: 0, forceNiceScale: true },
                        colors: ['var(--orange)'],
                        stroke: { curve: 'smooth', width: 3 },
                        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
                        markers: { size: 4, colors: ['var(--orange)'], strokeColors: '#ffffff', strokeWidth: 2, hover: { size: 6 } },
                        dataLabels: { enabled: false },
                        grid: { borderColor: 'var(--line)', strokeDashArray: 4, opacity: 0.5, padding: { left: isCompactChart ? 0 : 15, right: isCompactChart ? 8 : 28 } },
                        tooltip: { theme: 'dark', y: { formatter: function (val) { return tooltipFormatter.format(val) } } }
                    };
                    new window.ApexCharts(chartEl, opt2).render();
                }
            }" x-init="initChart()">
                <div x-ref="spark2" class="u-min-h-250"></div>
            </div>
        </x-panel>
    </div>

    {{-- Client Table --}}
    <div class="u-mb-lg">
        <x-panel title="Riepilogo per Cliente" dot="var(--blue)">
            <div class="t-table-wrap" role="region" aria-label="Riepilogo per cliente, tabella scorrevole" tabindex="0">
            <table class="t-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th class="text-right">Fatturato (IVA esclusa)</th>
                        <th class="text-right">Incassato</th>
                        <th class="text-right">Da Incassare</th>
                        <th class="text-center">Costi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summaryByClient as $cRow)
                    <tr>
                        <td class="name-col">{{ $cRow->client_name }}</td>
                        <td class="text-right mono-col">€ {{ number_format($cRow->total_invoiced, 2, ',', '.') }}</td>
                        <td class="text-right mono-col u-text-green">€ {{ number_format($cRow->total_collected, 2, ',', '.') }}</td>
                        <td class="text-right mono-col {{ $cRow->total_outstanding > 0 ? 'u-text-red' : '' }}">
                            € {{ number_format($cRow->total_outstanding, 2, ',', '.') }}
                        </td>
                        <td class="text-center mono-col u-text-red">€ {{ number_format($cRow->total_expenses, 2, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="u-empty-state-sm">Nessun dato economico nel perimetro.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </x-panel>
    </div>

    {{-- Project Table --}}
    <x-panel title="Riepilogo per Progetto" dot="var(--teal)">
        <div class="t-table-wrap" role="region" aria-label="Riepilogo per progetto, tabella scorrevole" tabindex="0">
        <table class="t-table">
            <thead>
                <tr>
                    <th>Progetto</th>
                    <th>Cliente</th>
                    <th class="text-right">Fatturato (IVA esclusa)</th>
                    <th class="text-right">Incassato</th>
                    <th class="text-right">Da Incassare</th>
                    <th class="text-center">Costi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($summaryByProject as $pRow)
                <tr>
                    <td class="name-col">{{ $pRow->project_name }}</td>
                    <td class="u-text-muted">{{ $pRow->client_name }}</td>
                    <td class="text-right mono-col">€ {{ number_format($pRow->total_invoiced, 2, ',', '.') }}</td>
                    <td class="text-right mono-col u-text-green">€ {{ number_format($pRow->total_collected, 2, ',', '.') }}</td>
                    <td class="text-right mono-col {{ $pRow->total_outstanding > 0 ? 'u-text-red' : '' }}">
                        € {{ number_format($pRow->total_outstanding, 2, ',', '.') }}
                    </td>
                    <td class="text-center mono-col u-text-red">€ {{ number_format($pRow->total_expenses, 2, ',', '.') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="u-empty-state-sm">Nessun progetto con record economici.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </x-panel>
</x-app-layout>
