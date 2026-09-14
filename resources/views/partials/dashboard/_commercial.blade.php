<p class="ticket-create-note">Apri le richieste ricevute dai clienti e segui qui quelle ancora in corso.</p>
<x-panel title="Richieste in corso" padded>
    @include('tickets.partials.request-list', ['requests' => $commercialTickets])
</x-panel>
<div class="mt-panel"><x-panel title="Ultimi aggiornamenti delle task" padded>
    @forelse($commercialTasks as $task)
        <article class="commercial-history-row">
            <h2><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></h2>
            <p>{{ $task->status_label }}. Cliente: {{ $task->clientForDisplay()?->name ?? 'Non indicato' }}. Referente: {{ $task->assignee?->name ?? 'Non assegnato' }}.</p>
        </article>
    @empty
        <p>Nessuna task disponibile.</p>
    @endforelse
    <a href="{{ route('tasks.index') }}" class="btn btn-g u-mt-lg">Tutte le task dei miei clienti</a>
</x-panel></div>
