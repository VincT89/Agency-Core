<x-app-layout :title="$client->name">
    <div class="page-back-row"><a href="{{ route('clients.index') }}" class="btn btn-g">I miei clienti</a></div>
    <x-page-header><x-slot:title>{{ $client->name }}</x-slot:title>
        <x-slot:actions>
            @can('updateRegistry', $client)<a href="{{ route('clients.edit', $client) }}" class="btn btn-g">Modifica anagrafica</a>@endcan
            <a href="{{ route('tickets.create', ['type' => 'quote', 'client_id' => $client->id]) }}" class="btn btn-p">Richiedi preventivo</a>
            <a href="{{ route('tickets.create', ['client_id' => $client->id]) }}" class="btn btn-g">Apri ticket</a>
        </x-slot:actions>
    </x-page-header>
    <x-panel title="Anagrafica" padded>
        @include('clients.partials.registry-details')
    </x-panel>
    @include('clients.partials.commercial-notes')
    <div class="mt-panel">@include('quotes.partials.history')</div>
    <div class="mt-panel"><x-panel title="I miei ticket per questo cliente" padded>
        @include('tickets.partials.request-list', ['requests' => $tickets])
        {{ $tickets->links() }}
    </x-panel></div>
    <div class="mt-panel">@include('clients.partials.task-history')</div>
    <div class="mt-panel"><x-panel title="Progetti del cliente" padded>
        @forelse($projects as $project)
            <article class="commercial-history-row">
                <h3>{{ $project->name }}</h3>
                <dl class="ticket-request-details">
                    <div><dt>Stato</dt><dd>{{ $project->status_label }}</dd></div>
                    <div><dt>Registrato il</dt><dd>{{ $project->created_at->format('d/m/Y') }}</dd></div>
                </dl>
            </article>
        @empty
            <p>Nessun progetto registrato per questo cliente.</p>
        @endforelse
        {{ $projects->links() }}
    </x-panel></div>
</x-app-layout>
