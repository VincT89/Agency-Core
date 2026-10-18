<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} {{ $document['number'] }}</title>
    @vite(['resources/css/invoice-document.css', 'resources/js/invoice-document.js'])
</head>
<body>
    @php
        $decimal = static function ($value, $precision = 2) {
            if ($value === '' || $value === null) return 'Non indicato';
            if (! is_numeric($value)) return (string) $value;
            $digits = min(8, max($precision, strlen(explode('.', (string) $value)[1] ?? '')));
            return number_format((float) $value, $digits, ',', '.');
        };
        $money = fn ($value) => $decimal($value).' '.($document['currency'] === 'EUR' ? '€' : $document['currency']);
        $date = static function ($value) {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? implode('/', array_reverse(explode('-', $value))) : ($value ?: 'Non indicata');
        };
    @endphp
    <nav class="invoice-document-toolbar" aria-label="Azioni documento">
        <a href="{{ $backUrl }}">Torna al dettaglio</a>
        <a href="{{ route('attachments.download', $document['attachment']) }}">Scarica originale</a>
        <button type="button" data-invoice-print>Stampa / Salva PDF</button>
        <p>Per salvare il PDF, scegli “Salva come PDF” nella finestra di stampa. Formato A4, senza intestazioni e piè di pagina del browser.</p>
    </nav>
    <main class="invoice-paper">
        <header class="invoice-document-heading">
            <div><h1>{{ $title }}</h1><p class="invoice-document-number">{{ $document['number'] }}</p></div>
            <dl><div><dt>Data documento</dt><dd>{{ $date($document['date']) }}</dd></div><div><dt>Tipo documento</dt><dd>{{ $document['type'] }}</dd></div></dl>
        </header>

        <div class="invoice-document-parties">
            @foreach(['issuer' => 'Emittente', 'customer' => 'Cliente'] as $key => $label)
                @php($party = $document[$key])
                <section aria-label="{{ $label }}">
                    <h2>{{ $label }}</h2>
                    <strong>{{ $party['name'] ?: 'Non indicato' }}</strong>
                    @if($party['address'])<p>{{ $party['address'] }}</p>@endif
                    <p>{{ $party['postal_code'] }} {{ $party['city'] }} @if($party['province'])({{ $party['province'] }})@endif {{ $party['country'] }}</p>
                    @if($party['vat_number'])<p>P. IVA {{ $party['vat_country'] }}{{ $party['vat_number'] }}</p>@endif
                    @if($party['tax_code'])<p>Codice fiscale {{ $party['tax_code'] }}</p>@endif
                    @if($party['regime'])<p>Regime fiscale {{ $party['regime'] }}</p>@endif
                    @if($key === 'customer' && $document['recipient_code'])<p>Codice destinatario {{ $document['recipient_code'] }}</p>@endif
                    @if($key === 'customer' && $document['recipient_pec'])<p>PEC {{ $document['recipient_pec'] }}</p>@endif
                </section>
            @endforeach
        </div>

        @if($document['notes'])
            <section class="invoice-document-section"><h2>Causale</h2>@foreach($document['notes'] as $note)<p class="invoice-document-copy">{{ $note }}</p>@endforeach</section>
        @endif
        @if($document['references'])
            <section class="invoice-document-section"><h2>Riferimenti del documento</h2>
                @foreach($document['references'] as $reference)
                    <p>{{ $reference['label'] }} {{ $reference['number'] }}@if($reference['date']) del {{ $date($reference['date']) }}@endif
                        @if($reference['cup'])<span class="invoice-document-reference">CUP {{ $reference['cup'] }}</span>@endif
                        @if($reference['cig'])<span class="invoice-document-reference">CIG {{ $reference['cig'] }}</span>@endif
                    </p>
                @endforeach
            </section>
        @endif

        <section class="invoice-document-section">
            <h2>Descrizione e importi</h2>
            <table class="invoice-document-table invoice-document-lines">
                <caption class="invoice-document-sr">Voci del documento, importi in {{ $document['currency'] }}</caption>
                <colgroup><col class="description-column"><col class="quantity-column"><col class="price-column"><col class="vat-column"><col class="total-column"></colgroup>
                <thead><tr><th scope="col">Descrizione</th><th scope="col">Quantità</th><th scope="col">Prezzo unitario</th><th scope="col">IVA</th><th scope="col">Importo</th></tr></thead>
                <tbody>
                    @forelse($document['items'] as $item)
                        <tr>
                            <td class="invoice-document-description"><p class="invoice-document-copy">{{ $item['description'] }}</p>
                                @if($item['period_from'] || $item['period_to'])<p class="invoice-document-small">Periodo: {{ $date($item['period_from']) }} - {{ $date($item['period_to']) }}</p>@endif
                                @foreach($item['adjustments'] as $adjustment)<p class="invoice-document-small">{{ $adjustment['type'] === 'SC' ? 'Sconto' : ($adjustment['type'] === 'MG' ? 'Maggiorazione' : $adjustment['type']) }}@if($adjustment['percentage'] !== '') {{ $decimal($adjustment['percentage']) }}%@endif @if($adjustment['amount'] !== '') {{ $money($adjustment['amount']) }}@endif</p>@endforeach
                            </td>
                            <td data-label="Quantità">{{ $decimal($item['quantity'], 0) }} {{ $item['unit'] }}</td>
                            <td data-label="Prezzo unitario">{{ $decimal($item['unit_price']) }}</td>
                            <td data-label="IVA">{{ $item['vat_rate'] !== '' ? $decimal($item['vat_rate']).'%' : 'Non indicata' }}@if($item['nature'])<span class="invoice-document-reference">{{ $item['nature'] }}</span>@endif</td>
                            <td data-label="Importo">{{ $decimal($item['total']) }}</td>
                        </tr>
                    @empty<tr><td colspan="5">Nessuna voce presente nel documento originale.</td></tr>@endforelse
                </tbody>
            </table>
        </section>

        <section class="invoice-document-section">
            <h2>Riepilogo IVA</h2>
            <table class="invoice-document-table invoice-document-taxes">
                <thead><tr><th scope="col">Aliquota / Natura</th><th scope="col">Imponibile</th><th scope="col">Imposta</th><th scope="col">Esigibilità</th></tr></thead>
                <tbody>@foreach($document['taxes'] as $tax)
                    <tr><td data-label="Aliquota / Natura">{{ $tax['rate'] !== '' ? $decimal($tax['rate']).'%' : '' }} {{ $tax['nature'] }}@if($tax['reference'])<p class="invoice-document-small">{{ $tax['reference'] }}</p>@endif</td>
                        <td data-label="Imponibile">{{ $decimal($tax['taxable']) }}</td><td data-label="Imposta">{{ $decimal($tax['amount']) }}</td>
                        <td data-label="Esigibilità">{{ match($tax['exigibility']) { 'S' => 'Split payment', 'I' => 'Immediata', 'D' => 'Differita', default => $tax['exigibility'] ?: 'Non indicata' } }}</td></tr>
                @endforeach</tbody>
            </table>
        </section>

        <dl class="invoice-document-totals">
            <div><dt>Imponibile</dt><dd>{{ $money($document['subtotal']) }}</dd></div>
            <div><dt>IVA</dt><dd>{{ $money($document['tax']) }}</dd></div>
            @if($document['stamp'] !== '')<div><dt>Bollo indicato nel documento</dt><dd>{{ $money($document['stamp']) }}</dd></div>@endif
            @if($document['rounding'] !== '')<div><dt>Arrotondamento</dt><dd>{{ $money($document['rounding']) }}</dd></div>@endif
            <div class="invoice-document-total"><dt>Totale documento</dt><dd>{{ $money($document['total']) }}</dd></div>
            @if((float) $document['split_tax'] > 0)
                <div><dt>IVA in split payment</dt><dd>{{ $money($document['split_tax']) }}</dd></div>
                <div class="invoice-document-net"><dt>{{ $document['type'] === 'TD04' ? 'Netto della nota di credito' : 'Netto da pagare' }}</dt><dd>{{ $money($document['payable']) }}</dd></div>
            @endif
        </dl>

        @if($document['payments'])
            <section class="invoice-document-section">
                <h2>Pagamento previsto nel documento</h2>
                @foreach($document['payments'] as $payment)
                    <div class="invoice-document-payment">
                        <dl><div><dt>Modalità</dt><dd>{{ \App\Enums\Finance\FatturaPaPaymentMethod::tryFrom($payment['method'])?->label() ?? ($payment['method'] ?: 'Non indicata') }}</dd></div><div><dt>Scadenza</dt><dd>{{ $date($payment['date']) }}</dd></div><div><dt>Importo</dt><dd>{{ $payment['amount'] !== '' ? $money($payment['amount']) : 'Non indicato' }}</dd></div></dl>
                        @if($payment['beneficiary'])<p>Beneficiario: {{ $payment['beneficiary'] }}</p>@endif
                        @if($payment['bank'])<p>{{ $payment['bank'] }}</p>@endif
                        @if($payment['iban'])<p>IBAN: {{ $payment['iban'] }}</p>@endif
                        @if($payment['bic'])<p>BIC: {{ $payment['bic'] }}</p>@endif
                    </div>
                @endforeach
            </section>
        @endif
        <footer class="invoice-document-footer">Copia di cortesia generata dall’XML importato. Il documento originale è disponibile nel gestionale.</footer>
    </main>
</body>
</html>
