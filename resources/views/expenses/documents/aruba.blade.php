<x-app-layout title="Fatture ricevute da Aruba">
    <x-page-header><x-slot:title>Fatture ricevute da Aruba</x-slot:title></x-page-header>
    @include('expenses.partials.navigation')
    <p class="u-mb-md">Cerca le fatture intestate all’agenzia per data di ricezione su Aruba, scegliendo al massimo due giorni consecutivi. Il collegamento usa le credenziali Aruba e la partita IVA già configurate.</p>
    @foreach(['aruba', 'document', 'invoice_id', 'body_index'] as $errorKey) @error($errorKey)<p class="invalid-feedback u-mb-md" role="alert">{{ $message }}</p>@enderror @endforeach
    <x-panel padded>
        <form method="GET" action="{{ route('expenses.documents.aruba.search') }}" class="finance-form">
            <div class="form-row">
                <x-form-group label="Ricevute dal" name="from" required><input id="field-from" type="date" name="from" class="form-in" value="{{ old('from', $from) }}" required></x-form-group>
                <x-form-group label="Ricevute fino al" name="to" required><input id="field-to" type="date" name="to" class="form-in" value="{{ old('to', $to) }}" required></x-form-group>
            </div>
            <button class="btn btn-p" type="submit">Cerca in Aruba</button>
        </form>
    </x-panel>
    @if($result !== null)
        <x-panel title="Risultati da Aruba" padded class="u-mt-lg">
            @forelse($result['content'] as $entry)
                @foreach(($entry['invoices'] ?? []) as $bodyIndex => $invoice)
                    <article class="finance-list-item">
                        <div><p><strong>{{ $entry['sender']['description'] ?? 'Emittente non indicato' }}</strong></p><p>Fattura {{ $invoice['number'] ?? 'Numero non disponibile' }} · {{ $invoice['invoiceDate'] ?? 'Data non disponibile' }}</p><p class="u-text-meta">{{ is_numeric($invoice['totalDocument'] ?? null) ? number_format($invoice['totalDocument'], 2, ',', '.').' (valuta verificata nel documento)' : 'Importo da verificare nel documento' }}</p></div>
                        <form method="POST" action="{{ route('expenses.documents.aruba.import') }}">@csrf<input type="hidden" name="invoice_id" value="{{ $entry['id'] ?? '' }}"><input type="hidden" name="body_index" value="{{ $bodyIndex }}"><button type="submit" class="btn btn-g">Importa documento</button></form>
                    </article>
                @endforeach
            @empty <p>Nessuna fattura ricevuta nel periodo selezionato.</p> @endforelse
            <nav class="finance-nav" aria-label="Pagine delle fatture Aruba">
                @if((int)request('page', 1) > 1)<a class="btn btn-g" href="{{ route('expenses.documents.aruba.search', ['from' => $from, 'to' => $to, 'page' => (int)request('page', 1) - 1]) }}">Pagina precedente</a>@endif
                @if(!($result['last'] ?? true))<a class="btn btn-g" href="{{ route('expenses.documents.aruba.search', ['from' => $from, 'to' => $to, 'page' => (int)request('page', 1) + 1]) }}">Pagina successiva</a>@endif
            </nav>
        </x-panel>
    @endif
    <p class="u-text-meta u-mt-md">L’importazione acquisisce il documento e non registra pagamenti. Dopo l’importazione collegalo alla spesa già prevista oppure crea una nuova uscita. Le note di credito e le fatture in altre valute richiedono una gestione distinta.</p>
</x-app-layout>
