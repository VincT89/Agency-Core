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

    <div class="kpi-strip u-grid-3 u-mb-lg">
        <div class="kpi-cell">
            <div class="kpi-label-t">Da incassare</div>
            <div class="kpi-val-t">€ {{ number_format($unpaidTotal, 2, ',', '.') }}</div>
            <div class="kpi-delta-t">Totale residuo aperto</div>
        </div>
        <div class="kpi-cell {{ $overdueCount > 0 ? 'accent-line' : '' }}">
            <div class="kpi-label-t">Scadute</div>
            <div class="kpi-val-t {{ $overdueCount > 0 ? 'u-text-red' : '' }}">{{ $overdueCount }}</div>
            <div class="kpi-delta-t {{ $overdueCount > 0 ? 'down' : '' }}">Fatture oltre termine</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label-t">Bozze in sospeso</div>
            <div class="kpi-val-t">{{ $draftCount }}</div>
            <div class="kpi-delta-t">Ancora da emettere</div>
        </div>
    </div>

    <div class="filter-bar">
        @php $currentStatus = request('status'); @endphp
        <div class="pills u-m-0">
            <a href="{{ route('invoices.index', array_filter(['search' => request('search')])) }}" class="pill {{ !$currentStatus ? 'on' : '' }}">Tutte</a>
            <a href="{{ route('invoices.index', array_filter(['status' => 'issued', 'search' => request('search')])) }}" class="pill {{ $currentStatus === 'issued' ? 'on' : '' }}">Emesse</a>
            <a href="{{ route('invoices.index', array_filter(['status' => 'partially_paid', 'search' => request('search')])) }}" class="pill {{ $currentStatus === 'partially_paid' ? 'on' : '' }}">Parziali</a>
            <a href="{{ route('invoices.index', array_filter(['status' => 'paid', 'search' => request('search')])) }}" class="pill {{ $currentStatus === 'paid' ? 'on' : '' }}">Pagate</a>
            <a href="{{ route('invoices.index', array_filter(['status' => 'overdue', 'search' => request('search')])) }}" class="pill {{ $currentStatus === 'overdue' ? 'on' : '' }}">Scadute</a>
            <a href="{{ route('invoices.index', array_filter(['status' => 'draft', 'search' => request('search')])) }}" class="pill {{ $currentStatus === 'draft' ? 'on' : '' }}">Bozze</a>
        </div>
        <form method="GET" action="{{ route('invoices.index') }}" class="u-flex u-gap-sm u-ml-auto">
            @if($currentStatus)
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cerca fattura o cliente..." class="form-in form-in-sm filter-search">
            @if(request('search') || $currentStatus)
                <a href="{{ route('invoices.index') }}" class="btn btn-g btn-sm">Reset</a>
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
                <tr x-data @click="window.Livewire.navigate('{{ route('invoices.show', $invoice) }}')" class="u-cursor-pointer hover-bg">
                    <td class="name-col">{{ $invoice->number }}</td>
                    <td class="mono-col">{{ $invoice->issue_date?->format('d/m/Y') }}</td>
                    <td class="mono-col {{ $invoice->status === 'overdue' ? 'u-text-red' : '' }}">{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</td>
                    <td>
                        <span class="u-text-sm u-text-strong">{{ $invoice->client?->name ?? '—' }}</span>
                        @if($invoice->project)
                            <div class="u-text-meta">{{ $invoice->project->name }}</div>
                        @endif
                    </td>
                    <td class="mono-col">€ {{ number_format($invoice->total, 2, ',', '.') }}</td>
                    <td class="mono-col {{ $invoice->residual > 0 ? 'u-text-orange' : 'u-text-green' }}">€ {{ number_format($invoice->residual, 2, ',', '.') }}</td>
                    <td><x-badge :status="$invoice->status" :label="$invoice->status_label" /></td>
                    <td>
                        @can('update', $invoice)
                            <a href="{{ route('invoices.edit', $invoice) }}" class="btn-icon" @click.stop>✎</a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="u-empty-state">Nessuna fattura trovata</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $invoices->links() }}
    </x-panel>
</x-app-layout>