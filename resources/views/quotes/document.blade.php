<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preventivo {{ $reference }} - {{ $customer['company_name'] ?? $customer['name'] ?? '' }}</title>
    @vite(['resources/css/quote-document.css', 'resources/js/quote-document.js'])
</head>
<body>
    <nav class="document-toolbar" aria-label="Azioni preventivo">
        <a href="{{ route('quotes.show', $quote) }}">Torna all’offerta</a>
        @can('update', $quote)<a href="{{ route('quotes.edit', $quote) }}">Modifica bozza</a>@endcan
        <button type="button" data-quote-print>Stampa / Salva PDF</button>
        <p>Per salvare il documento, scegli “Salva come PDF” nella finestra di stampa. Usa A4 e disattiva le intestazioni e i piè di pagina del browser.</p>
    </nav>
    <main class="quote-paper">
        <header class="document-header">
            <div class="document-issuer">
                <img src="{{ asset('images/logo.png') }}" alt="Sodano Consulting" class="document-logo" width="76" height="88">
                <div>
                    <strong>{{ $issuer['legal_name'] ?? '' }}</strong>
                    @if(filled($issuer['address'] ?? null))<div>{{ $issuer['address'] }}</div>@endif
                    <div>{{ $issuer['postal_code'] ?? '' }} {{ $issuer['city'] ?? '' }} @if(filled($issuer['province'] ?? null))({{ $issuer['province'] }})@endif</div>
                    @if(filled($issuer['vat_number'] ?? null))<div>P.IVA: {{ $issuer['vat_number'] }}</div>@endif
                    @if(filled($issuer['tax_code'] ?? null) && ($issuer['tax_code'] ?? null) !== ($issuer['vat_number'] ?? null))<div>C.F.: {{ $issuer['tax_code'] }}</div>@endif
                </div>
            </div>
            <div class="document-customer">
                <strong>{{ ($customer['company_name'] ?? null) ?: ($customer['name'] ?? '') }}</strong>
                @if(filled($customer['reference_person'] ?? null))<div>Alla cortese attenzione di {{ $customer['reference_person'] }}</div>@endif
                @if(filled($customer['address'] ?? null))<div>{{ $customer['address'] }}</div>@endif
                <div>{{ $customer['postal_code'] ?? '' }} {{ $customer['city'] ?? '' }} @if(filled($customer['province'] ?? null))({{ $customer['province'] }})@endif</div>
                @if(filled($customer['country'] ?? null))<div>{{ $customer['country'] }}</div>@endif
                @if(filled($customer['vat_number'] ?? null))<div>P.IVA: {{ $customer['vat_number'] }}</div>@endif
                @if(filled($customer['tax_code'] ?? null) && ($customer['tax_code'] ?? null) !== ($customer['vat_number'] ?? null))<div>C.F.: {{ $customer['tax_code'] }}</div>@endif
                @if(filled($customer['email'] ?? null))<div>{{ $customer['email'] }}</div>@endif
                @if(filled($customer['phone'] ?? null))<div>{{ $customer['phone'] }}</div>@endif
            </div>
        </header>
        <h1>{{ $quote->title }}</h1>
        <p class="document-reference">Preventivo {{ $reference }} del {{ ($quote->document_date ?? $quote->created_at)->format('d/m/Y') }}
            @if($quote->revision > 1) - Revisione {{ $quote->revision }} @endif
            @if($quote->status === 'draft') - Bozza @endif
        </p>
        <table class="document-services">
            <colgroup><col class="category-column"><col class="summary-column"><col class="delivery-column"></colgroup>
            <thead><tr><th scope="col">Categorie servizi</th><th scope="col">Servizi inclusi</th><th scope="col">Tempi di consegna</th></tr></thead>
            <tbody>
                @foreach($quote->items as $item)
                    <tr><th scope="row">{{ $item->name }}</th><td class="document-copy">{{ $item->summary ?: ($item->description ? 'Vedi descrizione dettagliata del servizio.' : '') }}</td><td class="document-copy">{{ $item->delivery_summary ?: $item->delivery_terms }}</td></tr>
                @endforeach
                <tr class="document-total"><td colspan="2"></td><td><strong>Costo del servizio: {{ number_format((float) $quote->total, 2, ',', '.') }} €@if($quote->price_note) {{ $quote->price_note }}@endif</strong></td></tr>
            </tbody>
        </table>
        @if($quote->introduction)
            <section class="document-section"><h2>Descrizione Progetto e Preventivo</h2><div class="document-copy">{{ $quote->introduction }}</div></section>
        @endif
        @foreach($quote->items as $item)
            @if($item->description || $item->delivery_terms)
                <section class="document-section"><h2>{{ $item->name }}</h2>
                    @if($item->description)<div class="document-copy">{{ $item->description }}</div>@endif
                    @if($item->delivery_terms)<p class="document-copy document-timing">Tempistiche: {{ $item->delivery_terms }}</p>@endif
                </section>
            @endif
        @endforeach
        @if($quote->payment_terms)<section class="document-section"><h2>Forma di pagamento:</h2><div class="document-copy">{{ $quote->payment_terms }}</div></section>@endif
        @if($quote->notes)<div class="document-notes document-copy">{{ $quote->notes }}</div>@endif
    </main>
</body>
</html>
