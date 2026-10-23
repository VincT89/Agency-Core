<x-panel padded id="expense-charts">
    <div x-data="{ view: 'categories', plot: 'bar' }">
        <div class="finance-expense-heading">
            <h2 x-text="view === 'categories' ? 'Spese per categoria' : 'Andamento mensile delle spese'">Spese per categoria</h2>
            <div class="finance-expense-controls">
                <label for="expense-chart-view" class="sr-only">Vista delle spese</label>
                <select id="expense-chart-view" class="form-sel" x-model="view"><option value="categories">Per categoria</option><option value="monthly">Andamento mensile</option></select>
                <label for="expense-chart-type" class="sr-only">Tipo di grafico delle spese</label>
                <select id="expense-chart-type" class="form-sel" x-model="plot" x-show="view === 'monthly'" x-cloak><option value="bar">Barre</option><option value="line">Linee</option></select>
            </div>
        </div>
        <div x-show="view === 'categories'">
            @if($chart['entries'])
                @php($largestExpense = max(array_column($chart['entries'], 'amount')))
                <ul class="finance-expense-bars" aria-label="Spese per categoria e importo">
                    @foreach($chart['entries'] as $entry)
                        <li><a href="{{ $entry['url'] }}">
                            <span class="finance-expense-bar-label"><span>{{ $entry['name'] }}</span><strong>{{ $money($entry['amount']) }}</strong></span>
                            <span class="finance-expense-bar-track" aria-hidden="true"><span style="width: {{ $largestExpense > 0 ? round($entry['amount'] / $largestExpense * 100, 3) : 0 }}%"></span></span>
                        </a></li>
                    @endforeach
                </ul>
            @else<p class="u-text-meta">Nessuna spesa con questi filtri.</p>@endif
        </div>
        <div x-show="view === 'monthly'" x-cloak>
            @foreach(['bar', 'line'] as $display)
                <template x-if="view === 'monthly' && plot === '{{ $display }}'"><div x-data="financeChart(@js($expenseChartData), @js($expenseTrendChart), @js($drillUrl), @js($display))"><div x-ref="chart" role="img" aria-label="Andamento mensile delle spese"></div></div></template>
            @endforeach
            <details class="finance-advanced"><summary>Importi mensili</summary>
                <div class="t-table-wrap" tabindex="0" role="region" aria-label="Importi mensili delle spese"><table class="t-table"><thead><tr><th>Mese</th><th>Spese</th></tr></thead><tbody>
                    @foreach($expenseChartData['labels'] as $index => $label)<tr><th scope="row">{{ $label }}</th><td>{{ $money($expenseChartData['series'][0]['data'][$index]) }}</td></tr>@endforeach
                </tbody></table></div>
            </details>
        </div>
    </div>
</x-panel>
