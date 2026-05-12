<div class="g-4col">
    <x-panel padded>
        <x-stat-card label="Incassi Mese (30gg)" value="€ {{ number_format($totalCollected30d, 2, ',', '.') }}" sub="Cash flow in" />
    </x-panel>
    <x-panel padded>
        <x-stat-card label="Credito Residuo" value="€ {{ number_format($totalOutstanding, 2, ',', '.') }}" sub="Valore nominale scoperto" />
    </x-panel>
    <x-panel padded>
        <x-stat-card label="Fatture Aperte" value="{{ $openInvoicesCount }}" sub="Documenti attivi" />
    </x-panel>
    <x-panel padded>
        <x-stat-card label="Fatture Scadute" value="{{ $overdueInvoicesCount }}" sub="Da sollecitare urgentemente" :highlight="$overdueInvoicesCount > 0" :subAlert="$overdueInvoicesCount > 0" />
    </x-panel>
</div>

<div class="g-2col">
    <div>
        <x-panel title="Scadenze Imminenti" dot="var(--accent)">
            <table class="t-table">
                <thead><tr><th>Data</th><th>Fattura</th><th>Importo</th></tr></thead>
                <tbody>
                    @forelse($upcomingDeadlines as $invoice)
                    <tr x-data @click="window.Livewire.navigate('{{ route('invoices.show', $invoice) }}')" class="u-cursor-pointer hover-bg">
                        <td class="mono-col {{ $invoice->due_date < now() ? 'u-text-danger' : '' }}">{{ $invoice->due_date->format('d/m/Y') }}</td>
                        <td>{{ $invoice->number }}</td>
                        <td class="mono-col">€ {{ number_format($invoice->total, 2, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="u-text-center u-text-muted u-p-md">Nessuna scadenza a breve</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>
    </div>

    <div>
        <x-panel title="Ultimi Pagamenti" dot="var(--green)">
            <table class="t-table">
                <thead><tr><th>Data</th><th>Importo</th><th>Fattura</th></tr></thead>
                <tbody>
                    @forelse($recentPayments as $payment)
                    <tr>
                        <td class="mono-col">{{ $payment->payment_date->format('d/m/Y') }}</td>
                        <td class="mono-col">€ {{ number_format($payment->amount, 2, ',', '.') }}</td>
                        <td><a href="{{ route('invoices.show', $payment->invoice_id) }}" class="u-text-inherit u-no-underline">{{ $payment->invoice?->number }}</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="u-text-center u-text-muted u-p-md">Nessun pagamento recente</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>
    </div>
</div>
