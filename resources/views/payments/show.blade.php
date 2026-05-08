<x-app-layout title="Pagamento">
    <div class="u-mb-lg u-flex-end">
        <a href="{{ route('payments.index') }}" wire:navigate class="btn btn-g u-flex-center u-gap-xs">
            <i data-lucide="arrow-left" class="u-icon-sm"></i> Torna alla lista
        </a>
    </div>
    <x-page-header
        eyebrow="Dettaglio · Pagamento"
        
    >
    <x-slot:title><strong>Riepilogo Incasso</strong></x-slot:title>
        <x-slot:actions>
            @can('update', $payment)
                <a href="{{ route('payments.edit', $payment) }}" class="btn btn-g">Modifica</a>
            @endcan
        
            @can('delete', $payment)
                <form action="{{ route('payments.destroy', $payment) }}" method="POST"
                      onsubmit="return confirm('Eliminare questo pagamento? Il totale della fattura verrà ricalcolato.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-g" style="color:var(--red);border-color:rgba(245,75,75,.3)">
                        Elimina
                    </button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="g-2col">
        <x-panel title="Info Transazione" dot="var(--green)" padded>
            <div style="font-size:32px;font-family:var(--mono);color:var(--text);margin-bottom:20px;font-weight:600;">
                € {{ number_format($payment->amount, 2, ',', '.') }}
            </div>
            
            <div class="form-g mb-2">
                <div class="form-lbl">Data Pagamento</div>
                <div style="color:var(--text);font-family:var(--mono)">{{ $payment->payment_date?->isoFormat('D MMMM YYYY') }}</div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Metodo</div>
                <div><x-badge :status="$payment->method" :label="$payment->method_label" /></div>
            </div>
            @if($payment->reference)
            <div class="form-g mb-2">
                <div class="form-lbl">Riferimento / CRO</div>
                <div style="color:var(--text);font-family:var(--mono)">{{ $payment->reference }}</div>
            </div>
            @endif
        </x-panel>

        <x-panel title="Collegamento" dot="var(--teal)" padded>
            <div class="form-g mb-2">
                <div class="form-lbl">Fattura di Riferimento</div>
                <div style="color:var(--text);font-family:var(--sans)">
                    @if($payment->invoice)
                        <a href="{{ route('invoices.show', $payment->invoice) }}" style="color:var(--accent);text-decoration:none">{{ $payment->invoice->number }}</a>
                    @else
                        —
                    @endif
                </div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Cliente</div>
                <div style="color:var(--text);font-family:var(--sans)">
                    @if($payment->client)
                        <a href="{{ route('clients.show', $payment->client) }}" style="color:var(--accent);text-decoration:none">{{ $payment->client->name }}</a>
                    @else
                        —
                    @endif
                </div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Registrato da</div>
                <div style="color:var(--text);font-family:var(--sans)">{{ $payment->creator?->name ?? 'Sistema' }}</div>
            </div>
            @if($payment->notes)
            <div class="form-g">
                <div class="form-lbl">Note</div>
                <div style="color:var(--text3);font-size:13px;white-space:pre-wrap">{{ $payment->notes }}</div>
            </div>
            @endif
        </x-panel>
    </div>
</x-app-layout>