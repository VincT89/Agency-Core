<x-app-layout title="Fattura {{ $invoice->number }}">
    <div class="u-mb-lg u-flex-end">
        <a href="{{ route('invoices.index') }}" wire:navigate class="btn btn-g u-flex-center u-gap-xs">
            <i data-lucide="arrow-left" class="u-icon-sm"></i>
            Torna alle fatture
        </a>
    </div>

    <x-page-header eyebrow="Riferimento interno">
        <x-slot:title><strong>{{ $invoice->number }}</strong></x-slot:title>
        <x-slot:actions>
            <x-badge :status="$invoice->fiscal_badge_status" :label="$invoice->fiscal_status_label" />
            <x-badge :status="$invoice->status" :label="$invoice->status_label" />

            @can('update', $invoice)
                <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-g">Modifica</a>
            @endcan

            @can('create', App\Models\Payment::class)
                @if(! in_array($invoice->status, ['paid', 'cancelled'], true))
                    <a href="{{ route('payments.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-p">
                        Registra pagamento
                    </a>
                @endif
            @endcan

            @can('delete', $invoice)
                <form action="{{ route('invoices.destroy', $invoice) }}" method="POST"
                      onsubmit="return confirm('Eliminare questa bozza? L’operazione non può essere annullata.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-g btn-danger-outline">Elimina bozza</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="inv-panel-gap">
        <x-panel title="Fattura elettronica" dot="var(--accent)" padded>
            <div class="inv-fiscal-status-head">
                <div>
                    <div class="form-lbl inv-lbl">Stato fiscale</div>
                    <div class="inv-client-name">{{ $invoice->fiscal_status_label }}</div>
                </div>
                @if($invoice->fiscal_number)
                    <div>
                        <div class="form-lbl inv-lbl">Numero fiscale riservato</div>
                        <div class="mono-col u-text-strong">{{ $invoice->fiscal_number }}</div>
                    </div>
                @endif
                <div>
                    <div class="form-lbl inv-lbl">Collegamento Aruba</div>
                    <div class="u-text-strong">{{ $arubaStatus['ready_for_validation'] ? 'Disponibile' : 'Non disponibile' }}</div>
                </div>
            </div>

            <div class="fiscal-action-row fiscal-action-row-top">
                <a href="{{ route('billing-profile.edit') }}" class="btn btn-g btn-sm">
                    Dati fiscali
                </a>
                @can('downloadFiscalXml', $invoice)
                    <a href="{{ route('invoices.fiscal.xml', $invoice) }}" class="btn btn-g btn-sm">
                        Scarica XML
                    </a>
                @endcan
            </div>

            @if($invoice->fiscal_status === \App\Enums\Finance\InvoiceFiscalStatus::NotPrepared)
                @if($fiscalReadiness->isReady())
                    <div class="inv-fiscal-message is-ready">
                        <div class="u-text-strong">Fattura pronta</div>
                    </div>

                    @can('prepareFiscal', $invoice)
                        <form action="{{ route('invoices.fiscal.prepare', $invoice) }}" method="POST"
                              class="u-mt-md" x-data="{ submitting: false }"
                              @submit="if (submitting || !confirm('Preparare e bloccare la fattura? Non verrà inviata ad Aruba o allo SdI.')) { $event.preventDefault(); return; } submitting = true">
                            @csrf
                            <button type="submit" class="btn btn-p" :disabled="submitting">
                                <span x-show="!submitting">Prepara fattura elettronica</span>
                                <span x-show="submitting" x-cloak class="btn-loading-copy">
                                    <span class="inline-loader" aria-hidden="true"></span>
                                    Preparazione in corso
                                </span>
                            </button>
                        </form>
                    @endcan
                @else
                    <div class="inv-fiscal-message">
                        <div class="u-text-strong">Dati mancanti</div>
                        <ul class="inv-fiscal-issues">
                            @foreach($fiscalReadiness->issues as $issue)
                                <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @elseif($invoice->fiscal_status === \App\Enums\Finance\InvoiceFiscalStatus::Ready)
                <div class="inv-fiscal-message is-ready">
                    <div class="u-text-strong">
                        {{ $matchingValidation ? 'Controlli Aruba superati' : 'Pronta nel gestionale, non inviata' }}
                    </div>
                    <div class="u-text-meta">
                        I dati fiscali sono stati copiati in una versione bloccata
                        {{ $invoice->fiscal_locked_at ? 'il '.$invoice->fiscal_locked_at->format('d/m/Y \a\l\l\e H:i') : '' }}.
                        @if($matchingValidation)
                            XML verificato da Aruba.
                        @endif
                    </div>
                </div>

                <div class="fiscal-action-row">
                    @can('validateFiscal', $invoice)
                        @if($arubaStatus['ready_for_validation'])
                            <form action="{{ route('invoices.fiscal.validate', $invoice) }}" method="POST"
                                  x-data="{ submitting: false }"
                                  @submit="if (submitting) { $event.preventDefault(); return; } submitting = true">
                                @csrf
                                <button type="submit" class="btn btn-p" :disabled="submitting">
                                    <span x-show="!submitting">{{ $matchingValidation ? 'Verifica di nuovo' : 'Verifica con Aruba' }}</span>
                                    <span x-show="submitting" x-cloak class="btn-loading-copy">
                                        <span class="inline-loader" aria-hidden="true"></span>
                                        Controllo XML in corso
                                    </span>
                                </button>
                            </form>
                        @endif
                    @endcan

                    @can('sendFiscal', $invoice)
                        @if($matchingValidation && $arubaStatus['ready_for_send'])
                            <form action="{{ route('invoices.fiscal.send', $invoice) }}" method="POST"
                                  x-data="{ submitting: false }"
                                  @submit="if (submitting || !confirm('{{ $arubaStatus['environment'] === 'production' ? 'Confermi l’invio effettivo ad Aruba e allo SdI? Dopo la trasmissione la fattura non potrà più essere modificata.' : 'Confermi l’invio nell’ambiente di collaudo Aruba? La fattura verrà considerata trasmessa nel gestionale.' }}')) { $event.preventDefault(); return; } submitting = true">
                                @csrf
                                <button type="submit" class="btn btn-p" :disabled="submitting">
                                    <span x-show="!submitting">
                                        {{ $arubaStatus['environment'] === 'production' ? 'Invia ad Aruba e allo SdI' : 'Invia nel collaudo Aruba' }}
                                    </span>
                                    <span x-show="submitting" x-cloak class="btn-loading-copy">
                                        <span class="inline-loader" aria-hidden="true"></span>
                                        Invio protetto in corso
                                    </span>
                                </button>
                            </form>
                        @endif
                    @endcan

                    @can('reopenFiscal', $invoice)
                        <form action="{{ route('invoices.fiscal.reopen', $invoice) }}" method="POST"
                              x-data="{ submitting: false }"
                              @submit="if (submitting || !confirm('Riaprire la fattura? Il numero fiscale già riservato verrà mantenuto.')) { $event.preventDefault(); return; } submitting = true">
                            @csrf
                            <button type="submit" class="btn btn-g" :disabled="submitting">Riapri per correggere</button>
                        </form>
                    @endcan
                </div>

                @if($matchingValidation && ! $arubaStatus['ready_for_send'])
                    <div class="u-text-meta u-mt-md">
                        Invio non disponibile.
                    </div>
                @endif
            @else
                <div class="inv-fiscal-message">
                    <div class="u-text-strong">{{ $invoice->fiscal_status_label }}</div>
                    <div class="u-text-meta">
                        La trasmissione fiscale è iniziata e i dati della fattura non sono più modificabili.
                        @if($latestLiveTransmission?->error_message)
                            {{ $latestLiveTransmission->error_message }}
                        @endif
                    </div>
                </div>

                @can('syncFiscal', $invoice)
                    @if($arubaStatus['ready_for_validation'])
                        <div class="fiscal-action-row">
                            <form action="{{ route('invoices.fiscal.sync', $invoice) }}" method="POST"
                                  x-data="{ submitting: false }"
                                  @submit="if (submitting) { $event.preventDefault(); return; } submitting = true">
                                @csrf
                                <button type="submit" class="btn btn-g" :disabled="submitting">
                                    <span x-show="!submitting">Aggiorna stato da Aruba</span>
                                    <span x-show="submitting" x-cloak class="btn-loading-copy">
                                        <span class="inline-loader" aria-hidden="true"></span>
                                        Aggiornamento in corso
                                    </span>
                                </button>
                            </form>
                        </div>
                    @endif
                @endcan

                @can('reopenFiscal', $invoice)
                    <div class="fiscal-action-row">
                        <form action="{{ route('invoices.fiscal.reopen', $invoice) }}" method="POST"
                              x-data="{ submitting: false }"
                              @submit="if (submitting || !confirm('Riaprire la fattura per correggerla? Il numero fiscale e la cronologia degli invii verranno mantenuti.')) { $event.preventDefault(); return; } submitting = true">
                            @csrf
                            <button type="submit" class="btn btn-g" :disabled="submitting">
                                Riapri dopo l’esito negativo
                            </button>
                        </form>
                    </div>
                @endcan
            @endif

            @if($errors->has('fiscal'))
                <div class="inv-fiscal-message has-errors u-mt-md">
                    <div class="u-text-strong">L’operazione non è stata completata</div>
                    <ul class="inv-fiscal-issues">
                        @foreach($errors->get('fiscal') as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($invoice->electronicInvoiceTransmissions->isNotEmpty())
                <div class="fiscal-history">
                    <div class="sec-lbl">Cronologia Aruba</div>
                    <div class="fiscal-history-list">
                        @foreach($invoice->electronicInvoiceTransmissions as $transmission)
                            <article class="fiscal-history-item">
                                <div class="fiscal-history-main">
                                    <div class="fiscal-history-title">
                                        <x-badge :status="$transmission->status->badgeStatus()" :label="$transmission->status_label" />
                                        <span class="u-text-strong">
                                            {{ $transmission->mode === 'dry_run' ? 'Verifica tecnica' : 'Invio fattura' }}
                                        </span>
                                    </div>
                                    <div class="u-text-meta">
                                        {{ $transmission->environment === 'production' ? 'Produzione' : 'Collaudo' }}
                                        , tentativo {{ $transmission->attempt_number }}
                                        , {{ $transmission->submitted_at?->format('d/m/Y H:i') ?? 'data non disponibile' }}
                                        @if($transmission->submitter)
                                            , {{ $transmission->submitter->name }}
                                        @endif
                                    </div>
                                    @if($transmission->error_message)
                                        <div class="fiscal-history-error">{{ $transmission->error_message }}</div>
                                    @endif
                                </div>
                                <div class="fiscal-history-side">
                                    @if($transmission->sdi_id)
                                        <div class="form-lbl inv-lbl">Identificativo SdI</div>
                                        <div class="mono-col">{{ $transmission->sdi_id }}</div>
                                    @elseif($transmission->upload_filename)
                                        <div class="form-lbl inv-lbl">Riferimento Aruba</div>
                                        <div class="mono-col fiscal-file-name">{{ $transmission->upload_filename }}</div>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-panel>
    </div>

    <div class="g-invoice inv-panel-gap">
        <div class="g-invoice-left">
            <x-panel title="Dati della fattura" dot="var(--accent)" padded>
                <div class="g-2col">
                    <div>
                        <div class="form-lbl inv-lbl">Cliente</div>
                        <div class="inv-client-name">
                            @if($invoice->client)
                                <a href="{{ route('clients.show', $invoice->client) }}">
                                    {{ $invoice->client->company_name ?: $invoice->client->name }}
                                </a>
                            @else
                                Non disponibile
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="form-lbl inv-lbl">
                            {{ $invoice->marketingCampaign ? 'Campagna' : 'Progetto' }}
                        </div>
                        <div class="inv-project-name">
                            @if($invoice->project)
                                <a href="{{ route('projects.show', $invoice->project) }}">
                                    {{ $invoice->project->name }}
                                </a>
                            @elseif($invoice->marketingCampaign)
                                <a href="{{ route('marketing-campaigns.show', $invoice->marketingCampaign) }}">
                                    {{ $invoice->marketingCampaign->name }}
                                </a>
                            @else
                                Non disponibile
                            @endif
                        </div>
                    </div>
                </div>

                <div class="g-2col inv-section-sep">
                    <div>
                        <div class="form-lbl inv-lbl">Data fattura</div>
                        <div class="inv-date">{{ $invoice->issue_date?->format('d/m/Y') ?? 'Non indicata' }}</div>
                    </div>
                    <div>
                        <div class="form-lbl inv-lbl">Scadenza</div>
                        <div class="{{ $invoice->due_date?->isPast() && $invoice->residual > 0 ? 'inv-date-overdue' : 'inv-date' }}">
                            {{ $invoice->due_date?->format('d/m/Y') ?? 'Non indicata' }}
                        </div>
                    </div>
                </div>

                @if($invoice->notes)
                    <div class="inv-section-sep">
                        <div class="form-lbl inv-lbl">Note interne</div>
                        <div>{{ $invoice->notes }}</div>
                    </div>
                @endif
            </x-panel>

            <div class="inv-panel-gap">
                <x-panel title="Pagamenti associati">
                    <table class="t-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Metodo</th>
                                <th>Importo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoice->payments as $payment)
                                <tr x-data
                                    @click="window.Livewire.navigate('{{ route('payments.show', $payment) }}')"
                                    class="u-cursor-pointer hover-bg">
                                    <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                                    <td>
                                        <x-badge :status="$payment->method" :label="$payment->method_label" />
                                    </td>
                                    <td class="mono-col">
                                        {{ $invoice->currency }} {{ number_format($payment->amount, 2, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="inv-empty-cell">Nessun pagamento registrato</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-panel>
            </div>
        </div>

        <div class="g-invoice-right">
            <x-panel title="Riepilogo economico" dot="var(--green)" padded>
                <div class="inv-total-hero">
                    <div class="form-lbl inv-lbl">Totale fattura</div>
                    <div class="inv-total-hero-value">
                        {{ $invoice->currency }} {{ number_format($invoice->total, 2, ',', '.') }}
                    </div>
                    <div class="inv-total-hero-sub">
                        Imponibile {{ number_format($invoice->subtotal, 2, ',', '.') }}
                        · IVA {{ number_format($invoice->tax_amount, 2, ',', '.') }}
                    </div>
                </div>

                <div class="inv-summary-row">
                    <span class="inv-summary-label">Incassato</span>
                    <strong class="inv-summary-value green">
                        {{ $invoice->currency }} {{ number_format($invoice->paid_total, 2, ',', '.') }}
                    </strong>
                </div>
                <div class="inv-summary-row inv-summary-row-last">
                    <span class="inv-summary-label">Da incassare</span>
                    <strong class="inv-summary-value {{ $invoice->residual > 0 ? 'red' : 'muted' }}">
                        {{ $invoice->currency }} {{ number_format($invoice->residual, 2, ',', '.') }}
                    </strong>
                </div>

                @php
                    $percent = $invoice->total > 0
                        ? min(100, ($invoice->paid_total / $invoice->total) * 100)
                        : 0;
                @endphp
                <div class="inv-progress-wrap">
                    <div class="inv-progress-hd">
                        <span>Incasso completato</span>
                        <span>{{ round($percent) }}%</span>
                    </div>
                    <x-workload-bar :percent="$percent" />
                </div>
            </x-panel>
        </div>
    </div>

    <div class="inv-panel-gap">
        <x-panel title="Voci della fattura" dot="var(--accent)">
            @can('update', $invoice)
                <x-slot:headerActions>
                    <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-g btn-sm">
                        Modifica voci e IVA
                    </a>
                </x-slot:headerActions>
            @endcan

            <table class="t-table">
                <thead>
                    <tr>
                        <th>Descrizione</th>
                        <th>Quantità</th>
                        <th>Prezzo unitario</th>
                        <th>Imponibile</th>
                        <th>IVA</th>
                        <th>Imposta</th>
                        <th>Totale</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoice->items as $item)
                        <tr>
                            <td>
                                <div>{{ $item->description }}</div>
                                @if($item->vat_nature)
                                    <div class="u-text-meta">
                                        {{ $item->vat_nature }}
                                        @if($item->vat_reference)
                                            · {{ $item->vat_reference }}
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td>
                                {{ fmod((float) $item->quantity, 1) === 0.0 ? (int) $item->quantity : $item->quantity }}
                                {{ $item->unit_of_measure }}
                            </td>
                            <td class="mono-col">{{ number_format($item->unit_price, 2, ',', '.') }}</td>
                            <td class="mono-col">{{ number_format($item->total, 2, ',', '.') }}</td>
                            <td>
                                @if($item->vat_rate === null)
                                    <x-badge status="overdue" label="Da completare" />
                                @else
                                    {{ number_format($item->vat_rate, 2, ',', '.') }}%
                                @endif
                            </td>
                            <td class="mono-col">{{ number_format($item->tax_amount, 2, ',', '.') }}</td>
                            <td class="mono-col">
                                {{ $item->total_with_tax === null
                                    ? 'Da ricalcolare'
                                    : number_format($item->total_with_tax, 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="inv-empty-cell">Nessuna voce presente</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>
    </div>

    <x-audit-timeline :logs="$invoice->auditLogs" />

    <livewire:shared.attachment-manager :model="$invoice" />
</x-app-layout>
