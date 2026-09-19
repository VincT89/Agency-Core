<x-app-layout :title="'Documento '.$document->number">
    <x-page-header><x-slot:title>{{ \App\Models\ExpenseDocument::KINDS[$document->kind] }} {{ $document->number }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('expenses.documents.download', $document) }}">Scarica documento</a></x-slot:actions></x-page-header>
    @include('expenses.partials.navigation')
    <x-panel padded>
        <dl class="finance-document-details">
            <dt>Emittente / beneficiario</dt><dd>{{ $document->issuer }}</dd>
            @if($document->issuer_identifier)<dt>Partita IVA / codice fiscale</dt><dd>{{ $document->issuer_identifier }}</dd>@endif
            @if($document->issuer_country)<dt>Paese dell’emittente</dt><dd>{{ $document->issuer_country }}</dd>@endif
            <dt>Data documento</dt><dd>{{ $document->document_date->format('d/m/Y') }}</dd>
            @if($document->due_date)<dt>Scadenza</dt><dd>{{ $document->due_date->format('d/m/Y') }}</dd>@endif
            <dt>Importo documentato</dt><dd>{{ number_format($document->amount, 2, ',', '.') }} EUR</dd>
            <dt>Importo collegato alle spese</dt><dd>{{ number_format($allocated / 100, 2, ',', '.') }} EUR</dd>
            <dt>Importo ancora da collegare</dt><dd>{{ number_format($remaining / 100, 2, ',', '.') }} EUR</dd>
            @if($document->aruba_id)<dt>Origine</dt><dd>Aruba — {{ $document->aruba_environment === 'production' ? 'Produzione' : 'Collaudo' }}</dd>@endif
        </dl>
        <p class="u-text-meta u-mt-md">Il documento giustifica le spese collegate. Il pagamento viene confermato nella scheda della spesa.</p>
    </x-panel>
    <x-panel title="Spese collegate" padded class="u-mt-lg">
        @forelse($document->expenses as $expense)
            <article class="finance-list-item"><div><a class="u-link" href="{{ route('expenses.show', $expense) }}">{{ $expense->title }}</a><p class="u-text-meta">{{ $expense->due_date?->format('d/m/Y') ?? $expense->expense_date->format('d/m/Y') }} · {{ $expense->status_label }}</p></div><p>{{ number_format($expense->amount, 2, ',', '.') }} EUR</p></article>
        @empty <p>Il documento non è ancora collegato a una spesa.</p> @endforelse
    </x-panel>
    @if($remaining > 0)
        <x-panel title="Collega a una spesa prevista" padded class="u-mt-lg">
            <p class="u-mb-md">Se la spesa è già prevista, selezionala qui. Nella sua scheda controlla importo e documento prima di salvare.</p>
            <form method="GET" class="ticket-search-form"><x-form-group label="Cerca spesa o beneficiario" name="search"><input id="field-search" name="search" class="form-in" value="{{ request('search') }}" maxlength="255"></x-form-group><button class="btn btn-g" type="submit">Cerca</button></form>
            @forelse($candidates as $expense)
                <article class="finance-list-item"><div><a class="u-link" href="{{ route('expenses.edit', ['expense' => $expense, 'document_id' => $document->id]) }}">{{ $expense->title }}</a><p class="u-text-meta">{{ $expense->supplier }} · {{ $expense->due_date?->format('d/m/Y') ?? $expense->expense_date->format('d/m/Y') }}</p></div><p>{{ number_format($expense->amount, 2, ',', '.') }} EUR</p></article>
            @empty <p>Nessuna spesa prevista corrispondente.</p> @endforelse
            {{ $candidates->links() }}
            <div class="u-section-sep"><p class="u-mb-md">Se l’uscita non è ancora presente, puoi crearla con i dati del documento.</p><a class="btn btn-p" href="{{ route('expenses.create', ['document_id' => $document->id]) }}">Crea nuova spesa da documento</a></div>
        </x-panel>
    @endif
</x-app-layout>
