<x-app-layout :title="$client->name">
    <div class="page-back-row"><a href="{{ route('clients.index') }}" class="btn btn-g">I miei clienti</a></div>
    <x-page-header><x-slot:title>{{ $client->name }}</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('tickets.create', ['type' => 'quote', 'client_id' => $client->id]) }}" class="btn btn-p">Richiedi preventivo</a>
            <a href="{{ route('tasks.index', ['client_id' => $client->id]) }}" class="btn btn-g">Task del cliente</a>
        </x-slot:actions>
    </x-page-header>
    <x-panel title="Anagrafica" padded>
        <dl class="ticket-request-details">
            @foreach(['company_name' => 'Ragione sociale', 'reference_person' => 'Referente', 'email' => 'Email', 'phone' => 'Telefono', 'vat_number' => 'Partita IVA', 'tax_code' => 'Codice fiscale', 'address' => 'Indirizzo', 'city' => 'Comune', 'postal_code' => 'CAP', 'province' => 'Provincia'] as $field => $label)
                <div><dt>{{ $label }}</dt><dd>{{ $client->$field ?: 'Non indicato' }}</dd></div>
            @endforeach
        </dl>
    </x-panel>
    <div class="mt-panel">@include('quotes.partials.history')</div>
    <div class="mt-panel"><x-panel title="I miei ticket per questo cliente" padded>
        @include('tickets.partials.request-list', ['requests' => $tickets])
        {{ $tickets->links() }}
    </x-panel></div>
</x-app-layout>
