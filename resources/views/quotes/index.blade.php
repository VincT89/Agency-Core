<x-app-layout title="Offerte commerciali">
    <x-page-header>
        <x-slot:title>Offerte commerciali</x-slot:title>
        @can('create', \App\Models\Quote::class)
            <x-slot:actions>
                <a href="{{ route('quote-services.index') }}" class="btn btn-g">Voci salvate</a>
                <a href="{{ route('quotes.create') }}" class="btn btn-p">Nuova offerta</a>
            </x-slot:actions>
        @endcan
    </x-page-header>
    <form method="GET" class="ticket-search-form">
        <x-form-group label="Stato" name="status">
            <select name="status" class="form-sel">
                <option value="">Tutti gli stati</option>
                @foreach(\App\Models\Quote::STATUSES as $status => $label)
                    @if($status !== 'draft' || auth()->user()->can('create', \App\Models\Quote::class))
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>
                    @endif
                @endforeach
            </select>
        </x-form-group>
        <button class="btn btn-p" type="submit">Filtra</button>
    </form>
    @include('quotes.partials.history')
</x-app-layout>
