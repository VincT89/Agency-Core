<x-app-layout title="Fattura {{ $invoice->number }}">
    <div class="u-mb-lg u-flex-end">
        <a href="{{ route('invoices.index') }}" wire:navigate class="btn btn-g u-flex-center u-gap-xs">
            <i data-lucide="arrow-left" class="u-icon-sm"></i> Torna alla lista
        </a>
    </div>
    <x-page-header
        eyebrow="Fattura num."
        
    >
    <x-slot:title><strong>{{ $invoice->number }}</strong></x-slot:title>
        <x-slot:actions>
            <x-badge :status="$invoice->status" :label="$invoice->status_label" />
            @can('update', $invoice)
                <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-g">Modifica</a>
            @endcan

            @can('create', App\Models\Payment::class)
                @if(!in_array($invoice->status, ['paid', 'cancelled']))
                    <a href="{{ route('payments.create') }}?invoice_id={{ $invoice->id }}"
                       class="btn btn-p">
                        + Registra pagamento
                    </a>
                @endif
            @endcan
        
            @can('delete', $invoice)
                <form action="{{ route('invoices.destroy', $invoice) }}" method="POST"
                      onsubmit="return confirm('Eliminare la fattura {{ addslashes($invoice->number) }}? Operazione irreversibile.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-g btn-danger-outline">
                        Elimina
                    </button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>




    <div class="g-invoice inv-panel-gap">
        <div class="g-invoice-left">
            <x-panel title="Dati Generali" dot="var(--accent)" padded>
                <div class="g-2col">
                    <div>
                        <div class="form-lbl inv-lbl">Cliente</div>
                        <div class="inv-client-name">
                            @if($invoice->client)
                                <a href="{{ route('clients.show', $invoice->client) }}">{{ $invoice->client->name }}</a>
                            @else — @endif
                        </div>
                    </div>
                    <div>
                        <div class="form-lbl inv-lbl">Progetto</div>
                        <div class="inv-project-name">
                            @if($invoice->project)
                                <a href="{{ route('projects.show', $invoice->project) }}">{{ $invoice->project->name }}</a>
                            @else — @endif
                        </div>
                    </div>
                </div>
                
                <div class="g-2col inv-section-sep">
                    <div>
                        <div class="form-lbl inv-lbl">Emissione</div>
                        <div class="inv-date">{{ $invoice->issue_date?->format('d/m/Y') ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="form-lbl inv-lbl">Scadenza</div>
                        <div class="{{ $invoice->due_date?->isPast() && $invoice->residual > 0 ? 'inv-date-overdue' : 'inv-date' }}">
                            {{ $invoice->due_date?->format('d/m/Y') ?? '—' }}
                        </div>
                    </div>
                </div>
            </x-panel>
            
            <div class="inv-panel-gap">
                <x-panel title="Pagamenti Associati">
                    <table class="t-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Metodo</th>
                                <th>Importo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoice->payments ?? [] as $payment)
                            <tr onclick="window.location='{{ route('payments.show', $payment) }}'" class="cursor-pointer">
                                <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                                <td><x-badge :status="$payment->method" :label="$payment->method_label" /></td>
                                <td class="mono-col">€ {{ number_format($payment->amount, 2, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="inv-empty-cell">Nessun pagamento registrato</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-panel>
            </div>
        </div>

        <div class="g-invoice-right">
            <x-panel title="Riepilogo Economico" dot="var(--green)" padded>
                <div class="inv-total-hero">
                    <div class="form-lbl inv-lbl">Totale Fattura</div>
                    <div class="inv-total-hero-value">€ {{ number_format($invoice->total, 2, ',', '.') }}</div>
                    <div class="inv-total-hero-sub">
                        Imponibile: € {{ number_format($invoice->subtotal, 2, ',', '.') }} | Imposte: € {{ number_format($invoice->tax_amount, 2, ',', '.') }}
                    </div>
                </div>

                <div class="inv-summary-row">
                    <span class="inv-summary-label">Incassato</span>
                    <strong class="inv-summary-value green">€ {{ number_format($invoice->paid_total, 2, ',', '.') }}</strong>
                </div>
                <div class="inv-summary-row inv-summary-row-last">
                    <span class="inv-summary-label">Da Incassare</span>
                    <strong class="inv-summary-value {{ $invoice->residual > 0 ? 'red' : 'muted' }}">€ {{ number_format($invoice->residual, 2, ',', '.') }}</strong>
                </div>

                @php
                    $percent = $invoice->total > 0 ? ($invoice->paid_total / $invoice->total) * 100 : 0;
                @endphp
                <div class="inv-progress-wrap">
                    <div class="inv-progress-hd">
                        <span>Avanzamento</span>
                        <span>{{ round($percent) }}%</span>
                    </div>
                    <x-workload-bar :percent="$percent" />
                </div>
            </x-panel>
        </div>
    </div>

        <div class="inv-panel-gap">
            <x-panel title="Voci Fattura" dot="var(--accent)">
                @can('update', $invoice)
                    <form id="add-item-form" action="{{ route('invoices.items.store', $invoice) }}" method="POST">
                        @csrf
                    </form>
                @endcan
                <table class="t-table">
                    <thead>
                        <tr>
                            <th>Descrizione</th>
                            <th>Qtà</th>
                            <th>Prezzo Unit.</th>
                            <th>Totale</th>
                            <th>Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoice->items as $item)
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td>{{ fmod($item->quantity, 1) == 0 ? (int)$item->quantity : $item->quantity }}</td>
                            <td class="mono-col">€ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                            <td class="mono-col">€ {{ number_format($item->total, 2, ',', '.') }}</td>
                            <td>
                                @php
                                    $typeLabel = '—';
                                    if ($item->billable_type === 'App\Models\MarketingCampaignPeriod') {
                                        $typeLabel = 'Contratto';
                                    } elseif ($item->billable_type === 'App\Models\MarketingCampaignExtra') {
                                        $typeLabel = 'Extra';
                                    } elseif ($item->billable_type === null) {
                                        $typeLabel = 'Manuale';
                                    }
                                @endphp
                                @if($typeLabel !== '—')
                                    <span class="inv-type-badge {{ $typeLabel === 'Manuale' ? 'manual' : '' }}">{{ $typeLabel }}</span>
                                    @if($typeLabel === 'Manuale')
                                        @can('update', $invoice)
                                            <form action="{{ route('invoices.items.destroy', [$invoice, $item]) }}"
                                                  method="POST"
                                                  class="inv-item-del-form"
                                                  onsubmit="return confirm('Eliminare questa voce?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn-ghost-danger btn-xs" title="Elimina voce">
                                                    <i data-lucide="trash-2" class="u-icon-sm"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    @endif
                                @else
                                    <span class="text-muted">{{ $typeLabel }}</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="inv-empty-cell">Nessuna voce presente in fattura.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        @can('update', $invoice)
                        <tr style="background: var(--bg2); border-top: 1px dashed var(--line);">
                            <td style="padding: 12px 16px;">
                                <input type="text" form="add-item-form" name="description" class="form-in w-full"
                                       placeholder="Descrizione voce (es. Lavori extra)"
                                       value="{{ old('description') }}" required>
                            </td>
                            <td style="padding: 12px 16px; width: 100px;">
                                <input type="number" form="add-item-form" name="quantity" class="form-in text-right"
                                       placeholder="Qtà" min="0.01" step="0.01" value="{{ old('quantity', 1) }}" required>
                            </td>
                            <td style="padding: 12px 16px; width: 140px;">
                                <input type="number" form="add-item-form" name="unit_price" class="form-in text-right"
                                       placeholder="€ Prezzo" min="0" step="0.01"
                                       value="{{ old('unit_price') }}" required>
                            </td>
                            <td style="padding: 12px 16px;"></td>
                            <td style="padding: 12px 16px; text-align: right; width: 120px;">
                                <button type="submit" form="add-item-form" class="btn btn-s" style="width: 100%;">
                                    <i data-lucide="plus" class="u-icon-sm"></i> Aggiungi
                                </button>
                            </td>
                        </tr>
                        @endcan
                    </tfoot>
                </table>

                @can('update', $invoice)
                    @if($errors->invoice_items->any())
                        @foreach($errors->invoice_items->all() as $error)
                            <div class="form-err u-mt-sm">{{ $error }}</div>
                        @endforeach
                    @endif
                @endcan
            </x-panel>
        </div>


    
    <x-audit-timeline :logs="$invoice->auditLogs" />

    {{-- Allegati --}}
    <livewire:shared.attachment-manager :model="$invoice" />
</x-app-layout>