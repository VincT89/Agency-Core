<x-app-layout title="Voci salvate dei preventivi">
    <div class="page-back-row"><a href="{{ route('quotes.index') }}" class="btn btn-g">Offerte commerciali</a></div>
    <x-page-header><x-slot:title>Voci salvate dei preventivi</x-slot:title><x-slot:actions><a href="{{ route('quotes.create') }}" class="btn btn-p">Prepara offerta</a></x-slot:actions></x-page-header>
    <x-panel padded>
        <p>Le voci sono condivise tra Admin e Amministrazione. Per salvarne una nuova, usa “Salva voce in libreria” durante la preparazione di un’offerta.</p>
        <form method="GET" class="quote-library-search u-mt-lg"><div><label for="q">Cerca servizio</label><input id="q" class="form-in" name="q" value="{{ request('q') }}" maxlength="100"></div><button type="submit" class="btn btn-g">Cerca</button></form>
        @forelse($services as $service)
            <article class="commercial-history-row">
                <h2>{{ $service->name }}</h2>
                @if($service->summary)<p class="ticket-description">{{ $service->summary }}</p>@endif
                @if($service->description)<details class="u-mt-md"><summary>Descrizione dettagliata</summary><p class="ticket-description">{{ $service->description }}</p></details>@endif
                @if($service->delivery_terms || $service->delivery_summary)<p>Consegna: {{ $service->delivery_terms ?: $service->delivery_summary }}</p>@endif
                <p>Quantità {{ number_format((float) $service->quantity, 2, ',', '.') }}, prezzo unitario {{ number_format((float) $service->unit_price, 2, ',', '.') }} €</p>
                <div class="u-mt-md">
                    <x-delete-modal :action="route('quote-services.destroy', $service)" title="Rimuovi voce dalla libreria"
                        message="Rimuovere questa voce dalla libreria? I preventivi esistenti resteranno invariati.">
                        <button type="button" class="btn btn-g btn-sm">Rimuovi dalla libreria</button>
                    </x-delete-modal>
                </div>
            </article>
        @empty<p class="u-mt-lg">Nessuna voce salvata.</p>@endforelse
        {{ $services->links() }}
    </x-panel>
</x-app-layout>
