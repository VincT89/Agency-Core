<x-app-layout title="I miei clienti">
    <x-page-header><x-slot:title>I miei clienti</x-slot:title>
        <x-slot:actions><a href="{{ route('tickets.create', ['type' => 'quote']) }}" class="btn btn-p">Richiedi preventivo</a></x-slot:actions>
    </x-page-header>
    <form method="GET" class="ticket-search-form">
        <x-form-group label="Cerca cliente" name="search"><input name="search" class="form-in" value="{{ request('search') }}"></x-form-group>
        <button class="btn btn-p" type="submit">Cerca</button>
    </form>
    <x-panel padded>
        @forelse($clients as $client)
            <article class="commercial-history-row">
                <h2><a href="{{ route('clients.show', $client) }}">{{ $client->name }}</a></h2>
                <p>{{ $client->company_name }}</p>
                <p>{{ $client->email }}{{ $client->email && $client->phone ? ' · ' : '' }}{{ $client->phone }}</p>
            </article>
        @empty
            <p>Nessun cliente trovato. Puoi aggiungerne uno mentre richiedi un preventivo o apri un ticket.</p>
        @endforelse
    </x-panel>
    {{ $clients->links() }}
</x-app-layout>
