<x-app-layout title="Offerte commerciali">
    <div class="quotes-workspace">
        <x-page-header :meta="$total.' '.($total === 1 ? 'offerta' : 'offerte')">
            <x-slot:title>Offerte commerciali</x-slot:title>
            <x-slot:actions>
                <nav class="tab-switcher u-m-0" aria-label="Visualizzazione offerte">
                    @foreach(['list' => 'Lista', 'kanban' => 'Kanban'] as $mode => $label)
                        <a href="{{ route('quotes.index', array_replace($filters, ['view' => $mode])) }}"
                           @class(['tab-btn', 'active' => $viewMode === $mode]) @if($viewMode === $mode) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                @can('create', \App\Models\Quote::class)
                    <a href="{{ route('quote-services.index') }}" class="btn btn-g">Voci salvate</a>
                    <a href="{{ route('quotes.create') }}" class="btn btn-p">Nuova offerta</a>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <form method="GET" action="{{ route('quotes.index') }}" class="quote-list-filters" role="search" aria-label="Cerca offerte commerciali">
            <input type="hidden" name="view" value="{{ $viewMode }}">
            @if(isset($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            <x-form-group label="Cerca offerta" name="q">
                <input id="field-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-in" placeholder="Titolo, cliente o numero offerta" maxlength="160">
            </x-form-group>
            <x-form-group label="Cliente" name="client_id" for="client_id_search">
                <x-client-autocomplete :value="$selectedClient?->id" :text="$selectedClient?->name" :can-create="false" />
            </x-form-group>
            <div class="quote-filter-actions">
                <button class="btn btn-p" type="submit">Cerca</button>
                @if(isset($filters['q']) || isset($filters['client_id']) || isset($filters['status']))
                    <a href="{{ route('quotes.index', ['view' => $viewMode]) }}" class="btn btn-g">Azzera filtri</a>
                @endif
            </div>
        </form>

        <nav class="pills quote-status-filters" aria-label="Filtra offerte per stato">
            <a href="{{ route('quotes.index', array_diff_key($filters, ['status' => true])) }}"
               @class(['pill', 'on' => !isset($filters['status'])]) @if(!isset($filters['status'])) aria-current="page" @endif>
                Tutte <span>{{ $statusCounts->sum() }}</span>
            </a>
            @foreach($statuses as $status => $label)
                <a href="{{ route('quotes.index', array_replace($filters, ['status' => $status])) }}"
                   @class(['pill', 'on' => ($filters['status'] ?? null) === $status]) @if(($filters['status'] ?? null) === $status) aria-current="page" @endif>
                    {{ ['draft' => 'Bozze', 'presented' => 'Presentate', 'accepted' => 'Accettate', 'rejected' => 'Rifiutate'][$status] }}
                    <span>{{ $statusCounts[$status] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>

        @if($total === 0)
            <x-panel padded><p class="quote-list-empty">Nessuna offerta trovata{{ isset($filters['q']) || isset($filters['client_id']) || isset($filters['status']) ? ' con questi filtri' : '' }}.</p></x-panel>
        @elseif($viewMode === 'kanban')
            @include('quotes.partials.board')
        @else
            @include('quotes.partials.list')
        @endif
    </div>
</x-app-layout>
