<x-app-layout title="Fattura {{ $invoice->number }}">
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
                    <button type="submit" class="btn btn-g" style="color:var(--red);border-color:rgba(245,75,75,.3)">
                        Elimina
                    </button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="g-invoice">
            <x-panel title="Dati Generali" dot="var(--accent)" padded>
                <div class="g-2col">
                    <div>
                        <div class="form-lbl" style="margin-bottom:4px;">Cliente</div>
                        <div style="font-family:var(--sans);font-weight:600;font-size:16px;">
                            @if($invoice->client)
                                <a href="{{ route('clients.show', $invoice->client) }}" style="color:var(--text);text-decoration:none">{{ $invoice->client->name }}</a>
                            @else — @endif
                        </div>
                    </div>
                    <div>
                        <div class="form-lbl" style="margin-bottom:4px;">Progetto</div>
                        <div style="font-family:var(--sans);font-weight:500;font-size:15px;">
                            @if($invoice->project)
                                <a href="{{ route('projects.show', $invoice->project) }}" style="color:var(--text2);text-decoration:none">{{ $invoice->project->name }}</a>
                            @else — @endif
                        </div>
                    </div>
                </div>
                
                <div class="g-2col" style="margin-top:20px;padding-top:20px;border-top:1px solid var(--line);">
                    <div>
                        <div class="form-lbl" style="margin-bottom:4px;">Emissione</div>
                        <div style="font-family:var(--mono);color:var(--text);">{{ $invoice->issue_date?->format('d/m/Y') ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="form-lbl" style="margin-bottom:4px;">Scadenza</div>
                        <div style="font-family:var(--mono);color:{{ $invoice->due_date?->isPast() && $invoice->residual > 0 ? 'var(--red)' : 'var(--text)' }};">
                            {{ $invoice->due_date?->format('d/m/Y') ?? '—' }}
                        </div>
                    </div>
                </div>
            </x-panel>
            
            <div style="margin-top:20px">
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
                            <tr onclick="window.location='{{ route('payments.show', $payment) }}'" style="cursor:pointer">
                                <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                                <td><x-badge :status="$payment->method" :label="$payment->method_label" /></td>
                                <td class="mono-col">€ {{ number_format($payment->amount, 2, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" style="text-align:center;color:var(--text3);padding:16px">Nessun pagamento registrato</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-panel>
            </div>
        </div>

        <div>
            <x-panel title="Riepilogo Economico" dot="var(--green)" padded style="background:var(--bg2);">
                <div style="text-align:center;margin-bottom:20px;">
                    <div class="form-lbl" style="margin-bottom:4px;">Totale Fattura</div>
                    <div style="font-size:28px;font-weight:700;font-family:var(--mono);color:var(--text);">€ {{ number_format($invoice->total, 2, ',', '.') }}</div>
                    <div style="font-size:12px;color:var(--text3);margin-top:4px;">
                        Imponibile: € {{ number_format($invoice->subtotal, 2, ',', '.') }} | Imposte: € {{ number_format($invoice->tax_amount, 2, ',', '.') }}
                    </div>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:white;border-radius:6px;border:1px solid var(--line);margin-bottom:8px;">
                    <span style="font-size:13px;color:var(--text2);">Incassato</span>
                    <strong style="font-family:var(--mono);color:var(--green);font-size:18px;">€ {{ number_format($invoice->paid_total, 2, ',', '.') }}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:white;border-radius:6px;border:1px solid var(--line);margin-bottom:16px;">
                    <span style="font-size:13px;color:var(--text2);">Da Incassare</span>
                    <strong style="font-family:var(--mono);color:{{ $invoice->residual > 0 ? 'var(--red)' : 'var(--text3)' }};font-size:18px;">€ {{ number_format($invoice->residual, 2, ',', '.') }}</strong>
                </div>

                @php
                    $percent = $invoice->total > 0 ? ($invoice->paid_total / $invoice->total) * 100 : 0;
                @endphp
                <div style="margin-top:10px;">
                    <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px;color:var(--text3);">
                        <span>Avanzamento</span>
                        <span>{{ round($percent) }}%</span>
                    </div>
                    <x-workload-bar :percent="$percent" />
                </div>
            </x-panel>
        </div>
    </div>


    
    <x-audit-timeline :logs="$invoice->auditLogs" />

    {{-- Allegati --}}
    <x-attachments-panel :model="$invoice" />
</x-app-layout>