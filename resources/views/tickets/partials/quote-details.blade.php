@if($ticket->type === 'quote')
    <div class="mt-panel"><x-panel title="Servizi richiesti" padded>
        @forelse($ticket->requestedServices as $service)
            <article class="commercial-history-row"><h3>{{ $service->name }}</h3><p class="ticket-description">{{ $service->description }}</p></article>
        @empty
            <p>I servizi di questa richiesta precedente sono descritti nel ticket.</p>
        @endforelse
        @can('create', \App\Models\Quote::class)
            <a href="{{ route('quotes.create', ['ticket_id' => $ticket->id]) }}" class="btn btn-p">Prepara offerta</a>
        @endcan
    </x-panel></div>
    <div class="mt-panel">@include('quotes.partials.history')</div>
@endif
