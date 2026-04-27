<x-app-layout title="Riepiloghi Economici">
    <x-page-header
        eyebrow="Finance"
        
        meta="Dashboard sintetica dei flussi di cassa"
    >
    <x-slot:title>Riepiloghi Economici</x-slot:title>
        <x-slot:actions>
            <form method="GET" action="{{ route('economic-summary.index') }}" style="display: flex; gap: 8px; align-items: center; margin: 0;">
                <input type="date" name="from" value="{{ $from ?? '' }}" class="form-in" style="height: 36px; padding: 0 12px; font-size: 13px;" >
    <x-slot:title>Dal</x-slot:title>
                <span style="color: var(--text3); font-size: 14px;">-</span>
                <input type="date" name="to" value="{{ $to ?? '' }}" class="form-in" style="height: 36px; padding: 0 12px; font-size: 13px;" title="Al">
                <button type="submit" class="btn btn-p" style="height: 36px;">Applica</button>
                @if($from || $to)
                    <a href="{{ route('economic-summary.index') }}" class="btn btn-g" style="height: 36px; display: inline-flex; align-items: center;">Reset</a>
                @endif
            </form>
        </x-slot:actions>
    </x-page-header>

    @if($from || $to)
    <div style="margin-bottom: 24px;">
        <x-panel padded style="background:var(--bg2);">
            <div style="font-size: 13px; color: var(--text2);">
                <strong style="color:var(--text1)">Nota sul periodo:</strong> "Fatturato" e "Da Incassare" si basano sulla data di emissione delle fatture nel periodo. L'"Incassato" si basa sulla data dei pagamenti ricevuti nel periodo.
            </div>
        </x-panel>
    </div>
    @endif

    <div class="g-3col" style="margin-bottom:32px;">
        <x-panel padded>
            <x-stat-card 
                label="Fatturato" 
                value="€ {{ number_format($globalSummary['total_invoiced'], 2, ',', '.') }}" 
                sub="Da {{ $globalSummary['invoices_count'] }} fatture valide" 
            />
        </x-panel>
        <x-panel padded>
            <x-stat-card 
                label="Incassato" 
                value="€ {{ number_format($globalSummary['total_collected'], 2, ',', '.') }}" 
                sub="Flusso cassa reale"
                dot="var(--green)"
            />
        </x-panel>
        <x-panel padded>
            <x-stat-card 
                label="Da Incassare" 
                value="€ {{ number_format($globalSummary['total_outstanding'], 2, ',', '.') }}" 
                sub="Credito sospeso isolato"
                dot="var(--red)"
                :highlight="$globalSummary['total_outstanding'] > 0"
                :subAlert="$globalSummary['total_outstanding'] > 0"
            />
        </x-panel>
    </div>

    <!-- Client Table -->
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

    <!-- Project Table -->
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