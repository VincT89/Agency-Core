<x-panel title="Storico offerte commerciali" padded>
    @forelse($quotes as $offer)
        <article class="commercial-history-row">
            <h3><a href="{{ route('quotes.show', $offer) }}">{{ $offer->title }}</a></h3>
            <dl class="ticket-request-details">
                <div><dt>Offerta</dt><dd>#{{ $offer->id }}, revisione {{ $offer->revision }}</dd></div>
                <div><dt>Data</dt><dd>{{ ($offer->presented_at ?? $offer->created_at)->format('d/m/Y') }}</dd></div>
                <div><dt>Stato</dt><dd>{{ $offer->status_label }}</dd></div>
                <div><dt>Totale servizi</dt><dd>{{ number_format((float) $offer->total, 2, ',', '.') }} €</dd></div>
            </dl>
        </article>
    @empty
        <p>Nessuna offerta disponibile.</p>
    @endforelse
    @if($quotes instanceof \Illuminate\Contracts\Pagination\Paginator)
        {{ $quotes->links() }}
    @endif
</x-panel>
