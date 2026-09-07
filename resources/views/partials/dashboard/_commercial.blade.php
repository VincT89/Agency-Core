<p class="ticket-create-note">Apri le richieste ricevute dai clienti e segui qui quelle ancora in corso.</p>
<x-panel title="Richieste in corso" padded>
    @include('tickets.partials.request-list', ['requests' => $commercialTickets])
</x-panel>
