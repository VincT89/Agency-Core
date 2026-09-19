<x-app-layout title="Previsione entrate e uscite">
    <x-page-header><x-slot:title>Previsione entrate e uscite</x-slot:title></x-page-header>
    @include('expenses.partials.navigation')
    <p class="u-mb-md">Dal {{ $start->format('d/m/Y') }} al {{ $end->format('d/m/Y') }}. Gli importi sono in euro. Il saldo previsto considera incassi registrati, fatture ancora da incassare, entrate manuali e spese, comprese le ricorrenze.</p>
    <x-panel title="Saldo di partenza" padded>
        <form class="finance-form" method="POST" action="{{ route('expenses.forecast.balance') }}">
            @csrf @method('PUT')
            <div class="form-row">
                <x-form-group label="Saldo disponibile a inizio giornata (EUR)" name="opening_balance" required><input id="field-opening-balance" type="number" step="0.01" name="opening_balance" class="form-in" value="{{ old('opening_balance', $setting?->opening_balance) }}" required></x-form-group>
                <x-form-group label="Data del saldo iniziale" name="balance_date" required><input id="field-balance-date" type="date" name="balance_date" class="form-in" max="{{ today()->toDateString() }}" value="{{ old('balance_date', $setting?->balance_date?->toDateString() ?? today()->toDateString()) }}" required></x-form-group>
            </div>
            <p class="u-text-meta u-mb-md">Indica il saldo effettivo all’inizio della giornata scelta. I movimenti già avvenuti prima di quella data sono esclusi dal calcolo. Il saldo è condiviso con gli utenti dell’amministrazione.</p>
            <button type="submit" class="btn btn-p">Aggiorna saldo iniziale</button>
        </form>
    </x-panel>
    <form class="ticket-search-form u-mt-lg" method="GET">
        <x-form-group label="Orizzonte della previsione" name="months"><select id="field-months" name="months" class="form-sel">@foreach([6,12,24] as $value)<option value="{{ $value }}" @selected($months === $value)>{{ $value }} mesi</option>@endforeach</select></x-form-group>
        <button type="submit" class="btn btn-g">Aggiorna periodo</button>
    </form>
    @if(!$setting)<p class="u-mb-md">Imposta il saldo di partenza per vedere il saldo previsto. Intanto puoi consultare i movimenti e la variazione mensile.</p>@endif
    @if($overdue['incoming'] || $overdue['outgoing'])<p class="u-mb-md">Nel primo mese sono riportati anche gli importi ancora aperti con scadenza precedente alla data iniziale: {{ number_format($overdue['incoming'] / 100, 2, ',', '.') }} EUR da incassare e {{ number_format($overdue['outgoing'] / 100, 2, ',', '.') }} EUR da pagare. Le date originali restano nelle rispettive schede.</p>@endif
    @if($missingDueDates)<p class="u-mb-md">{{ $missingDueDates }} fatture da incassare non hanno una scadenza: sono incluse nel primo mese. Completa le date per una previsione più precisa.</p>@endif
    @if($excludedCurrencies)<p class="u-mb-md">Sono escluse {{ $excludedCurrencies }} fatture in valuta diversa dall’euro e i relativi incassi. Il prospetto non applica conversioni automatiche.</p>@endif
    <x-panel>
        <div class="t-table-wrap" tabindex="0" role="region" aria-label="Previsione mensile, tabella scorrevole"><table class="t-table finance-table">
            <thead><tr><th>Mese</th><th>Entrate registrate</th><th>Entrate attese</th><th>Uscite pagate</th><th>Uscite previste</th><th>Variazione</th><th>Saldo previsto</th></tr></thead>
            <tbody>@foreach($rows as $row)<tr>
                <th scope="row">{{ ucfirst($row['month']->translatedFormat('F Y')) }}</th>
                @foreach(['received','expected','paid','pending','net'] as $field)<td class="mono-col">{{ number_format($row[$field] / 100, 2, ',', '.') }}</td>@endforeach
                <td class="mono-col {{ $row['closing_balance'] !== null && $row['closing_balance'] < 0 ? 'finance-balance-negative' : '' }}" @if($row['closing_balance'] !== null && $row['closing_balance'] < 0) aria-label="{{ number_format($row['closing_balance'] / 100, 2, ',', '.') }} euro, saldo negativo" @endif>{{ $row['closing_balance'] === null ? 'Non impostato' : number_format($row['closing_balance'] / 100, 2, ',', '.') }}</td>
            </tr>@endforeach</tbody>
            <tfoot><tr><th scope="row">Totale periodo</th>@foreach(['received','expected','paid','pending'] as $field)<td class="mono-col">{{ number_format($totals[$field] / 100, 2, ',', '.') }}</td>@endforeach<td colspan="2"></td></tr></tfoot>
        </table></div>
    </x-panel>
    <p class="u-text-meta u-mt-md">La previsione segue le scadenze registrate: non conferma automaticamente incassi o pagamenti. Le fatture fornitori importate entrano nel calcolo quando le colleghi a una spesa.</p>
</x-app-layout>
