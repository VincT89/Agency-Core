<x-app-layout title="Clienti">
    <x-page-header
        eyebrow="Modulo · Core"
        
        :meta="$clients->total() . ' totali'"
    >
    <x-slot:title><strong>Clienti</strong></x-slot:title>
        <x-slot:actions>
            @can('create', App\Models\Client::class)
                <a href="{{ route('clients.create') }}" class="btn btn-p">+ Nuovo cliente</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="filter-bar justify-end">
        <form method="GET" action="{{ route('clients.index') }}" class="u-flex u-gap-sm">
            <label for="clients-search" class="sr-only">Cerca clienti</label>
            <input id="clients-search" type="search" name="search" value="{{ request('search') }}"
                   placeholder="Nome, email o partita IVA" class="form-in form-in-sm u-w-250">
            <button type="submit" class="btn btn-p btn-sm">Cerca</button>
            @if(request('search'))
                <a href="{{ route('clients.index') }}" class="btn btn-g btn-sm">Reset</a>
            @endif
        </form>
    </div>

    <x-panel>
        <table class="t-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Progetti</th>
                    <th>Ticket</th>
                    <th>Fatture</th>
                    <th>Stato</th>
                    <th class="t-actions">Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clients as $client)
                <tr x-data @click="window.Livewire.navigate('{{ route('clients.show', $client) }}')"
                    @keydown.enter.self.prevent="window.Livewire.navigate('{{ route('clients.show', $client) }}')"
                    @keydown.space.self.prevent="window.Livewire.navigate('{{ route('clients.show', $client) }}')"
                    tabindex="0" role="link" aria-label="Apri cliente {{ $client->name }}"
                    class="u-cursor-pointer hover-bg">
                    <td class="name-col">{{ $client->name }}</td>
                    <td class="mono-col">{{ $client->projects_count }}</td>
                    <td class="mono-col">{{ $client->tickets_count }}</td>
                    <td class="mono-col">{{ $client->invoices_count }}</td>
                    <td><x-badge :status="$client->status" :label="$client->status_label" /></td>
                    <td class="t-actions">
                        @can('update', $client)
                            <a href="{{ route('clients.edit', $client) }}" class="btn-icon" @click.stop>✎</a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="u-empty-state">Nessun cliente trovato</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $clients->links() }}
    </x-panel>
</x-app-layout>
