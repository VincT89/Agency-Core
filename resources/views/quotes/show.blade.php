<x-app-layout :title="$quote->title">
    <div class="page-back-row"><a href="{{ route('quotes.index') }}" class="btn btn-g">Offerte commerciali</a></div>
    <x-page-header><x-slot:title>{{ $quote->title }}</x-slot:title></x-page-header>
    <x-panel padded>
        <dl class="ticket-request-details">
            <div><dt>Offerta</dt><dd>#{{ $quote->id }}, revisione {{ $quote->revision }}</dd></div>
            <div><dt>Stato</dt><dd>{{ $quote->status_label }}</dd></div>
            <div><dt>Presentata il</dt><dd>{{ $quote->presented_at?->format('d/m/Y H:i') ?? 'Non ancora presentata' }}</dd></div>
            @if($quote->accepted_at)<div><dt>Accettata il</dt><dd>{{ $quote->accepted_at->format('d/m/Y H:i') }}</dd></div>@endif
            @if($quote->rejected_at)<div><dt>Rifiutata il</dt><dd>{{ $quote->rejected_at->format('d/m/Y H:i') }}</dd></div>@endif
        </dl>
        <h2 class="u-text-strong u-mt-lg">Dati cliente dell’offerta</h2>
        @php($customer = $quote->client_snapshot ?? $quote->client->toArray())
        <dl class="ticket-request-details">
            @foreach(['name' => 'Cliente', 'company_name' => 'Ragione sociale', 'reference_person' => 'Referente', 'email' => 'Email', 'phone' => 'Telefono', 'vat_number' => 'Partita IVA', 'tax_code' => 'Codice fiscale', 'address' => 'Indirizzo', 'city' => 'Comune', 'postal_code' => 'CAP', 'province' => 'Provincia'] as $field => $label)
                @if(!empty($customer[$field]))<div><dt>{{ $label }}</dt><dd>{{ $customer[$field] }}</dd></div>@endif
            @endforeach
        </dl>
        @foreach($quote->items as $item)
            <article class="commercial-history-row">
                <h2>{{ $item->name }}</h2>
                <p class="ticket-description">{{ $item->description }}</p>
                <p>Quantità {{ number_format((float) $item->quantity, 2, ',', '.') }}, prezzo unitario {{ number_format((float) $item->unit_price, 2, ',', '.') }} €</p>
                <p>Importo: <strong>{{ number_format((float) $item->total, 2, ',', '.') }} €</strong></p>
            </article>
        @endforeach
        <p class="u-text-strong u-mt-lg">Totale servizi: {{ number_format((float) $quote->total, 2, ',', '.') }} €</p>
        @if($quote->notes)<h2 class="u-text-strong u-mt-lg">Condizioni e note</h2><p class="ticket-description">{{ $quote->notes }}</p>@endif
        @if($quote->previousQuote)
            @can('view', $quote->previousQuote)<p class="u-mt-lg"><a href="{{ route('quotes.show', $quote->previousQuote) }}">Consulta la revisione precedente</a></p>@endcan
        @endif
        @if($quote->nextQuote)
            @can('view', $quote->nextQuote)<p class="u-mt-lg"><a href="{{ route('quotes.show', $quote->nextQuote) }}">Consulta la revisione successiva</a></p>@endcan
        @endif
        <div class="commercial-actions u-mt-lg">
            <a href="{{ route('clients.show', $quote->client) }}" class="btn btn-g">Storico cliente</a>
            @if($quote->ticket) @can('view', $quote->ticket)<a href="{{ route('tickets.show', $quote->ticket) }}" class="btn btn-g">Richiesta originale</a>@endcan @endif
            @can('update', $quote)<a href="{{ route('quotes.edit', $quote) }}" class="btn btn-g">Modifica bozza</a>@endcan
            @can('present', $quote)<form method="POST" action="{{ route('quotes.present', $quote) }}">@csrf<button class="btn btn-p" type="submit">Registra come presentata</button></form>@endcan
            @can('respond', $quote)
                <form method="POST" action="{{ route('quotes.accept', $quote) }}">@csrf<button class="btn btn-p" type="submit">Registra accettazione</button></form>
                <form method="POST" action="{{ route('quotes.reject', $quote) }}">@csrf<button class="btn btn-g" type="submit">Registra rifiuto</button></form>
            @endcan
            @can('revise', $quote)<form method="POST" action="{{ route('quotes.revise', $quote) }}">@csrf<button class="btn btn-g" type="submit">Prepara revisione</button></form>@endcan
            @can('createProject', $quote)<a href="{{ route('quotes.project.create', $quote) }}" class="btn btn-p">{{ $quote->project_id ? 'Apri progetto' : 'Crea progetto' }}</a>@endcan
        </div>
    </x-panel>
</x-app-layout>
