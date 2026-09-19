<nav class="finance-nav" aria-label="Gestione entrate e uscite">
    @foreach(['expenses.index' => 'Spese', 'expenses.recurrences.index' => 'Ricorrenze', 'expenses.incomes.index' => 'Entrate manuali', 'expenses.documents.index' => 'Documenti', 'expenses.forecast' => 'Previsione'] as $route => $label)
        <a href="{{ route($route) }}" class="btn btn-g" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
