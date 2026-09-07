<x-app-layout title="I miei ticket">
    <x-page-header>
        <x-slot:title>I miei ticket</x-slot:title>
        <x-slot:actions><a href="{{ route('tickets.create') }}" class="btn btn-p">Apri ticket</a></x-slot:actions>
    </x-page-header>
    <form method="GET" action="{{ route('tickets.index') }}" class="ticket-search-form">
        <x-form-group label="Cerca nei tuoi ticket" name="search">
            <input name="search" class="form-in" value="{{ request('search') }}" placeholder="Oggetto, codice o cliente">
        </x-form-group>
        <x-form-group label="Stato" name="status">
            <select name="status" class="form-sel">
                <option value="">Tutti gli stati</option>
                @foreach(\App\Models\Ticket::STATUSES as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ (new \App\Models\Ticket(['status' => $status]))->status_label }}</option>
                @endforeach
            </select>
        </x-form-group>
        <button class="btn btn-g" type="submit">Cerca</button>
        @if(request('search') || request('status'))
            <a href="{{ route('tickets.index') }}" class="btn btn-g">Azzera filtri</a>
        @endif
    </form>
    <x-panel padded>
        @include('tickets.partials.request-list', ['requests' => $tickets])
    </x-panel>
    {{ $tickets->links() }}
</x-app-layout>
