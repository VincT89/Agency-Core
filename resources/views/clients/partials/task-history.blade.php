<x-panel title="Task del cliente" padded>
    @if(auth()->user()->isCommercial())
    <x-slot:headerActions>
        <a href="{{ route('tasks.index', ['client_id' => $client->id]) }}" class="btn btn-g btn-sm">Filtra task</a>
    </x-slot:headerActions>
    @endif
    @forelse($tasks as $task)
        <article class="commercial-history-row">
            <h3><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></h3>
            <dl class="ticket-request-details">
                <div><dt>Stato</dt><dd>{{ $task->status_label }}</dd></div>
                <div><dt>Assegnatario</dt><dd>{{ $task->assignee?->name ?? 'Da assegnare' }}</dd></div>
                <div><dt>Scadenza</dt><dd>{{ $task->due_date?->format('d/m/Y') ?? 'Non indicata' }}</dd></div>
                <div><dt>Completata il</dt><dd>{{ $task->completed_at?->format('d/m/Y') ?? 'Non indicato' }}</dd></div>
            </dl>
        </article>
    @empty
        <p>Nessuna task collegata a questo cliente.</p>
    @endforelse
    {{ $tasks->links() }}
</x-panel>
