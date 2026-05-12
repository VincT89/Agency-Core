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
                    <button type="submit" class="btn btn-g btn-danger-outline">
                        Elimina
                    </button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="g-2col">
        <x-panel title="Info Transazione" dot="var(--green)" padded>
            <div class="u-text-hero u-font-mono u-mb-md">
                € {{ number_format($payment->amount, 2, ',', '.') }}
            </div>
            
            <div class="form-g mb-2">
                <div class="form-lbl">Data Pagamento</div>
                <div class="u-text-strong u-font-mono">{{ $payment->payment_date?->isoFormat('D MMMM YYYY') }}</div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Metodo</div>
                <div><x-badge :status="$payment->method" :label="$payment->method_label" /></div>
            </div>
            @if($payment->reference)
            <div class="form-g mb-2">
                <div class="form-lbl">Riferimento / CRO</div>
                <div class="u-text-strong u-font-mono">{{ $payment->reference }}</div>
            </div>
            @endif
        </x-panel>

        <x-panel title="Collegamento" dot="var(--teal)" padded>
            <div class="form-g mb-2">
                <div class="form-lbl">Fattura di Riferimento</div>
                <div class="u-text-strong">
                    @if($payment->invoice)
                        <a href="{{ route('invoices.show', $payment->invoice) }}" class="u-text-accent-link">{{ $payment->invoice->number }}</a>
                    @else
                        —
                    @endif
                </div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Cliente</div>
                <div class="u-text-strong">
                    @if($payment->client)
                        <a href="{{ route('clients.show', $payment->client) }}" class="u-text-accent-link">{{ $payment->client->name }}</a>
                    @else
                        —
                    @endif
                </div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Registrato da</div>
                <div class="u-text-strong">{{ $payment->creator?->name ?? 'Sistema' }}</div>
            </div>
            @if($payment->notes)
            <div class="form-g">
                <div class="form-lbl">Note</div>
                <div class="u-text-muted u-whitespace-pre-wrap">{{ $payment->notes }}</div>
            </div>
            @endif
        </x-panel>
    </div>
</x-app-layout>