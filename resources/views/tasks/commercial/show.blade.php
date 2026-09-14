<x-app-layout :title="$task->title">
    <div class="page-back-row"><a href="{{ route('tasks.index') }}" class="btn btn-g">Task dei miei clienti</a></div>
    <x-page-header><x-slot:title>{{ $task->title }}</x-slot:title></x-page-header>
    <x-panel padded>
        <dl class="ticket-request-details">
            <div><dt>Cliente</dt><dd>{{ $task->clientForDisplay()?->name ?? 'Non indicato' }}</dd></div>
            <div><dt>Progetto</dt><dd>{{ $task->project?->name ?? 'Non collegato' }}</dd></div>
            <div><dt>Stato</dt><dd>{{ $task->status_label }}</dd></div>
            <div><dt>Referente</dt><dd>{{ $task->assignee?->name ?? 'Non assegnato' }}</dd></div>
            <div><dt>Scadenza</dt><dd>{{ $task->due_date?->format('d/m/Y') ?? 'Non indicata' }}</dd></div>
            <div><dt>Completata il</dt><dd>{{ $task->completed_at?->format('d/m/Y H:i') ?? 'Non ancora completata' }}</dd></div>
        </dl>
        <h2 class="u-text-strong u-mt-lg">Descrizione</h2>
        <div class="ticket-description">{{ $task->commercialShoot ? ($task->commercialShoot->client_notes ?: 'Shooting del cliente. Consulta stato e scadenza per seguirne l’avanzamento.') : ($task->description ?: 'Nessuna descrizione aggiuntiva.') }}</div>
        @if($task->ticket)
            <p class="u-mt-lg"><a href="{{ route('tickets.show', $task->ticket) }}">Apri il ticket {{ $task->ticket->code }}</a></p>
        @endif
    </x-panel>
</x-app-layout>
