<x-app-layout title="I miei clienti">
    <x-page-header><x-slot:title>I miei clienti</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('clients.create') }}" class="btn btn-p">Nuovo cliente</a>
            <a href="{{ route('tickets.create', ['type' => 'quote']) }}" class="btn btn-g">Richiedi preventivo</a>
        </x-slot:actions>
    </x-page-header>
    <form method="GET" class="ticket-search-form">
        <x-form-group label="Cerca cliente" name="search"><input id="field-search" type="search" name="search" class="form-in" value="{{ request('search') }}" placeholder="Nome, ragione sociale, contatti o dati fiscali" maxlength="255"></x-form-group>
        <button class="btn btn-p" type="submit">Cerca</button>
        @if(request('search'))<a href="{{ route('clients.index') }}" class="btn btn-g">Azzera ricerca</a>@endif
    </form>
    <x-panel padded>
        @forelse($clients as $client)
            <article class="commercial-history-row">
                <h2><a href="{{ route('clients.show', $client) }}">{{ $client->name }}</a></h2>
                <p>{{ $client->company_name }}</p>
                <p>{{ $client->email }}</p>
                <p>{{ $client->phone }}</p>
                @if($client->vat_number)<p>Partita IVA: {{ $client->vat_number }}</p>@endif
            </article>
        @empty
            <p>Nessun cliente trovato. Usa “Nuovo cliente” per registrare un’anagrafica, anche prima di aprire un ticket o richiedere un preventivo.</p>
        @endforelse
    </x-panel>
    {{ $clients->links() }}
</x-app-layout>
