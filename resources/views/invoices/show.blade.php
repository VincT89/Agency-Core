<x-app-layout title="Fattura {{ $invoice->display_number }}">
    @php
        $imported = $invoice->fiscal_status === \App\Enums\Finance\InvoiceFiscalStatus::Imported;
        $original = $invoice->attachments->firstWhere('description', 'Documento originale importato');
        $previewAvailable = $imported && $original && in_array($original->extension, ['xml', 'p7m'], true);
        $customer = $imported ? ($invoice->import_metadata['counterparty'] ?? []) : [];
        $money = fn ($value) => number_format((float) $value, 2, ',', '.').' '.($invoice->currency === 'EUR' ? '€' : $invoice->currency);
    @endphp
    <div class="invoice-detail">
        <div class="page-back-row"><a href="{{ route('invoices.index') }}" wire:navigate class="btn btn-g btn-sm">Torna alle fatture</a></div>
        <x-page-header>
            <x-slot:title>Fattura {{ $invoice->display_number }}</x-slot:title>
            <x-slot:meta>
                <div class="invoice-detail-status">
                    <span>{{ $invoice->fiscal_status_label }}</span>
                    <x-badge :status="$invoice->status" :label="$invoice->status_label" />
                </div>
            </x-slot:meta>
            <x-slot:actions>
                @if($previewAvailable)<a href="{{ route('invoices.document', $invoice) }}" class="btn btn-p">Visualizza fattura</a>@endif
                @can('changeStatus', $invoice)
                    <a href="{{ route('invoices.status.edit', $invoice) }}" class="btn btn-g">Modifica stato</a>
                @endcan
                @can('changeDueDate', $invoice)
                    <a href="{{ route('invoices.due-date.edit', $invoice) }}" class="btn btn-g">Modifica scadenza</a>
                @endcan
                @can('manageAssociations', $invoice)<a href="{{ route('invoices.associations.edit', $invoice) }}" class="btn btn-g">Modifica collegamento</a>@endcan
                @can('create', App\Models\Payment::class)
                    @if($invoice->residual > 0 && ! in_array($invoice->status, ['draft', 'cancelled'], true))
                        <a href="{{ route('payments.create', ['invoice_id' => $invoice->id]) }}" class="btn {{ $previewAvailable ? 'btn-g' : 'btn-p' }}">Registra pagamento</a>
                    @endif
                @endcan
                @can('update', $invoice)<a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-g">Modifica</a>@endcan
                @can('delete', $invoice)
                    <x-delete-modal :action="route('invoices.destroy', $invoice)" title="Elimina bozza fattura" message="Eliminare questa bozza? L’operazione non può essere annullata.">
                        <button type="button" class="btn btn-g btn-danger-outline">Elimina bozza</button>
                    </x-delete-modal>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <div class="invoice-detail-grid">
            <div class="invoice-detail-main">
                <x-panel padded class="invoice-detail-customer">
                    <x-slot:title><h2>Cliente e dati della fattura</h2></x-slot:title>
                    <div class="invoice-customer-heading">
                        <h3>{{ $customer['name'] ?? ($invoice->client?->company_name ?: $invoice->client?->name ?: 'Cliente non disponibile') }}</h3>
                        @if($invoice->client)<a href="{{ route('clients.show', $invoice->client) }}" class="invoice-text-link">Anagrafica cliente</a>@endif
                    </div>
                    @if(filled($customer['address'] ?? null))<p class="invoice-detail-muted">{{ $customer['address'] }}, {{ $customer['postal_code'] ?? '' }} {{ $customer['city'] ?? '' }} @if(filled($customer['province'] ?? null))({{ $customer['province'] }})@endif</p>@endif
                    <dl class="invoice-detail-facts">
                        <div><dt>Data fattura</dt><dd>{{ $invoice->issue_date?->format('d/m/Y') ?? 'Non indicata' }}</dd></div>
                        <div><dt>Scadenza</dt><dd class="{{ $invoice->is_overdue ? 'invoice-overdue' : '' }}">{{ $invoice->due_date?->format('d/m/Y') ?? 'Non indicata' }}@if($invoice->is_overdue) <x-badge status="overdue" label="Scaduta" />@endif</dd></div>
                        <div><dt>Tipo documento</dt><dd>{{ $invoice->fiscal_document_type ?: 'Non indicato' }}</dd></div>
                        <div><dt>{{ $invoice->hostingService ? ($invoice->hostingService->hasServiceType('domain') ? 'Dominio' : 'Hosting') : ($invoice->marketingCampaign ? 'Campagna' : 'Progetto') }}</dt><dd>
                            @if($invoice->hostingService)
                                @if($invoice->hostingService->trashed()){{ $invoice->hostingService->name }} (archiviato)
                                @else<a href="{{ route('hosting-services.show', $invoice->hostingService) }}">{{ $invoice->hostingService->name }}</a>@endif
                            @else
                            @if($invoice->project)<a href="{{ route('projects.show', $invoice->project) }}">{{ $invoice->project->name }}</a>
                            @elseif($invoice->marketingCampaign)<a href="{{ route('marketing-campaigns.show', $invoice->marketingCampaign) }}">{{ $invoice->marketingCampaign->name }}</a>
                            @else<span class="invoice-detail-muted">Nessun progetto associato</span>@endif
                            @endif
                        </dd></div>
                    </dl>
                    @if($imported)
                        <div class="invoice-source-row">
                            <p>Numero originale conservato. {{ $invoice->import_source === 'aruba' ? 'Importazione da Aruba.' : 'Importazione da file '.strtoupper($invoice->import_metadata['format'] ?? '').'.' }}</p>
                            @if($original)
                                @can('download', $original)
                                    <a href="{{ route('attachments.download', $original) }}" class="invoice-text-link">Scarica originale</a>
                                @endcan
                            @endif
                        </div>
                    @endif
                </x-panel>

                <x-panel class="invoice-detail-items">
                    @include('invoices.partials.linked-services')
                    <x-slot:title><h2>Voci della fattura</h2></x-slot:title>
                    @can('update', $invoice)
                        <x-slot:headerActions><a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-g btn-sm">Modifica voci e IVA</a></x-slot:headerActions>
                    @endcan
                    <div class="invoice-items-scroll" role="region" aria-label="Voci della fattura" tabindex="0">
                        <table class="invoice-items-table">
                            <thead><tr><th scope="col">Descrizione</th><th scope="col">Quantità</th><th scope="col">Prezzo unitario</th><th scope="col">IVA</th><th scope="col">Imponibile</th>@unless($imported)<th scope="col">Imposta</th><th scope="col">Totale</th>@endunless</tr></thead>
                            <tbody>
                                @if($imported)
                                    @forelse($invoice->import_metadata['items'] ?? [] as $item)
                                        <tr><td class="invoice-item-description">{{ $item['description'] }}</td>
                                            <td data-label="Quantità">{{ $item['quantity'] !== '' ? number_format((float) $item['quantity'], 2, ',', '.') : 'Non indicata' }}</td>
                                            <td data-label="Prezzo unitario">{{ $item['unit_price'] !== '' ? $money($item['unit_price']) : 'Non indicato' }}</td>
                                            <td data-label="IVA">{{ $item['vat_rate'] !== '' ? number_format((float) $item['vat_rate'], 2, ',', '.').'%' : 'Non indicata' }}</td>
                                            <td data-label="Imponibile">{{ $item['total'] !== '' ? $money($item['total']) : 'Non indicato' }}</td></tr>
                                    @empty<tr><td colspan="5" class="invoice-detail-empty">Le voci sono consultabili nel documento originale allegato.</td></tr>@endforelse
                                @else
                                    @forelse($invoice->items as $item)
                                        <tr><td class="invoice-item-description">{{ $item->description }}@if($item->vat_nature)<p class="invoice-detail-muted">{{ $item->vat_nature }} {{ $item->vat_reference }}</p>@endif</td>
                                            <td data-label="Quantità">{{ number_format((float) $item->quantity, 2, ',', '.') }} {{ $item->unit_of_measure }}</td>
                                            <td data-label="Prezzo unitario">{{ $money($item->unit_price) }}</td>
                                            <td data-label="IVA">{{ $item->vat_rate === null ? 'Da completare' : number_format((float) $item->vat_rate, 2, ',', '.').'%' }}</td>
                                            <td data-label="Imponibile">{{ $money($item->total) }}</td>
                                            <td data-label="Imposta">{{ $money($item->tax_amount) }}</td>
                                            <td data-label="Totale">{{ $item->total_with_tax === null ? 'Da ricalcolare' : $money($item->total_with_tax) }}</td></tr>
                                    @empty<tr><td colspan="7" class="invoice-detail-empty">Nessuna voce presente.</td></tr>@endforelse
                                @endif
                            </tbody>
                        </table>
                    </div>
                    <p class="invoice-items-footer">Totale imponibile <strong>{{ $money($invoice->subtotal) }}</strong></p>
                </x-panel>

                @if($invoice->notes)
                    <x-panel padded><x-slot:title><h2>Note interne</h2></x-slot:title><p class="invoice-detail-copy">{{ $invoice->notes }}</p></x-panel>
                @endif
                @if($invoice->creditAllocations->isNotEmpty())
                    <x-panel padded><x-slot:title><h2>Note di credito collegate</h2></x-slot:title>
                        <dl class="invoice-credit-links">@foreach($invoice->creditAllocations as $allocation)
                            <div><dt><a href="{{ route('credit-notes.show', $allocation->creditNote) }}">{{ $allocation->creditNote->number }}</a></dt><dd>{{ $money($allocation->amount) }}</dd></div>
                        @endforeach</dl>
                    </x-panel>
                @endif
            </div>

            <aside class="invoice-detail-side" aria-label="Importi e incassi">
                <x-panel padded class="invoice-detail-summary">
                    <x-slot:title><h2>Riepilogo importi</h2></x-slot:title>
                    <dl class="invoice-totals">
                        <div><dt>Imponibile</dt><dd>{{ $money($invoice->subtotal) }}</dd></div>
                        <div><dt>IVA</dt><dd>{{ $money($invoice->tax_amount) }}</dd></div>
                        <div class="invoice-total"><dt>Totale fattura</dt><dd>{{ $money($invoice->total) }}</dd></div>
                        @if($invoice->payable_total !== null && (float) $invoice->payable_total !== (float) $invoice->total)
                            @if((float) ($invoice->import_metadata['split_payment_tax'] ?? 0) > 0)<div><dt>IVA in split payment</dt><dd>{{ $money($invoice->import_metadata['split_payment_tax']) }}</dd></div>@endif
                            <div><dt>Netto dovuto dal cliente</dt><dd>{{ $money($invoice->payable_total) }}</dd></div>
                        @endif
                        @if((float) $invoice->credited_total > 0)<div><dt>Storni collegati sul dovuto</dt><dd>{{ $money($invoice->credited_total) }}</dd></div>@endif
                        <div><dt>Incassato</dt><dd>{{ $money($invoice->paid_total) }}</dd></div>
                        <div class="invoice-outstanding"><dt>Da incassare</dt><dd>{{ $money($invoice->residual) }}</dd></div>
                        @if($invoice->customer_credit > 0)<div><dt>Credito cliente da regolare</dt><dd>{{ $money($invoice->customer_credit) }}</dd></div>@endif
                    </dl>
                </x-panel>

                <x-panel padded>
                    <x-slot:title><h2>Pagamenti registrati</h2></x-slot:title>
                    @forelse($invoice->payments as $payment)
                        <a class="invoice-payment" href="{{ route('payments.show', $payment) }}"><span>{{ $payment->payment_date?->format('d/m/Y') }}<small>{{ $payment->method_label }}</small></span><strong>{{ $money($payment->amount) }}</strong></a>
                    @empty<p class="invoice-detail-muted">Nessun pagamento registrato.</p>@endforelse
                </x-panel>
            </aside>
        </div>

        @unless($imported)
            @include('invoices.partials.fiscal')
        @endunless
        <div class="invoice-detail-attachments"><livewire:shared.attachment-manager :model="$invoice" /></div>
        <div class="invoice-detail-history"><x-audit-timeline :logs="$invoice->auditLogs" /></div>
    </div>
</x-app-layout>
