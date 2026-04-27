<x-app-layout title="Dashboard">
    <x-page-header
        eyebrow="Console"
        
        :meta="today()->isoFormat('D MMMM YYYY')"
    >
    <x-slot:title><strong>Bentornato</strong>, {{ auth()->user()->name }}</x-slot:title>
        <x-slot:actions>
            @can('create', \App\Models\Ticket::class)
                @if(!auth()->user()->isMarketing())
                <a href="{{ route('tickets.create') }}" class="btn btn-p">+ Nuovo ticket</a>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    @can('system.admin')
        @include('partials.dashboard._admin')
    @elseif(auth()->user()->isAdministration())
        @include('partials.dashboard._administration')
    @elseif(auth()->user()->isPhotographer())
        <livewire:dashboard.photographer-dashboard />
    @else
        @include('partials.dashboard._workspace')
    @endif
</x-app-layout>