@php
    $money = fn ($value) => '€ '.number_format((float) $value, 2, ',', '.');
    $pageUrl = route('economic-summary.index');
    $directorySorts = $group === 'categories'
        ? ['costs_desc' => 'Costi decrescenti', 'name_asc' => 'Nome A–Z', 'name_desc' => 'Nome Z–A']
        : ['turnover_desc' => 'Fatturato decrescente', 'turnover_asc' => 'Fatturato crescente', 'name_asc' => 'Nome A–Z', 'name_desc' => 'Nome Z–A', 'pending_desc' => 'Da incassare decrescente', 'costs_desc' => 'Costi decrescenti'];
@endphp
    <x-panel padded class="u-mb-lg">
        <nav class="finance-report-tabs" aria-label="Raggruppamento riepilogo">@foreach(['clients' => 'Clienti', 'projects' => 'Progetti', 'categories' => 'Categorie delle spese'] as $value => $label)<a data-finance-link href="{{ route('economic-summary.index', array_replace(\Illuminate\Support\Arr::except($baseFilters, ['directory_search', 'sort']), ['group' => $value])) }}" @if($group === $value) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
        <form method="GET" action="{{ $pageUrl }}" class="finance-directory-search {{ $group !== 'categories' ? 'finance-directory-receivables' : '' }}" data-finance-form>
            @foreach(\Illuminate\Support\Arr::except($baseFilters, ['directory_search','sort','pending_only']) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            @if(in_array($group, ['clients', 'projects']))
                <x-finance-lookup :type="$group" name="directory_search" id="directory-search" :label="$group === 'projects' ? 'Cerca progetto' : 'Cerca cliente'" :text="$filters['directory_search'] ?? ''" :free-text="true" :client="$filters['client_id'] ?? ''" />
            @else
                <div><label for="directory-search-lookup" class="form-lbl">Cerca categoria</label><input id="directory-search-lookup" type="search" name="directory_search" class="form-in" value="{{ $filters['directory_search'] ?? '' }}" maxlength="100" data-finance-live></div>
            @endif
            @if($group !== 'categories')<div><label for="directory-pending" class="form-lbl">Mostra</label><select id="directory-pending" name="pending_only" class="form-sel" data-finance-auto><option value="0">Tutti</option><option value="1" @selected($filters['pending_only'] ?? false)>Con fatture da saldare</option></select></div>@endif
            <div><label for="directory-sort" class="form-lbl">Ordina</label><select id="directory-sort" data-finance-auto name="sort" class="form-sel">@foreach($directorySorts as $value => $label)<option value="{{ $value }}" @selected(($filters['sort'] ?? (($filters['pending_only'] ?? false) ? 'pending_desc' : array_key_first($directorySorts))) === $value)>{{ $label }}</option>@endforeach</select></div><button type="submit" class="btn btn-g">Applica</button>
        </form>
        <div class="finance-directory">
            @forelse($directory as $entry)
                @php
                    $entryName = $group === 'projects' ? $entry->project_name : ($group === 'clients' ? $entry->client_name : $entry->name);
                @endphp
                <div class="finance-directory-entry">
                    <div><h3>{{ $entryName }}</h3>@if($group === 'projects')<p class="u-text-meta">{{ $entry->client_name }}</p>@endif
                        <p>{{ $group === 'categories' ? 'Spese: '.$money($entry->total_expenses) : 'Fatturato netto: '.$money($entry->total_invoiced) }}</p>
                        @if($group !== 'categories')
                            <p>Incassato: {{ $money($entry->total_collected) }} · Da incassare: <strong>{{ $money($entry->actual_outstanding) }}</strong></p>
                            @if($entry->open_invoices > 0)
                                <a href="{{ route('invoices.index', array_filter(['client_id' => $group === 'clients' ? $entry->client_id : ($filters['client_id'] ?? null), 'project_id' => $group === 'projects' ? $entry->project_id : ($filters['project_id'] ?? null), 'status' => 'pending', 'start_date' => $from, 'end_date' => $to, 'document_number' => $filters['q'] ?? null, 'min_amount' => $filters['min_amount'] ?? null, 'max_amount' => $filters['max_amount'] ?? null], fn ($value) => filled($value))) }}">Fatture da saldare ({{ $entry->open_invoices }})</a>
                            @endif
                        @endif
                    </div>
                    @if($group === 'categories')<a class="btn btn-g btn-sm" href="{{ route('expenses.index', ['category_id' => $entry->category_id ?? 'none', 'from' => $from, 'to' => $to]) }}">Apri spese</a>
                    @else<a class="btn btn-g btn-sm" aria-label="Apri dettaglio {{ $entryName }}" href="{{ route('economic-summary.show', array_merge($baseFilters, ['group' => $group, 'id' => $group === 'projects' ? $entry->project_id : $entry->client_id])) }}">Apri dettaglio</a>@endif
                </div>
            @empty<p class="u-p-md">Nessun risultato con questi filtri.</p>@endforelse
        </div>
        <div data-finance-pagination>{{ $directory->links() }}</div>
    </x-panel>
