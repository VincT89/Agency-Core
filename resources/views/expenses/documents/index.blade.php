<x-app-layout title="Documenti delle spese">
    <x-page-header>
        <x-slot:title>Documenti delle spese</x-slot:title>
        <x-slot:actions><a href="{{ route('expenses.documents.aruba') }}" class="btn btn-g">Fatture da Aruba</a><a href="{{ route('expenses.documents.create') }}" class="btn btn-p">Carica documento</a></x-slot:actions>
    </x-page-header>
    @include('expenses.partials.navigation')
    <p class="u-mb-md">Fatture, cedolini e altri giustificativi. Dopo il caricamento collega il documento alla spesa o alle rate corrispondenti.</p>
    <form method="GET" class="ticket-search-form">
        <x-form-group label="Cerca per emittente o numero" name="search"><input id="field-search" name="search" class="form-in" value="{{ request('search') }}" maxlength="255"></x-form-group>
        <button type="submit" class="btn btn-g">Cerca</button>
    </form>
    <x-panel padded>
        @forelse($documents as $document)
            <article class="finance-list-item">
                <div><a class="u-link" href="{{ route('expenses.documents.show', $document) }}">{{ \App\Models\ExpenseDocument::KINDS[$document->kind] }} {{ $document->number }}</a><p>{{ $document->issuer }}</p><p class="u-text-meta">{{ $document->document_date->format('d/m/Y') }} · {{ $document->expenses_count }} spese collegate</p></div>
                <p class="mono-col">{{ number_format($document->amount, 2, ',', '.') }} EUR</p>
            </article>
        @empty <p>Nessun documento disponibile.</p> @endforelse
        {{ $documents->links() }}
    </x-panel>
</x-app-layout>
