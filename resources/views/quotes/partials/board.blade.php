<div @class(['kanban', 'quote-board', 'quote-board-three-columns' => count($columns) === 3, 'quote-board-filtered' => isset($filters['status'])])>
    @foreach($columns as $status => $offers)
        <section class="k-col quote-column" id="offers-{{ $status }}" aria-labelledby="offers-{{ $status }}-title">
            <h2 class="k-col-title" id="offers-{{ $status }}-title">
                <span>{{ ['draft' => 'Bozze', 'presented' => 'Presentate', 'accepted' => 'Accettate', 'rejected' => 'Rifiutate'][$status] }}</span>
                <span>{{ $offers->total() }}</span>
            </h2>
            @forelse($offers as $offer)
                <article @class(['k-card', 'quote-card', 'js-clickable-row', 'is-rejected' => $status === 'rejected']) data-href="{{ route('quotes.show', $offer) }}">
                    <div class="quote-reference">#{{ $offer->id }} · Revisione {{ $offer->revision }}</div>
                    <h3 class="k-card-title"><a class="commercial-offer-title" href="{{ route('quotes.show', $offer) }}">{{ $offer->title }}</a></h3>
                    <p class="quote-card-client">{{ $offer->client?->name ?? $offer->client_snapshot['name'] ?? 'Cliente non disponibile' }}</p>
                    <div class="quote-card-details">
                        <strong class="commercial-offer-total">{{ number_format((float) $offer->total, 2, ',', '.') }} €</strong>
                        <time datetime="{{ ($offer->presented_at ?? $offer->created_at)->toDateString() }}">{{ ($offer->presented_at ?? $offer->created_at)->format('d/m/Y') }}</time>
                    </div>
                    @include('quotes.partials.quick-actions')
                </article>
            @empty
                <p class="quote-column-empty">Nessuna offerta in questo stato.</p>
            @endforelse
            @if($offers->hasPages())
                <nav class="quote-column-pagination" aria-label="Pagine offerte in stato {{ $statuses[$status] }}">
                    <p>{{ $offers->firstItem() }}–{{ $offers->lastItem() }} di {{ $offers->total() }}</p>
                    <div>
                        @if(!$offers->onFirstPage())<a href="{{ $offers->previousPageUrl() }}" class="btn btn-g btn-sm" rel="prev">Precedenti</a>@endif
                        @if($offers->hasMorePages())<a href="{{ $offers->nextPageUrl() }}" class="btn btn-g btn-sm" rel="next">Successive</a>@endif
                    </div>
                </nav>
            @endif
        </section>
    @endforeach
</div>
