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
            @elseif($invoice->fiscal_status === \App\Enums\Finance\InvoiceFiscalStatus::Imported)
                <div class="inv-fiscal-message">
                    <div class="u-text-strong">Importata manualmente</div>
                    <p>Numero originale: {{ $invoice->import_metadata['number'] ?? '' }}. Provenienza: {{ $invoice->import_source === 'aruba' ? 'Aruba' : 'file' }}.</p>
                    <p class="u-text-meta">L’importazione non ha effettuato un nuovo invio allo SdI. Il documento originale è disponibile negli allegati.</p>
                </div>
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


