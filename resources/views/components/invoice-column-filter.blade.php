@props(['column', 'label', 'filters', 'sort'])
@php
    $state = $sort === $column.'_asc' ? 'ascending' : ($sort === $column.'_desc' ? 'descending' : 'none');
    $fields = match($column) { 'number' => ['document_number'], 'type' => ['document_type'], 'date' => ['start_date', 'end_date'], default => ['min_amount', 'max_amount'] };
    $filtered = collect($fields)->contains(fn ($field) => filled($filters[$field] ?? null));
    $buttonId = 'invoice-column-'.$column;
@endphp
<th scope="col" class="inv-sort-heading" aria-sort="{{ $state }}" x-data="invoiceColumnFilter(@js($buttonId))"
    @invoice-filter-open.window="if ($event.detail !== @js($buttonId)) close()" @resize.window="place()" @scroll.window.capture="place()">
    <button id="{{ $buttonId }}" type="button" class="inv-sort-link" x-ref="trigger" @click="toggle()" :aria-expanded="open"
        aria-haspopup="dialog" aria-controls="{{ $buttonId }}-menu" aria-label="{{ $label }}: filtra e ordina" data-filtered="{{ $filtered ? 'true' : 'false' }}">
        <span>{{ $label }}</span><span class="inv-sort-indicator" aria-hidden="true"></span>
    </button>
    <template x-teleport="body">
        <div id="{{ $buttonId }}-menu" class="invoice-column-menu" x-ref="menu" x-show="open" x-cloak :style="position" role="dialog" aria-label="Filtra e ordina {{ mb_strtolower($label) }}"
            @click.outside="if (!$refs.trigger.contains($event.target)) close()" @keydown.escape.stop.prevent="close(); $refs.trigger.focus()">
            <h2>{{ $label }}</h2>
            @foreach(['asc' => $column === 'type' ? 'Prima le fatture (TD01)' : 'Ordine crescente', 'desc' => $column === 'type' ? 'Prima le note di credito (TD04)' : 'Ordine decrescente'] as $direction => $orderLabel)
                <a class="invoice-column-order" href="{{ route('invoices.index', array_replace($filters, ['sort' => $column.'_'.$direction])) }}"
                    aria-current="{{ $sort === $column.'_'.$direction ? 'true' : 'false' }}" @click.prevent="choose($el.href)">{{ $orderLabel }}</a>
            @endforeach
            <form method="GET" action="{{ route('invoices.index') }}" @submit.prevent="apply($event.target)">
                @foreach(\Illuminate\Support\Arr::except($filters, $fields) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                @if($column === 'number')
                    <label for="invoice-document-number" class="form-lbl">Cerca numero documento</label>
                    <input id="invoice-document-number" type="search" name="document_number" class="form-in" value="{{ $filters['document_number'] ?? '' }}" placeholder="Es. FPR 2/26, anche parziale" maxlength="100">
                @elseif($column === 'type')
                    <label for="invoice-document-type" class="form-lbl">Mostra</label>
                    <select id="invoice-document-type" name="document_type" class="form-sel">
                        <option value="">Fatture e note di credito</option>
                        <option value="TD01" @selected(($filters['document_type'] ?? '') === 'TD01')>Solo fatture (TD01)</option>
                        <option value="TD04" @selected(($filters['document_type'] ?? '') === 'TD04')>Solo note di credito (TD04)</option>
                    </select>
                @elseif($column === 'date')
                    <label for="invoice-start-date" class="form-lbl">Emessa dal</label><input id="invoice-start-date" type="date" name="start_date" class="form-in" value="{{ $filters['start_date'] ?? '' }}">
                    <label for="invoice-end-date" class="form-lbl">Emessa al</label><input id="invoice-end-date" type="date" name="end_date" class="form-in" value="{{ $filters['end_date'] ?? '' }}">
                @else
                    <label for="invoice-min" class="form-lbl">Importo minimo</label><input id="invoice-min" type="number" name="min_amount" min="0" step="0.01" class="form-in" value="{{ $filters['min_amount'] ?? '' }}">
                    <label for="invoice-max" class="form-lbl">Importo massimo</label><input id="invoice-max" type="number" name="max_amount" min="0" step="0.01" class="form-in" value="{{ $filters['max_amount'] ?? '' }}">
                @endif
                <div class="invoice-column-menu-actions"><button class="btn btn-p" type="submit">Applica</button><a class="btn btn-g" href="{{ route('invoices.index', \Illuminate\Support\Arr::except($filters, $fields)) }}" @click.prevent="choose($el.href)">Azzera filtro</a></div>
            </form>
        </div>
    </template>
</th>
