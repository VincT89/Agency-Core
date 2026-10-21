    @php
        $currentStatus = request('status');
        $currentFiscalStatus = request('fiscal_status');
        $currentDocumentType = request('document_type');
        $currentSort = request('sort') ?: 'date_desc';
        $baseFilters = array_filter([
            'search' => request('search'),
            'document_number' => request('document_number'),
            'status' => $currentStatus,
            'fiscal_status' => $currentFiscalStatus,
            'document_type' => $currentDocumentType,
            'client_id' => request('client_id'),
            'start_date' => request('start_date'),
            'end_date' => request('end_date'),
            'sort' => request('sort'),
            'project_id' => request('project_id'),
            'service_type' => request('service_type'),
            'hosting_service_id' => request('hosting_service_id'),
            'min_amount' => request('min_amount'),
            'max_amount' => request('max_amount'),
        ], fn ($value) => filled($value));
    @endphp

    <div class="inv-filter-stack">
        <div class="inv-filter-groups">
            <div class="inv-filter-group" role="group" aria-labelledby="invoice-payment-filter-label">
                <div id="invoice-payment-filter-label" class="form-lbl">Stato dell’incasso (fatture)</div>
                <div class="pills">
                    <a data-finance-link href="{{ route('invoices.index', array_filter($baseFilters, fn ($value, $key) => $key !== 'status', ARRAY_FILTER_USE_BOTH)) }}"
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
                        <a data-finance-link href="{{ route('invoices.index', array_merge($baseFilters, ['status' => $status])) }}"
                           class="pill {{ $currentStatus === $status ? 'on' : '' }}" @if($currentStatus === $status) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            <div class="inv-filter-group" role="group" aria-labelledby="invoice-fiscal-filter-label">
                <div id="invoice-fiscal-filter-label" class="form-lbl">Stato fiscale</div>
                <div class="pills">
                    <a data-finance-link href="{{ route('invoices.index', array_filter($baseFilters, fn ($value, $key) => $key !== 'fiscal_status', ARRAY_FILTER_USE_BOTH)) }}"
                       class="pill {{ ! $currentFiscalStatus ? 'on' : '' }}" @if(! $currentFiscalStatus) aria-current="true" @endif>Tutti</a>
                    <a data-finance-link href="{{ route('invoices.index', array_merge($baseFilters, ['fiscal_status' => 'not_prepared'])) }}"
                       class="pill {{ $currentFiscalStatus === 'not_prepared' ? 'on' : '' }}" @if($currentFiscalStatus === 'not_prepared') aria-current="true" @endif>Da preparare</a>
                    <a data-finance-link href="{{ route('invoices.index', array_merge($baseFilters, ['fiscal_status' => 'ready'])) }}"
                       class="pill {{ $currentFiscalStatus === 'ready' ? 'on' : '' }}" @if($currentFiscalStatus === 'ready') aria-current="true" @endif>Pronte, non inviate</a>
                </div>
            </div>
        </div>

        @if($errors->any())<div class="u-alert-error" role="alert">Controlla i filtri evidenziati.</div>@endif
        <form method="GET" action="{{ route('invoices.index') }}" class="inv-search-form inv-document-filters" role="search" aria-label="Cerca fatture e note di credito" data-finance-form>
            @foreach(\Illuminate\Support\Arr::only($baseFilters, ['document_number', 'document_type', 'start_date', 'end_date', 'sort', 'min_amount', 'max_amount']) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            @if($currentStatus)
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            @if($currentFiscalStatus)
                <input type="hidden" name="fiscal_status" value="{{ $currentFiscalStatus }}">
            @endif
            <x-finance-lookup type="clients" name="client_id" label="Cliente" :value="request('client_id')" :text="\App\Models\Client::find(request('client_id'))?->name" />
            <div class="inv-search-field">
                <label for="invoices-search" class="form-lbl">Ricerca libera</label>
                <input id="invoices-search" data-finance-live type="search" name="search" value="{{ old('search', request('search')) }}"
                       placeholder="Numero o cliente" class="form-in" maxlength="100">
            </div>
            <div class="inv-search-field"><label for="invoice-service-type" class="form-lbl">Servizi collegati</label><select id="invoice-service-type" name="service_type" class="form-sel"><option value="">Tutti</option><option value="domain" @selected(request('service_type') === 'domain')>Domini</option><option value="hosting" @selected(request('service_type') === 'hosting')>Hosting</option></select></div>
            <x-finance-lookup type="projects" name="project_id" label="Progetto" :value="request('project_id')" :text="\App\Models\Project::find(request('project_id'))?->name" :client="request('client_id')" />
            @if(request('hosting_service_id'))<input type="hidden" name="hosting_service_id" value="{{ request('hosting_service_id') }}">@endif
            <div class="inv-search-actions">
                <button type="submit" class="btn btn-p">Cerca</button>
                @if($baseFilters)
                    <a data-finance-link href="{{ route('invoices.index') }}" class="btn btn-g">Azzera filtri</a>
                @endif
            </div>
        </form>
    </div>

    <x-panel class="inv-list" id="invoice-documents">
        <div class="t-table-wrap" role="region" aria-label="Elenco fatture e note di credito" tabindex="0">
            <table class="t-table">
                <thead>
                    <tr>
                        @foreach(['number' => 'Numero documento', 'type' => 'Tipo documento', 'date' => 'Data'] as $column => $label)
                            <x-invoice-column-filter :column="$column" :label="$label" :filters="$baseFilters" :sort="$currentSort" />
                        @endforeach
                        <th>Scadenza</th>
                        <th>Cliente</th>
                        <x-invoice-column-filter column="amount" label="Totale" :filters="$baseFilters" :sort="$currentSort" />
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
                                @elseif(! $isCredit && $document->hostingService)
                                    <div class="u-text-meta">{{ $document->hostingService->name }}</div>
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
        <div data-finance-pagination>{{ $documents->links() }}</div>
    </x-panel>
