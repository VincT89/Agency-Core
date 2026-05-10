<x-app-layout title="Riepiloghi Economici">
    <x-page-header
        eyebrow="Finance"
        
        meta="Dashboard sintetica dei flussi di cassa"
    >
    <x-slot:title>Riepiloghi Economici</x-slot:title>
        <x-slot:actions>
            <form method="GET" action="{{ route('economic-summary.index') }}" class="finance-filter-form">
                <input type="date" name="from" value="{{ $from ?? '' }}" class="form-in finance-date-input" title="Dal">
                <span class="finance-filter-sep">-</span>
                <input type="date" name="to" value="{{ $to ?? '' }}" class="form-in finance-date-input" title="Al">
                <button type="submit" class="btn btn-p finance-btn-sm">Applica</button>
                @if($from || $to)
                    <a href="{{ route('economic-summary.index') }}" class="btn btn-g finance-btn-sm">Reset</a>
                @endif
            </form>
        </x-slot:actions>
    </x-page-header>

    @if($from || $to)
    <div class="finance-note-wrap">
        <x-panel padded class="finance-note-panel">
            <div class="finance-note-text">
                <strong class="u-text-main">Nota sul periodo:</strong> "Fatturato" e "Da Incassare" si basano sulla data di emissione delle fatture nel periodo. L'"Incassato" si basa sulla data dei pagamenti ricevuti nel periodo.
            </div>
        </x-panel>
    </div>
    @endif

    <div class="g-3col u-mb-lg">
        <x-panel padded>
            <x-stat-card 
                label="Fatturato" 
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
                sub="Credito sospeso isolato"
                color="var(--orange)"
                :subAlert="$globalSummary['total_outstanding'] > 0"
            />
        </x-panel>
    </div>

    <div class="g-3col u-mb-lg">
        <x-panel title="Trend Fatturato" dot="var(--purple)" padded>
            <div x-data="{
                initChart() {
                    const data = {{ $sparklineData }};
                    if (!window.ApexCharts) { setTimeout(() => this.initChart(), 200); return; }
                    
                    const formatter = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
                    const tooltipFormatter = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });
                    
                    const opt0 = {
                        chart: { type: 'area', height: 250, toolbar: { show: false }, background: 'transparent' },
                        series: [{ name: 'Fatturato', data: data.invoiced }],
                        xaxis: { categories: data.labels, labels: { style: { colors: 'var(--text3)', fontFamily: 'var(--sans)' }, formatter: (val) => { if (!val) return val; let p = val.split(' '); return p.length === 2 ? p[0] + ' ' + p[1].substring(2) : val; } } },
                        yaxis: { labels: { style: { colors: 'var(--text3)', fontFamily: 'var(--mono)' }, formatter: (value) => value >= 1000 ? (value / 1000) + 'k €' : value + ' €' }, min: 0, forceNiceScale: true },
                        colors: ['var(--purple)'],
                        stroke: { curve: 'smooth', width: 3 },
                        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
                        markers: { size: 4, colors: ['var(--purple)'], strokeColors: '#ffffff', strokeWidth: 2, hover: { size: 6 } },
                        dataLabels: { enabled: false },
                        grid: { borderColor: 'var(--line)', strokeDashArray: 4, opacity: 0.5 },
                        tooltip: { theme: 'dark', y: { formatter: function (val) { return tooltipFormatter.format(val) } } }
                    };
                    new window.ApexCharts(this.$refs.fatturato, opt0).render();
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
                    
                    const formatter = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
                    const tooltipFormatter = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });
                    
                    const opt1 = {
                        chart: { type: 'area', height: 250, toolbar: { show: false }, background: 'transparent' },
                        series: [{ name: 'Incassato', data: data.collected }],
                        xaxis: { categories: data.labels, labels: { style: { colors: 'var(--text3)', fontFamily: 'var(--sans)' }, formatter: (val) => { if (!val) return val; let p = val.split(' '); return p.length === 2 ? p[0] + ' ' + p[1].substring(2) : val; } } },
                        yaxis: { labels: { style: { colors: 'var(--text3)', fontFamily: 'var(--mono)' }, formatter: (value) => value >= 1000 ? (value / 1000) + 'k €' : value + ' €' }, min: 0, forceNiceScale: true },
                        colors: ['var(--teal)'],
                        stroke: { curve: 'smooth', width: 3 },
                        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
                        markers: { size: 4, colors: ['var(--teal)'], strokeColors: '#ffffff', strokeWidth: 2, hover: { size: 6 } },
                        dataLabels: { enabled: false },
                        grid: { borderColor: 'var(--line)', strokeDashArray: 4, opacity: 0.5 },
                        tooltip: { theme: 'dark', y: { formatter: function (val) { return tooltipFormatter.format(val) } } }
                    };
                    new window.ApexCharts(this.$refs.spark1, opt1).render();
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
                    
                    const formatter = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
                    const tooltipFormatter = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });
                    
                    const opt2 = {
                        chart: { type: 'area', height: 250, toolbar: { show: false }, background: 'transparent' },
                        series: [{ name: 'Da Incassare', data: data.pending }],
                        xaxis: { categories: data.labels, labels: { style: { colors: 'var(--text3)', fontFamily: 'var(--sans)' }, formatter: (val) => { if (!val) return val; let p = val.split(' '); return p.length === 2 ? p[0] + ' ' + p[1].substring(2) : val; } } },
                        yaxis: { labels: { style: { colors: 'var(--text3)', fontFamily: 'var(--mono)' }, formatter: (value) => value >= 1000 ? (value / 1000) + 'k €' : value + ' €' }, min: 0, forceNiceScale: true },
                        colors: ['var(--orange)'],
                        stroke: { curve: 'smooth', width: 3 },
                        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
                        markers: { size: 4, colors: ['var(--orange)'], strokeColors: '#ffffff', strokeWidth: 2, hover: { size: 6 } },
                        dataLabels: { enabled: false },
                        grid: { borderColor: 'var(--line)', strokeDashArray: 4, opacity: 0.5 },
                        tooltip: { theme: 'dark', y: { formatter: function (val) { return tooltipFormatter.format(val) } } }
                    };
                    new window.ApexCharts(this.$refs.spark2, opt2).render();
                }
            }" x-init="initChart()">
                <div x-ref="spark2" class="u-min-h-250"></div>
            </div>
        </x-panel>
    </div>

    {{-- Client Table --}}
    <div style="margin-bottom: 32px">
        <x-panel title="Riepilogo per Cliente" dot="var(--blue)">
            <table class="t-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th class="text-right">Fatturato</th>
                        <th class="text-right">Incassato</th>
                        <th class="text-right">Da Incassare</th>
                        <th class="text-center">Vol.</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summaryByClient as $cRow)
                    <tr>
                        <td class="name-col">{{ $cRow->client_name }}</td>
                        <td class="text-right mono-col">€ {{ number_format($cRow->total_invoiced, 2, ',', '.') }}</td>
                        <td class="text-right mono-col" style="color:var(--green)">€ {{ number_format($cRow->total_collected, 2, ',', '.') }}</td>
                        <td class="text-right mono-col" style="{{ $cRow->total_outstanding > 0 ? 'color:var(--red)' : '' }}">
                            € {{ number_format($cRow->total_outstanding, 2, ',', '.') }}
                        </td>
                        <td class="text-center"><span class="badge" style="background:var(--bg2)">{{ $cRow->invoices_count }}</span></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="text-align:center;color:var(--text3);padding:16px">Nessun dato economico nel perimetro.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>
    </div>

    {{-- Project Table --}}
    <x-panel title="Riepilogo per Progetto" dot="var(--teal)">
        <table class="t-table">
            <thead>
                <tr>
                    <th>Progetto</th>
                    <th>Cliente</th>
                    <th class="text-right">Fatturato</th>
                    <th class="text-right">Incassato</th>
                    <th class="text-right">Da Incassare</th>
                    <th class="text-center">Vol.</th>
                </tr>
            </thead>
            <tbody>
                @forelse($summaryByProject as $pRow)
                <tr>
                    <td class="name-col">{{ $pRow->project_name }}</td>
                    <td style="color:var(--text2)">{{ $pRow->client_name }}</td>
                    <td class="text-right mono-col">€ {{ number_format($pRow->total_invoiced, 2, ',', '.') }}</td>
                    <td class="text-right mono-col" style="color:var(--green)">€ {{ number_format($pRow->total_collected, 2, ',', '.') }}</td>
                    <td class="text-right mono-col" style="{{ $pRow->total_outstanding > 0 ? 'color:var(--red)' : '' }}">
                        € {{ number_format($pRow->total_outstanding, 2, ',', '.') }}
                    </td>
                    <td class="text-center"><span class="badge" style="background:var(--bg2)">{{ $pRow->invoices_count }}</span></td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:var(--text3);padding:16px">Nessun progetto con record economici.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </x-panel>
</x-app-layout>