<x-app-layout title="Entrate manuali">
    <x-page-header><x-slot:title>Entrate manuali</x-slot:title><x-slot:actions><a href="{{ route('expenses.incomes.create') }}" class="btn btn-p">Nuova entrata</a></x-slot:actions></x-page-header>
    @include('expenses.partials.navigation')
    <p class="u-mb-md">Registra qui le entrate fuori fattura. Per incassare una fattura cliente, registra il pagamento dalla fattura: sarà incluso automaticamente nella previsione.</p>
    <form method="GET" class="ticket-search-form">
        <x-form-group label="Stato" name="status"><select id="field-status" name="status" class="form-sel"><option value="">Tutti</option>@foreach(\App\Models\ManualIncome::STATUSES as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></x-form-group>
        <button class="btn btn-g" type="submit">Filtra</button>
    </form>
    <x-panel padded>
        @forelse($incomes as $income)
            <article class="commercial-history-row">
                <h2><a href="{{ route('expenses.incomes.edit', $income) }}">{{ $income->title }}</a></h2>
                <dl class="ticket-request-details">
                    <div><dt>Importo</dt><dd>{{ number_format($income->amount, 2, ',', '.') }} EUR</dd></div>
                    <div><dt>Pagante</dt><dd>{{ $income->payer ?: $income->client?->name ?: 'Non indicato' }}</dd></div>
                    <div><dt>Data prevista</dt><dd>{{ $income->expected_on->format('d/m/Y') }}</dd></div>
                    <div><dt>Stato</dt><dd>{{ \App\Models\ManualIncome::STATUSES[$income->status] }}{{ $income->received_on ? ' il '.$income->received_on->format('d/m/Y') : '' }}</dd></div>
                </dl>
            </article>
        @empty
            <p>Nessuna entrata manuale registrata.</p>
        @endforelse
        {{ $incomes->links() }}
    </x-panel>
</x-app-layout>
