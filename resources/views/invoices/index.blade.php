<x-app-layout title="Fatture e note di credito">
    <x-page-header eyebrow="Amministrazione">
        <x-slot:title><strong>Fatture e note di credito</strong></x-slot:title>
        <x-slot:meta><span id="invoice-document-count">{{ $documents->total() }} documenti</span></x-slot:meta>
        <x-slot:actions>
            <a href="{{ route('billing-profile.edit') }}" class="btn btn-g">Dati fiscali</a>
            @can('create', App\Models\Invoice::class)
                <a href="{{ route('invoices.import') }}" class="btn btn-g">Importa fattura</a>
                <a href="{{ route('invoices.import.archive') }}" class="btn btn-g">Importa ZIP Aruba</a>
                <a href="{{ route('invoices.create') }}" class="btn btn-p">Nuova fattura</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="kpi-strip u-grid-3 inv-summary">
        <div class="kpi-cell">
            <div class="kpi-label-t">Da incassare</div>
            <div class="kpi-val-t">EUR {{ number_format($unpaidTotal, 2, ',', '.') }}</div>
            <div class="kpi-delta-t">Residuo effettivo delle fatture</div>
            <div class="kpi-delta-t">{{ $outstandingCount }} {{ $outstandingCount === 1 ? 'fattura con residuo aperto' : 'fatture con residuo aperto' }}</div>
        </div>
        <div class="kpi-cell {{ $overdueCount > 0 ? 'accent-line' : '' }}">
            <div class="kpi-label-t">Scadute</div>
            <div class="kpi-val-t {{ $overdueCount > 0 ? 'u-text-red' : '' }}">{{ $overdueCount }}</div>
            <div class="kpi-delta-t {{ $overdueCount > 0 ? 'down' : '' }}">Incassi oltre la scadenza</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label-t">Pronte, non inviate</div>
            <div class="kpi-val-t">{{ $readyFiscalCount }}</div>
            <div class="kpi-delta-t">Controllate e bloccate nel gestionale</div>
        </div>
    </div>

    <x-finance-region region="invoice-list">
        @include('invoices.partials.document-list')
    </x-finance-region>
</x-app-layout>
