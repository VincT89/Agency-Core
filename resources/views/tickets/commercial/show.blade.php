<x-app-layout :title="$ticket->title">
    <div class="page-back-row"><a href="{{ route('tickets.index') }}" class="btn btn-g">I miei ticket</a></div>
    <x-page-header><x-slot:title>{{ $ticket->title }}</x-slot:title></x-page-header>
    <x-panel padded>
        <dl class="ticket-request-details">
            <div><dt>Codice</dt><dd>{{ $ticket->code }}</dd></div>
            <div><dt>Stato</dt><dd>{{ $ticket->status_label }}</dd></div>
            <div><dt>Cliente</dt><dd>{{ $ticket->client?->name }}</dd></div>
            <div><dt>Progetto</dt><dd>{{ $ticket->project?->name ?? 'Non collegato' }}</dd></div>
            <div><dt>Motivo</dt><dd>{{ $ticket->type_label }}</dd></div>
            <div><dt>Area della richiesta</dt><dd>{{ \App\Models\Ticket::DEPARTMENTS[$ticket->requested_department] ?? 'Da valutare' }}</dd></div>
            <div><dt>Referente ticket</dt><dd>{{ $ticket->assignee?->name ?? 'Non assegnato' }}</dd></div>
            <div><dt>Priorità</dt><dd>{{ $ticket->priority_label }}</dd></div>
        </dl>
        <h2 class="u-text-strong u-mt-lg">Descrizione</h2>
        <div class="ticket-description">{{ $ticket->description ?: 'Nessuna descrizione aggiuntiva.' }}</div>
        @if($ticket->resolution_notes)
            <h2 class="u-text-strong u-mt-lg">Risoluzione</h2>
            <div class="ticket-description">{{ $ticket->resolution_notes }}</div>
        @endif
    </x-panel>
    <livewire:tickets.ticket-comments :ticket="$ticket" />
    <livewire:shared.attachment-manager :model="$ticket" />
</x-app-layout>
