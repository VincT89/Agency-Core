<x-panel>
    <table class="t-table quote-list" role="table" aria-label="Offerte commerciali">
        <thead role="rowgroup">
            <tr role="row">
                <th scope="col" role="columnheader">Offerta</th>
                <th scope="col" role="columnheader">Cliente</th>
                <th scope="col" role="columnheader">Importo</th>
                <th scope="col" role="columnheader">Data</th>
                <th scope="col" role="columnheader">Stato</th>
                <th scope="col" role="columnheader">Azioni</th>
            </tr>
        </thead>
        <tbody role="rowgroup">
            @foreach($quotes as $offer)
                <tr @class(['quote-list-row', 'js-clickable-row', 'is-rejected' => $offer->status === 'rejected']) data-href="{{ route('quotes.show', $offer) }}" role="row">
                    <td class="name-col quote-title-cell" role="cell">
                        <a class="commercial-offer-title" href="{{ route('quotes.show', $offer) }}">{{ $offer->title }}</a>
                        <div class="quote-reference">#{{ $offer->id }} · Revisione {{ $offer->revision }}</div>
                    </td>
                    <td class="quote-client-cell" role="cell" data-label="Cliente">{{ $offer->client?->name ?? $offer->client_snapshot['name'] ?? 'Cliente non disponibile' }}</td>
                    <td class="quote-amount-cell" role="cell" data-label="Importo"><span class="commercial-offer-total">{{ number_format((float) $offer->total, 2, ',', '.') }} €</span></td>
                    <td class="quote-date-cell" role="cell" data-label="Data"><time datetime="{{ ($offer->presented_at ?? $offer->created_at)->toDateString() }}">{{ ($offer->presented_at ?? $offer->created_at)->format('d/m/Y') }}</time></td>
                    <td class="quote-status-cell" role="cell" data-label="Stato"><x-badge :status="$offer->status" :label="$offer->status_label" /></td>
                    <td class="quote-actions-cell" role="cell">@include('quotes.partials.quick-actions')</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="quote-list-pagination">{{ $quotes->onEachSide(1)->links() }}</div>
</x-panel>
