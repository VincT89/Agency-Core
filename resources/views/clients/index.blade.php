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

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;justify-content:flex-end">
        <form method="GET" action="{{ route('clients.index') }}" style="display:flex;gap:8px">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cerca cliente (nome, email, p.iva)..." class="form-in" style="padding:5px 10px;font-size:11px;width:250px">
            @if(request('search'))
                <a href="{{ route('clients.index') }}" class="btn btn-g" style="padding:5px 10px;font-size:11px">Reset</a>
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
                <tr onclick="window.location='{{ route('clients.show', $client) }}'" style="cursor:pointer">
                    <td class="name-col">{{ $client->name }}</td>
                    <td class="mono-col">{{ $client->projects_count }}</td>
                    <td class="mono-col">{{ $client->tickets_count }}</td>
                    <td class="mono-col">{{ $client->invoices_count }}</td>
                    <td><x-badge :status="$client->status" :label="$client->status_label" /></td>
                    <td>
                        @can('update', $client)
                            <a href="{{ route('clients.edit', $client) }}" class="btn-icon" onclick="event.stopPropagation()">✎</a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:var(--text3);padding:32px">Nessun cliente trovato</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $clients->links() }}
    </x-panel>
</x-app-layout>