<x-app-layout :title="$subject ? 'Riepilogo: '.$subject->name : 'Riepiloghi economici'">
    @php
        $money = fn ($value) => '€ '.number_format((float) $value, 2, ',', '.');
        $pageUrl = $subject ? route('economic-summary.show', ['group' => $subject instanceof \App\Models\Client ? 'clients' : 'projects', 'id' => $subject->id]) : route('economic-summary.index');
        $drillUrl = $pageUrl.'?'.http_build_query(array_replace($baseFilters, ['documents' => 1]));
        $metrics = ['total_invoiced' => 'Fatturato netto (IVA esclusa)', 'total_collected' => 'Incassato', 'total_outstanding' => 'Da incassare (IVA 22%)'];
        $directorySorts = $group === 'categories'
            ? ['costs_desc' => 'Costi decrescenti', 'name_asc' => 'Nome A–Z', 'name_desc' => 'Nome Z–A']
            : ['turnover_desc' => 'Fatturato decrescente', 'turnover_asc' => 'Fatturato crescente', 'name_asc' => 'Nome A–Z', 'name_desc' => 'Nome Z–A', 'pending_desc' => 'Da incassare decrescente', 'costs_desc' => 'Costi decrescenti'];
    @endphp
    @if($subject)<p class="u-mb-md"><a class="btn btn-g btn-sm" href="{{ route('economic-summary.index', \Illuminate\Support\Arr::except($baseFilters, ['client_id', 'project_id', 'documents'])) }}">Torna al riepilogo</a></p>@endif
    <x-page-header><x-slot:title>{{ $subject?->name ?? 'Riepiloghi economici' }}</x-slot:title>
        <x-slot:meta>{{ $subject ? 'Dettaglio economico e documenti collegati' : 'Consulta clienti, progetti e categorie delle spese' }}</x-slot:meta>
    </x-page-header>
    <form method="GET" action="{{ $pageUrl }}" class="finance-report-filters">
        <input type="hidden" name="group" value="{{ $group }}">
        @if($subject)<input type="hidden" name="{{ $subject instanceof \App\Models\Client ? 'client_id' : 'project_id' }}" value="{{ $subject->id }}">@endif
        <div class="finance-filter-grid">
            <x-form-group label="Dal" name="from" for="economic-from"><input id="economic-from" type="date" name="from" value="{{ old('from', $from) }}" class="form-in"></x-form-group>
            <x-form-group label="Al" name="to" for="economic-to"><input id="economic-to" type="date" name="to" value="{{ old('to', $to) }}" class="form-in"></x-form-group>
            <x-form-group label="Raggruppamento grafici" name="interval"><select id="field-interval" name="interval" class="form-sel">@foreach(['month' => 'Mensile', 'quarter' => 'Trimestrale', 'year' => 'Annuale'] as $value => $label)<option value="{{ $value }}" @selected(($filters['interval'] ?? 'month') === $value)>{{ $label }}</option>@endforeach</select></x-form-group>
            <div class="finance-filter-actions"><button type="submit" class="btn btn-p">Applica</button><a href="{{ $pageUrl }}" class="btn btn-g">Azzera filtri</a></div>
        </div>
        <details class="finance-advanced" @if(collect($filters)->except(['from','to','group','page','documents_page','interval'])->isNotEmpty()) open @endif>
            <summary>Filtri avanzati e confronto periodi</summary>
            <div class="finance-filter-grid">
                @unless($subject instanceof \App\Models\Client)<x-finance-lookup type="clients" name="client_id" label="Cliente" :value="$filters['client_id'] ?? ''" :text="$clientLabel" />@endunless
                @unless($subject instanceof \App\Models\Project)<x-finance-lookup type="projects" name="project_id" label="Progetto" :value="$filters['project_id'] ?? ''" :text="$projectLabel" :client="$filters['client_id'] ?? ''" />@endunless
                <x-form-group label="Numero documento o descrizione" name="q"><input id="field-q" type="search" name="q" class="form-in" value="{{ $filters['q'] ?? '' }}" maxlength="100"></x-form-group>
                <x-form-group label="Stato delle fatture" name="invoice_status"><select id="field-invoice-status" name="invoice_status" class="form-sel"><option value="">Tutte le fatture valide</option>@foreach(['issued' => 'Da incassare', 'partially_paid' => 'Parzialmente incassate', 'paid' => 'Incassate', 'overdue' => 'Scadute', 'credited' => 'Stornate'] as $value => $label)<option value="{{ $value }}" @selected(($filters['invoice_status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Importo documento minimo (IVA inclusa)" name="min_amount"><input id="field-min-amount" type="number" name="min_amount" class="form-in" min="0" step="0.01" value="{{ $filters['min_amount'] ?? '' }}"></x-form-group>
                <x-form-group label="Importo documento massimo (IVA inclusa)" name="max_amount"><input id="field-max-amount" type="number" name="max_amount" class="form-in" min="0" step="0.01" value="{{ $filters['max_amount'] ?? '' }}"></x-form-group>
                <x-form-group label="Categoria delle spese" name="category_id"><select id="field-category-id" name="category_id" class="form-sel" onchange="this.form.querySelector('[name=subcategory_id]').value='';this.form.requestSubmit()"><option value="">Tutte le categorie</option>@foreach($expenseCategories as $category)<option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Sottocategoria delle spese" name="subcategory_id"><select id="field-subcategory-id" name="subcategory_id" class="form-sel"><option value="">Tutte le sottocategorie</option>@foreach($expenseSubcategories as $category)<option value="{{ $category->id }}" @selected(($filters['subcategory_id'] ?? '') == $category->id)>{{ $category->name }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Stato delle spese" name="expense_status"><select id="field-expense-status" name="expense_status" class="form-sel"><option value="">Da pagare e pagate</option><option value="pending" @selected(($filters['expense_status'] ?? '') === 'pending')>Da pagare</option><option value="paid" @selected(($filters['expense_status'] ?? '') === 'paid')>Pagate</option></select></x-form-group>
                <x-form-group label="Confronta dal" name="compare_from"><input id="field-compare-from" type="date" name="compare_from" class="form-in" value="{{ $filters['compare_from'] ?? '' }}"></x-form-group>
                <x-form-group label="Confronta al" name="compare_to"><input id="field-compare-to" type="date" name="compare_to" class="form-in" value="{{ $filters['compare_to'] ?? '' }}"></x-form-group>
            </div>
            <p class="u-text-meta">Categoria, sottocategoria e stato delle spese filtrano i costi. Lo stato delle fatture filtra anche i relativi pagamenti e le note collegate.</p>
        </details>
    </form>
    @if($errors->any())<div class="u-alert-error u-mb-md" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <div class="g-3col u-mb-lg">
        @foreach($metrics as $key => $label)
            <x-panel padded><x-stat-card :label="$label" :value="$money($globalSummary[$key])" :sub="match($key) { 'total_invoiced' => 'Da '.$globalSummary['invoices_count'].' fatture valide', 'total_collected' => 'Flusso cassa reale', default => 'Fatturato netto × 1,22 − incassato' }" :color="match($key) { 'total_invoiced' => 'var(--purple)', 'total_collected' => 'var(--teal)', default => 'var(--orange)' }" />
                @if($comparison)<p class="finance-comparison">Confronto: {{ $money($comparison[$key]) }}<br>Differenza: {{ $money($globalSummary[$key] - $comparison[$key]) }}</p>@endif
            </x-panel>
        @endforeach
    </div>
    <x-panel padded class="u-mb-lg">
        <div x-data="{ metric: 0, plot: 'line' }">
            <div class="finance-chart-heading"><h2>Andamento nel periodo</h2><div class="finance-chart-controls"><label for="report-metric" class="sr-only">Dato del grafico</label><select id="report-metric" class="form-sel" x-model.number="metric">@foreach($chartData['series'] as $i => $series)<option value="{{ $i }}">{{ $series['name'] }}</option>@endforeach</select><label for="report-chart-type" class="sr-only">Tipo di grafico</label><select id="report-chart-type" class="form-sel" x-model="plot"><option value="line">Linee</option><option value="bar">Colonne</option></select></div></div>
            @foreach($chartData['series'] as $i => $series)
                @foreach(['line', 'bar'] as $display)<template x-if="metric === {{ $i }} && plot === '{{ $display }}'"><div x-data="financeChart(@js($chartData), @js($trendCharts[$i]), @js($drillUrl), @js($display))"><div x-ref="chart" role="img" aria-label="{{ $series['name'] }} nel periodo"></div></div></template>@endforeach
            @endforeach
            @if($comparison)<p class="u-text-meta">Confronto {{ \Carbon\Carbon::parse($filters['compare_from'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($filters['compare_to'])->format('d/m/Y') }}. I punti sono affiancati per posizione nel periodo.</p>@endif
            <details class="finance-advanced"><summary>Valori e documenti per periodo</summary>
                <div class="t-table-wrap" tabindex="0" role="region" aria-label="Valori del grafico"><table class="t-table"><thead><tr><th>Periodo</th>@foreach($metrics as $label)<th>{{ $label }}</th>@endforeach<th>Spese</th></tr></thead><tbody>
                    @foreach($chartData['labels'] as $i => $label)<tr><th><a href="{{ $pageUrl.'?'.http_build_query(array_replace($baseFilters, ['from' => max($from ?? '1900-01-01', $chartData['periods'][$i]['from']), 'to' => min($to ?? '2100-12-31', $chartData['periods'][$i]['to']), 'documents' => 1])) }}#report-documents">{{ $label }}</a></th>@foreach($chartData['series'] as $series)<td>{{ $money($series['data'][$i]) }}</td>@endforeach</tr>@endforeach
                </tbody></table></div>
            </details>
        </div>
    </x-panel>
    <div class="finance-distributions u-mb-lg">
        @foreach($distributionCharts as $chart)
        <x-panel padded>
            <h2 class="finance-chart-title">{{ $chart['title'] }}</h2>
            @if($chart['entries'])
                <div x-data="financeDistribution(@js($chart['options']), @js($chart['entries']), @js($chart['kind']))"><div x-ref="chart" role="img" aria-label="{{ $chart['title'] }}"></div></div>
                <details class="finance-advanced"><summary>Valori e dettagli</summary><ul class="finance-chart-values">@foreach($chart['entries'] as $entry)<li>@if($entry['url'])<a href="{{ $entry['url'] }}">{{ $entry['name'] }}</a>@else<span>{{ $entry['name'] }}</span>@endif<strong>{{ $money($entry['amount']) }}</strong></li>@endforeach</ul></details>
            @else<p class="u-text-meta u-p-md">Nessun dato con questi filtri.</p>@endif
        </x-panel>
        @endforeach
    </div>
    @unless($subject)
    <x-finance-region region="report-directory">
        @include('economic-summary.partials.directory')
    </x-finance-region>
    @endunless
    @if($documents)
    <x-panel padded class="u-mb-lg" id="report-documents">
        <h2 class="u-mb-md">Documenti e movimenti</h2>
        <p class="u-mb-md">Spese nel periodo: <strong>{{ $money($globalSummary['total_expenses']) }}</strong></p>
        <div class="finance-directory">
            @forelse($documents as $document)
                @php
                    $documentRoute = match($document->kind) { 'invoice' => 'invoices.show', 'credit_note' => 'credit-notes.show', 'payment' => 'payments.show', default => 'expenses.show' };
                    $kindLabel = match($document->kind) { 'invoice' => 'Fattura', 'credit_note' => 'Nota di credito', 'payment' => 'Incasso', default => 'Spesa' };
                    $amount = $document->kind === 'payment' ? $document->collected : ($document->kind === 'expense' ? $document->costs : $document->net);
                @endphp
                <div class="finance-directory-entry"><div><strong>{{ $kindLabel }} {{ $document->label }}</strong><p class="u-text-meta">{{ \Carbon\Carbon::parse($document->event_date)->format('d/m/Y') }} · {{ $money($amount) }}{{ in_array($document->kind, ['invoice','credit_note']) ? ' imponibile' : '' }}</p></div><a href="{{ route($documentRoute, $document->record_id) }}" class="btn btn-g btn-sm">Apri documento</a></div>
            @empty<p>Nessun documento nel periodo selezionato.</p>@endforelse
        </div>
        {{ $documents->links() }}
    </x-panel>
    @else<p class="u-mb-lg"><a href="{{ $drillUrl }}#report-documents" class="btn btn-g">Consulta i documenti del periodo</a></p>@endif
</x-app-layout>
