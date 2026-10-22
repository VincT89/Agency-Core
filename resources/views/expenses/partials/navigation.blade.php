@php
    $activeSection = match (true) {
        request()->routeIs('expenses.recurrences.*') => 'expenses.recurrences.index',
        ($manualOnly ?? false) || request()->routeIs('expenses.manual.*') => 'expenses.manual.index',
        request()->routeIs('expenses.documents.*') => 'expenses.documents.index',
        request()->routeIs('expenses.forecast*') => 'expenses.forecast',
        default => 'expenses.index',
    };
@endphp
<nav class="finance-report-tabs expense-navigation" aria-label="Gestione entrate e uscite" x-data
    x-init="$nextTick(() => { const active = $el.querySelector('[aria-current=page]'); if (active) $el.scrollLeft = active.offsetLeft - ($el.clientWidth - active.offsetWidth) / 2; })">
    @foreach(['expenses.index' => 'Spese', 'expenses.recurrences.index' => 'Ricorrenze', 'expenses.manual.index' => 'Uscite manuali', 'expenses.documents.index' => 'Documenti', 'expenses.forecast' => 'Previsione'] as $route => $label)
        <a href="{{ route($route) }}" wire:navigate @if($activeSection === $route) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
