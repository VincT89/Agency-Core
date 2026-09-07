<x-app-layout title="Apri ticket">
    <x-page-header><x-slot:title>Apri ticket</x-slot:title></x-page-header>
    <x-panel padded>
        @include('tickets._form', ['ticket' => null])
    </x-panel>
</x-app-layout>
