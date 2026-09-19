<x-app-layout title="Spese ricorrenti">
    <x-page-header><x-slot:title>Spese ricorrenti</x-slot:title>
        <x-slot:actions><a href="{{ route('expenses.recurrences.create') }}" class="btn btn-p">Nuova ricorrenza</a></x-slot:actions>
    </x-page-header>
    @include('expenses.partials.navigation')
    <p class="u-mb-md">Programma abbonamenti, stipendi e altre uscite periodiche. Le scadenze vengono generate come spese da pagare per i prossimi 24 mesi.</p>
    <x-panel padded>
        @forelse($recurrences as $recurrence)
            <article class="commercial-history-row">
                <h2><a href="{{ route('expenses.recurrences.edit', $recurrence) }}">{{ $recurrence->title }}</a></h2>
                <dl class="ticket-request-details">
                    <div><dt>Importo previsto</dt><dd>{{ number_format($recurrence->amount, 2, ',', '.') }} EUR</dd></div>
                    <div><dt>Frequenza</dt><dd>{{ \App\Models\ExpenseRecurrence::FREQUENCIES[$recurrence->frequency] }}</dd></div>
                    <div><dt>Fornitore / beneficiario</dt><dd>{{ $recurrence->supplier ?: 'Non indicato' }}</dd></div>
                    <div><dt>Stato</dt><dd>{{ $recurrence->active ? 'Attiva' : 'Sospesa' }}</dd></div>
                </dl>
                <a href="{{ route('expenses.index', ['recurrence_id' => $recurrence->id]) }}" class="btn btn-g btn-sm u-mt-md">Vedi scadenze</a>
            </article>
        @empty
            <p>Nessuna ricorrenza registrata.</p>
        @endforelse
        {{ $recurrences->links() }}
    </x-panel>
</x-app-layout>
