<div class="quote-quick-actions">
    @can('update', $offer)<a href="{{ route('quotes.edit', $offer) }}" class="btn btn-g btn-sm" aria-label="Modifica {{ $offer->title }}">Modifica</a>@endcan
    <a href="{{ route('quotes.document', $offer) }}" class="btn btn-g btn-sm" aria-label="Anteprima PDF di {{ $offer->title }}">PDF</a>
</div>
