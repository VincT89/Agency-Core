<x-app-layout title="Dashboard">
    <x-page-header
        eyebrow="Console"
        
        :meta="today()->isoFormat('D MMMM YYYY')"
    >
    <x-slot:title><strong>Bentornato</strong>, {{ auth()->user()->name }}</x-slot:title>

        @if(auth()->user()->isCommercial())
            <x-slot:actions>
                <a href="{{ route('tickets.create') }}" class="btn btn-p">Apri ticket</a>
                <a href="{{ route('tickets.index') }}" class="btn btn-g">I miei ticket</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    @can('system.admin')
        @include('partials.dashboard._admin')
    @elseif(auth()->user()->isAdministration())
        @include('partials.dashboard._administration')
    @elseif(auth()->user()->isCommercial())
        @include('partials.dashboard._commercial')
    @elseif(auth()->user()->isPhotographer())
        <livewire:dashboard.photographer-dashboard />
    @else
        @include('partials.dashboard._workspace')
    @endif
</x-app-layout>
