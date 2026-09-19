<x-app-layout title="Fatture">
    <x-page-header eyebrow="Amministrazione" :meta="$invoices->total().' totali'">
        <x-slot:title><strong>Fatture</strong></x-slot:title>
        <x-slot:actions>
            <a href="{{ route('billing-profile.edit') }}" class="btn btn-g">Dati fiscali</a>
            @can('create', App\Models\Invoice::class)
                <a href="{{ route('invoices.import') }}" class="btn btn-g">Importa fattura</a>
                <a href="{{ route('invoices.create') }}" class="btn btn-p">Nuova fattura</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="kpi-strip u-grid-3 inv-summary">
        <div class="kpi-cell">
            <div class="kpi-label-t">Da incassare</div>
            <div class="kpi-val-t">EUR {{ number_format($unpaidTotal, 2, ',', '.') }}</div>
            <div class="kpi-delta-t">Residuo complessivo aperto</div>
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

    @php
        $currentStatus = request('status');
        $currentFiscalStatus = request('fiscal_status');
        $baseFilters = array_filter([
            'search' => request('search'),
            'status' => $currentStatus,
            'fiscal_status' => $currentFiscalStatus,
        ]);
    @endphp

    <div class="inv-filter-stack">
        <div class="inv-filter-groups">
            <div class="inv-filter-group" role="group" aria-labelledby="invoice-payment-filter-label">
                <div id="invoice-payment-filter-label" class="form-lbl">Stato dell’incasso</div>
                <div class="pills">
                    <a href="{{ route('invoices.index', array_filter($baseFilters, fn ($value, $key) => $key !== 'status', ARRAY_FILTER_USE_BOTH)) }}"
                       class="pill {{ ! $currentStatus ? 'on' : '' }}" @if(! $currentStatus) aria-current="true" @endif>Tutti</a>
                    @foreach([
                        'draft' => 'Bozze gestionali',
                        'issued' => 'Da incassare',
                        'partially_paid' => 'Parziali',
                        'paid' => 'Pagate',
                        'overdue' => 'Scadute',
                    ] as $status => $label)
                        <a href="{{ route('invoices.index', array_merge($baseFilters, ['status' => $status])) }}"
                           class="pill {{ $currentStatus === $status ? 'on' : '' }}" @if($currentStatus === $status) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            <div class="inv-filter-group" role="group" aria-labelledby="invoice-fiscal-filter-label">
                <div id="invoice-fiscal-filter-label" class="form-lbl">Stato fiscale</div>
                <div class="pills">
                    <a href="{{ route('invoices.index', array_filter($baseFilters, fn ($value, $key) => $key !== 'fiscal_status', ARRAY_FILTER_USE_BOTH)) }}"
                       class="pill {{ ! $currentFiscalStatus ? 'on' : '' }}" @if(! $currentFiscalStatus) aria-current="true" @endif>Tutti</a>
                    <a href="{{ route('invoices.index', array_merge($baseFilters, ['fiscal_status' => 'not_prepared'])) }}"
                       class="pill {{ $currentFiscalStatus === 'not_prepared' ? 'on' : '' }}" @if($currentFiscalStatus === 'not_prepared') aria-current="true" @endif>Da preparare</a>
                    <a href="{{ route('invoices.index', array_merge($baseFilters, ['fiscal_status' => 'ready'])) }}"
                       class="pill {{ $currentFiscalStatus === 'ready' ? 'on' : '' }}" @if($currentFiscalStatus === 'ready') aria-current="true" @endif>Pronte, non inviate</a>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('invoices.index') }}" class="inv-search-form" role="search" aria-label="Cerca fatture">
            @if($currentStatus)
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            @if($currentFiscalStatus)
                <input type="hidden" name="fiscal_status" value="{{ $currentFiscalStatus }}">
            @endif
            <div class="inv-search-field">
                <label for="invoices-search" class="form-lbl">Cerca fatture</label>
                <input id="invoices-search" type="search" name="search" value="{{ request('search') }}"
                       placeholder="Numero o cliente" class="form-in">
            </div>
            <div class="inv-search-actions">
                <button type="submit" class="btn btn-p">Cerca</button>
                @if(request('search') || $currentStatus || $currentFiscalStatus)
                    <a href="{{ route('invoices.index') }}" class="btn btn-g">Azzera filtri</a>
                @endif
            </div>
        </form>
    </div>

    <x-panel class="inv-list">
        <div class="t-table-wrap" role="region" aria-label="Elenco fatture" tabindex="0">
            <table class="t-table">
                <thead>
                    <tr>
                        <th>Riferimento</th>
                        <th>Numero fiscale</th>
                        <th>Data</th>
                        <th>Scadenza</th>
                        <th>Cliente</th>
                        <th>Totale</th>
                        <th>Residuo</th>
                        <th>Stato fiscale</th>
                        <th>Incasso</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr x-data
                            @click="window.Livewire.navigate('{{ route('invoices.show', $invoice) }}')"
                            @keydown.enter.self.prevent="window.Livewire.navigate('{{ route('invoices.show', $invoice) }}')"
                            @keydown.space.self.prevent="window.Livewire.navigate('{{ route('invoices.show', $invoice) }}')"
                            tabindex="0" role="link" aria-label="Apri fattura {{ $invoice->number }}"
                            class="u-cursor-pointer hover-bg">
                            <td class="name-col">{{ $invoice->number }}</td>
                            <td class="mono-col">{{ $invoice->fiscal_number ?? 'Non assegnato' }}</td>
                            <td class="mono-col">{{ $invoice->issue_date?->format('d/m/Y') }}</td>
                            <td class="mono-col {{ $invoice->status === 'overdue' ? 'u-text-red' : '' }}">
                                {{ $invoice->due_date?->format('d/m/Y') ?? 'Non indicata' }}
                            </td>
                            <td>
                                <span class="u-text-sm u-text-strong">
                                    {{ $invoice->client?->company_name ?: ($invoice->client?->name ?? 'Non disponibile') }}
                                </span>
                                @if($invoice->project)
                                    <div class="u-text-meta">{{ $invoice->project->name }}</div>
                                @endif
                            </td>
                            <td class="mono-col">
                                {{ $invoice->currency }} {{ number_format($invoice->total, 2, ',', '.') }}
                            </td>
                            <td class="mono-col {{ $invoice->residual > 0 ? 'u-text-orange' : 'u-text-green' }}">
                                {{ $invoice->currency }} {{ number_format($invoice->residual, 2, ',', '.') }}
                            </td>
                            <td>
                                <x-badge :status="$invoice->fiscal_badge_status"
                                         :label="$invoice->fiscal_status_label" />
                            </td>
                            <td><x-badge :status="$invoice->status" :label="$invoice->status_label" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="u-empty-state">Nessuna fattura trovata</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $invoices->links() }}
    </x-panel>
</x-app-layout>
