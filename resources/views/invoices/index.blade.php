<x-app-layout title="Fatture">
    <x-page-header
        eyebrow="Modulo · Amministrazione"
        
        :meta="$invoices->total() . ' totali'"
    >
    <x-slot:title><strong>Fatture</strong></x-slot:title>
        <x-slot:actions>
            @can('create', App\Models\Invoice::class)
                <a href="{{ route('invoices.create') }}" class="btn btn-p">+ Nuova fattura</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="kpi-strip" style="grid-template-columns: repeat(3, 1fr); margin-bottom:20px">
        <div class="kpi-cell">
            <div class="kpi-label-t">Da incassare</div>
            <div class="kpi-val-t">€ {{ number_format($unpaidTotal, 2, ',', '.') }}</div>
            <div class="kpi-delta-t">Totale residuo aperto</div>
        </div>
        <div class="kpi-cell {{ $overdueCount > 0 ? 'accent-line' : '' }}">
            <div class="kpi-label-t">Scadute</div>
            <div class="kpi-val-t" style="{{ $overdueCount > 0 ? 'color:var(--red)' : '' }}">{{ $overdueCount }}</div>
            <div class="kpi-delta-t {{ $overdueCount > 0 ? 'down' : '' }}">Fatture oltre termine</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label-t">Bozze in sospeso</div>
            <div class="kpi-val-t">{{ $draftCount }}</div>
            <div class="kpi-delta-t">Ancora da emettere</div>
        </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
        @php $currentStatus = request('status'); @endphp
        <div class="pills" style="margin:0">
            <a href="{{ route('invoices.index', array_filter(['search' => request('search')])) }}" class="pill {{ !$currentStatus ? 'on' : '' }}">Tutte</a>
            <a href="{{ route('invoices.index', array_filter(['status' => 'issued', 'search' => request('search')])) }}" class="pill {{ $currentStatus === 'issued' ? 'on' : '' }}">Emesse</a>
            <a href="{{ route('invoices.index', array_filter(['status' => 'partially_paid', 'search' => request('search')])) }}" class="pill {{ $currentStatus === 'partially_paid' ? 'on' : '' }}">Parziali</a>
            <a href="{{ route('invoices.index', array_filter(['status' => 'paid', 'search' => request('search')])) }}" class="pill {{ $currentStatus === 'paid' ? 'on' : '' }}">Pagate</a>
            <a href="{{ route('invoices.index', array_filter(['status' => 'overdue', 'search' => request('search')])) }}" class="pill {{ $currentStatus === 'overdue' ? 'on' : '' }}">Scadute</a>
            <a href="{{ route('invoices.index', array_filter(['status' => 'draft', 'search' => request('search')])) }}" class="pill {{ $currentStatus === 'draft' ? 'on' : '' }}">Bozze</a>
        </div>
        <form method="GET" action="{{ route('invoices.index') }}" style="display:flex;gap:8px;margin-left:auto">
            @if($currentStatus)
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cerca fattura o cliente..." class="form-in" style="padding:5px 10px;font-size:11px;width:200px">
            @if(request('search') || $currentStatus)
                <a href="{{ route('invoices.index') }}" class="btn btn-g" style="padding:5px 10px;font-size:11px">Reset</a>
            @endif
        </form>
    </div>

    <x-panel>
        <table class="t-table">
            <thead>
                <tr>
                    <th>Num / Rif</th>
                    <th>Data Emiss.</th>
                    <th>Scadenza</th>
                    <th>Cliente</th>
                    <th>Totale</th>
                    <th>Residuo</th>
                    <th>Stato</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                <tr onclick="window.location='{{ route('invoices.show', $invoice) }}'" style="cursor:pointer">
                    <td class="name-col">{{ $invoice->number }}</td>
                    <td class="mono-col">{{ $invoice->issue_date?->format('d/m/Y') }}</td>
                    <td class="mono-col" style="{{ $invoice->status === 'overdue' ? 'color:var(--red)' : '' }}">{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</td>
                    <td>
                        <span style="font-size:12px;color:var(--text)">{{ $invoice->client?->name ?? '—' }}</span>
                        @if($invoice->project)
                            <div style="font-family:var(--mono);font-size:10px;color:var(--text3)">{{ $invoice->project->name }}</div>
                        @endif
                    </td>
                    <td class="mono-col">€ {{ number_format($invoice->total, 2, ',', '.') }}</td>
                    <td class="mono-col" style="{{ $invoice->residual > 0 ? 'color:var(--yellow)' : 'color:var(--green)' }}">€ {{ number_format($invoice->residual, 2, ',', '.') }}</td>
                    <td><x-badge :status="$invoice->status" :label="$invoice->status_label" /></td>
                    <td>
                        @can('update', $invoice)
                            <a href="{{ route('invoices.edit', $invoice) }}" class="btn-icon" onclick="event.stopPropagation()">✎</a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;color:var(--text3);padding:32px">Nessuna fattura trovata</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $invoices->links() }}
    </x-panel>
</x-app-layout>