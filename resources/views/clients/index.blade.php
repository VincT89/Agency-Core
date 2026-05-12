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
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cerca cliente (nome, email, p.iva)..." class="form-in form-in-sm u-w-250">
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
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($clients as $client)
                <tr x-data @click="window.Livewire.navigate('{{ route('clients.show', $client) }}')" class="u-cursor-pointer hover-bg">
                    <td class="name-col">{{ $client->name }}</td>
                    <td class="mono-col">{{ $client->projects_count }}</td>
                    <td class="mono-col">{{ $client->tickets_count }}</td>
                    <td class="mono-col">{{ $client->invoices_count }}</td>
                    <td><x-badge :status="$client->status" :label="$client->status_label" /></td>
                    <td>
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