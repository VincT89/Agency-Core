<x-app-layout title="Task dei miei clienti">
    <x-page-header><x-slot:title>Task dei miei clienti</x-slot:title></x-page-header>
    <p class="ticket-create-note">Segui le attività dei tuoi clienti e quelle assegnate a te, comprese le attività completate.</p>
    <form method="GET" class="ticket-search-form">
        <x-form-group label="Cliente" name="client_id">
            <select name="client_id" class="form-sel">
                <option value="">Tutti i miei clienti</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" @selected(request('client_id') == $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
        </x-form-group>
        <x-form-group label="Stato" name="status">
            <select name="status" class="form-sel">
                <option value="">Tutti gli stati</option>
                @foreach(['todo', 'in_progress', 'waiting', 'review', 'done', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ (new \App\Models\Task(['status' => $status]))->status_label }}</option>
                @endforeach
            </select>
        </x-form-group>
        <button class="btn btn-p" type="submit">Filtra</button>
        <a href="{{ route('tasks.index') }}" class="btn btn-g">Azzera filtri</a>
    </form>
    <x-panel padded>
        @forelse($tasks as $task)
            <article class="commercial-history-row">
                <h2><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></h2>
                <dl class="ticket-request-details">
                    <div><dt>Cliente</dt><dd>{{ $task->clientForDisplay()?->name ?? 'Attività assegnata a te' }}</dd></div>
                    <div><dt>Stato</dt><dd>{{ $task->status_label }}</dd></div>
                    <div><dt>Referente</dt><dd>{{ $task->assignee?->name ?? 'Non assegnato' }}</dd></div>
                    <div><dt>Scadenza</dt><dd>{{ $task->due_date?->format('d/m/Y') ?? 'Non indicata' }}</dd></div>
                </dl>
            </article>
        @empty
            <p>Nessuna task trovata.</p>
        @endforelse
    </x-panel>
    {{ $tasks->links() }}
</x-app-layout>
