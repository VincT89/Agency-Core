<x-app-layout title="Pagamenti">
    <x-page-header
        eyebrow="Modulo · Amministrazione"
        
        :meta="$payments->total() . ' totali'"
    >
    <x-slot:title><strong>Pagamenti</strong></x-slot:title>
        <x-slot:actions>
            @can('create', App\Models\Payment::class)
                <a href="{{ route('payments.create') }}" class="btn btn-p">+ Registra pagamento</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-panel>
        <table class="t-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Fattura Rif.</th>
                    <th>Cliente</th>
                    <th>Importo</th>
                    <th>Metodo</th>
                    <th>Registrato da</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                <tr onclick="window.location='{{ route('payments.show', $payment) }}'" style="cursor:pointer">
                    <td class="mono-col">{{ $payment->payment_date?->format('d/m/Y') }}</td>
                    <td>
                        @if($payment->invoice)
                            <a href="{{ route('invoices.show', $payment->invoice) }}" style="color:var(--accent);text-decoration:none">{{ $payment->invoice->number }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $payment->client?->name ?? '—' }}</td>
                    <td class="mono-col">€ {{ number_format($payment->amount, 2, ',', '.') }}</td>
                    <td><x-badge :status="$payment->method" :label="$payment->method_label" /></td>
                    <td>{{ $payment->creator?->name ?? 'Sistema' }}</td>
                    <td>
                        @can('update', $payment)
                            <a href="{{ route('payments.edit', $payment) }}" class="btn-icon" onclick="event.stopPropagation()">✎</a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;color:var(--text3);padding:32px">Nessun pagamento trovato</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $payments->links() }}
    </x-panel>
</x-app-layout>