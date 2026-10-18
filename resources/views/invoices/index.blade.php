<x-app-layout title="Fatture e note di credito">
    <x-page-header eyebrow="Amministrazione" :meta="$documents->total().' documenti'">
        <x-slot:title><strong>Fatture e note di credito</strong></x-slot:title>
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

    @php
        $currentStatus = request('status');
        $currentFiscalStatus = request('fiscal_status');
        $currentDocumentType = request('document_type');
        $baseFilters = array_filter([
            'search' => request('search'),
            'document_number' => request('document_number'),
            'status' => $currentStatus,
            'fiscal_status' => $currentFiscalStatus,
            'document_type' => $currentDocumentType,
            'client_id' => request('client_id'),
            'start_date' => request('start_date'),
            'end_date' => request('end_date'),
        ], fn ($value) => filled($value));
    @endphp

    <div class="inv-filter-stack">
        <div class="inv-filter-groups">
            <div class="inv-filter-group" role="group" aria-labelledby="invoice-payment-filter-label">
                <div id="invoice-payment-filter-label" class="form-lbl">Stato dell’incasso (fatture)</div>
                <div class="pills">
                    <a href="{{ route('invoices.index', array_filter($baseFilters, fn ($value, $key) => $key !== 'status', ARRAY_FILTER_USE_BOTH)) }}"
                       class="pill {{ ! $currentStatus ? 'on' : '' }}" @if(! $currentStatus) aria-current="true" @endif>Tutti</a>
                    @foreach([
                        'draft' => 'Bozze gestionali',
                        'issued' => 'Da incassare',
                        'partially_paid' => 'Parziali',
                        'paid' => 'Pagate',
                        'overdue' => 'Scadute',
                        'credited' => 'Stornate',
                        'cancelled' => 'Annullate',
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

        @if($errors->any())<div class="u-alert-error" role="alert">Controlla i filtri evidenziati.</div>@endif
        <form method="GET" action="{{ route('invoices.index') }}" class="inv-search-form inv-document-filters" role="search" aria-label="Cerca fatture e note di credito">
            @if($currentStatus)
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            @if($currentFiscalStatus)
                <input type="hidden" name="fiscal_status" value="{{ $currentFiscalStatus }}">
            @endif
            @if(request('client_id'))<input type="hidden" name="client_id" value="{{ request('client_id') }}">@endif
            <div class="inv-search-field">
                <label for="invoices-search" class="form-lbl">Ricerca libera</label>
                <input id="invoices-search" type="search" name="search" value="{{ old('search', request('search')) }}"
                       placeholder="Numero o cliente" class="form-in" maxlength="100">
            </div>
            <div class="inv-search-field">
                <label for="invoice-document-number" class="form-lbl">Numero documento</label>
                <input id="invoice-document-number" type="search" name="document_number" value="{{ old('document_number', request('document_number')) }}"
                       placeholder="Es. FPR 2/26, anche parziale" class="form-in @error('document_number') is-invalid @enderror" maxlength="100"
                       @error('document_number') aria-invalid="true" aria-describedby="invoice-number-error" @enderror>
                @error('document_number')<p id="invoice-number-error" class="u-text-red u-text-sm">{{ $message }}</p>@enderror
            </div>
            <div class="inv-search-field">
                <label for="invoice-document-type" class="form-lbl">Tipo documento</label>
                <select id="invoice-document-type" name="document_type" class="form-sel">
                    <option value="">Tutti i documenti</option>
                    <option value="TD01" @selected($currentDocumentType === 'TD01')>Fatture (TD01)</option>
                    <option value="TD04" @selected($currentDocumentType === 'TD04')>Note di credito (TD04)</option>
                </select>
            </div>
            <div class="inv-search-field inv-date-field">
                <label for="invoice-start-date" class="form-lbl">Emessa dal</label>
                <input id="invoice-start-date" type="date" name="start_date" value="{{ old('start_date', request('start_date')) }}"
                       class="form-in @error('start_date') is-invalid @enderror"
                       @error('start_date') aria-invalid="true" aria-describedby="invoice-start-error" @enderror>
                @error('start_date')<p id="invoice-start-error" class="u-text-red u-text-sm">{{ $message }}</p>@enderror
            </div>
            <div class="inv-search-field inv-date-field">
                <label for="invoice-end-date" class="form-lbl">Emessa al</label>
                <input id="invoice-end-date" type="date" name="end_date" value="{{ old('end_date', request('end_date')) }}"
                       class="form-in @error('end_date') is-invalid @enderror"
                       @error('end_date') aria-invalid="true" aria-describedby="invoice-end-error" @enderror>
                @error('end_date')<p id="invoice-end-error" class="u-text-red u-text-sm">La data finale deve essere valida e non precedere quella iniziale.</p>@enderror
            </div>
            <div class="inv-search-actions">
                <button type="submit" class="btn btn-p">Cerca</button>
                @if($baseFilters)
                    <a href="{{ route('invoices.index') }}" class="btn btn-g">Azzera filtri</a>
                @endif
            </div>
        </form>
    </div>

    <x-panel class="inv-list">
        <div class="t-table-wrap" role="region" aria-label="Elenco fatture e note di credito" tabindex="0">
            <table class="t-table">
                <thead>
                    <tr>
                        <th>Numero documento</th>
                        <th>Tipo documento</th>
                        <th>Data</th>
                        <th>Scadenza</th>
                        <th>Cliente</th>
                        <th>Totale</th>
                        <th>Residuo / credito</th>
                        <th>Stato fiscale</th>
                        <th>Incasso / collegamento</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $document)
                        @php
                            $isCredit = $document instanceof \App\Models\CreditNote;
                            $number = $isCredit ? $document->number : $document->display_number;
                            $detailUrl = route($isCredit ? 'credit-notes.show' : 'invoices.show', $document);
                            $remaining = $isCredit ? $document->unallocated_total : $document->residual;
                        @endphp
                        <tr x-data
                            @click="window.Livewire.navigate('{{ $detailUrl }}')"
                            @keydown.enter.self.prevent="window.Livewire.navigate('{{ $detailUrl }}')"
                            @keydown.space.self.prevent="window.Livewire.navigate('{{ $detailUrl }}')"
                            tabindex="0" role="link" aria-label="Apri {{ $isCredit ? 'nota di credito' : 'fattura' }} {{ $number }}"
                            class="u-cursor-pointer hover-bg">
                            <td class="name-col">
                                <a href="{{ $detailUrl }}" wire:navigate @click.stop>{{ $number }}</a>
                                @if($isCredit)
                                    @foreach($document->allocations as $allocation)
                                        <div class="u-text-meta">Collegata a <a href="{{ route('invoices.show', $allocation->invoice) }}" wire:navigate @click.stop>{{ $allocation->invoice->display_number }}</a></div>
                                    @endforeach
                                @endif
                            </td>
                            <td class="mono-col">{{ $document->fiscal_document_type }}@if($isCredit)<div class="u-text-meta">Nota di credito</div>@endif</td>
                            <td class="mono-col">{{ $document->issue_date?->format('d/m/Y') }}</td>
                            <td class="mono-col {{ ! $isCredit && $document->is_overdue ? 'u-text-red' : '' }}">
                                {{ $isCredit ? 'Non prevista' : ($document->due_date?->format('d/m/Y') ?? 'Non indicata') }}
                                @if(! $isCredit && $document->is_overdue)<div><x-badge status="overdue" label="Scaduta" /></div>@endif
                            </td>
                            <td>
                                <span class="u-text-sm u-text-strong">
                                    {{ $document->client?->company_name ?: ($document->client?->name ?? 'Non disponibile') }}
                                </span>
                                @if(! $isCredit && $document->project)
                                    <div class="u-text-meta">{{ $document->project->name }}</div>
                                @endif
                            </td>
                            <td class="mono-col">
                                {{ $document->currency }} {{ number_format($document->total, 2, ',', '.') }}
                            </td>
                            <td class="mono-col {{ ! $isCredit ? ($remaining > 0 ? 'u-text-orange' : 'u-text-green') : '' }}">
                                {{ $document->currency }} {{ number_format($remaining, 2, ',', '.') }}
                                @if($isCredit)<div class="u-text-meta">Credito da collegare</div>@endif
                            </td>
                            <td>
                                <x-badge :status="$isCredit ? 'issued' : $document->fiscal_badge_status"
                                         :label="$isCredit ? \App\Enums\Finance\InvoiceFiscalStatus::Imported->label() : $document->fiscal_status_label" />
                            </td>
                            <td>
                                @if($isCredit)
                                    {{ $remaining <= 0 ? 'Collegata' : ($document->allocations->isEmpty() ? 'Da collegare' : 'Collegata parzialmente') }}
                                @else
                                    <x-badge :status="$document->status" :label="$document->status_label" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="u-empty-state">Nessun documento trovato</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $documents->links() }}
    </x-panel>
</x-app-layout>
