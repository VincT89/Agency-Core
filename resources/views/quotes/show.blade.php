<x-app-layout :title="$quote->title">
    @php($customer = $quote->client_snapshot ?? $quote->client->toArray())
    <div class="quote-detail">
        <div class="page-back-row">
            <a href="{{ route('quotes.index') }}" class="btn btn-g">Offerte commerciali</a>
        </div>
        <x-page-header>
            <x-slot:title>{{ $quote->title }}</x-slot:title>
            <x-slot:meta>
                <div class="quote-detail-reference">
                    <span>Offerta #{{ $quote->id }}, revisione {{ $quote->revision }}</span>
                    <x-badge :type="$quote->status">{{ $quote->status_label }}</x-badge>
                </div>
            </x-slot:meta>
            <x-slot:actions>
                <a href="{{ route('quotes.document', $quote) }}" class="btn btn-g">Anteprima e stampa PDF</a>
                @can('update', $quote)
                    <a href="{{ route('quotes.edit', $quote) }}" class="btn btn-p">Modifica bozza</a>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-panel class="quote-detail-customer" padded>
            <x-slot:title><h2>Dati cliente dell’offerta</h2></x-slot:title>
            <x-slot:headerActions>
                <a href="{{ route('clients.show', $quote->client) }}" class="btn btn-g">Storico cliente</a>
            </x-slot:headerActions>
            <dl class="quote-detail-facts quote-client-facts">
                @foreach(['name' => 'Cliente', 'company_name' => 'Ragione sociale', 'reference_person' => 'Referente', 'email' => 'Email', 'phone' => 'Telefono', 'vat_number' => 'Partita IVA', 'tax_code' => 'Codice fiscale', 'address' => 'Indirizzo', 'city' => 'Comune', 'postal_code' => 'CAP', 'province' => 'Provincia'] as $field => $label)
                    @if(filled($customer[$field] ?? null))
                        <div><dt>{{ $label }}</dt><dd>{{ $customer[$field] }}</dd></div>
                    @endif
                @endforeach
            </dl>
        </x-panel>

        <div class="quote-detail-grid">
            <div class="quote-detail-content">
                @if(filled($quote->introduction))
                    <x-panel padded>
                        <section aria-labelledby="quote-introduction-title">
                            <h2 id="quote-introduction-title" class="quote-detail-heading">Descrizione progetto e preventivo</h2>
                            <p class="quote-detail-copy">{{ $quote->introduction }}</p>
                        </section>
                    </x-panel>
                @endif

                <x-panel class="quote-services">
                    <x-slot:title><h2>Servizi e importi</h2></x-slot:title>
                    @forelse($quote->items as $item)
                        <article class="quote-service">
                            <div class="quote-service-heading">
                                <h3>{{ $item->name }}</h3>
                                <dl class="quote-service-amount">
                                    <dt>Importo</dt>
                                    <dd>{{ number_format((float) $item->total, 2, ',', '.') }} €</dd>
                                </dl>
                            </div>
                            <dl class="quote-service-pricing">
                                <div><dt>Quantità</dt><dd>{{ number_format((float) $item->quantity, 2, ',', '.') }}</dd></div>
                                <div><dt>Prezzo unitario</dt><dd>{{ number_format((float) $item->unit_price, 2, ',', '.') }} €</dd></div>
                            </dl>
                            @if(filled($item->summary))<p class="quote-detail-copy quote-service-summary">{{ $item->summary }}</p>@endif
                            @if(filled($item->description))<p class="quote-detail-copy">{{ $item->description }}</p>@endif
                            @if(filled($item->delivery_terms) || filled($item->delivery_summary))
                                <dl class="quote-service-delivery">
                                    <dt>Tempistiche</dt>
                                    <dd class="quote-detail-copy">{{ filled($item->delivery_terms) ? $item->delivery_terms : $item->delivery_summary }}</dd>
                                </dl>
                            @endif
                        </article>
                    @empty
                        <p class="quote-services-empty">Nessun servizio inserito.</p>
                    @endforelse
                    <div class="quote-detail-total">
                        <dl><dt>Totale servizi</dt><dd>{{ number_format((float) $quote->total, 2, ',', '.') }} €</dd></dl>
                        @if(filled($quote->price_note))<p class="quote-detail-copy">{{ $quote->price_note }}</p>@endif
                    </div>
                </x-panel>

                @if(filled($quote->payment_terms) || filled($quote->notes))
                    <x-panel padded>
                        @if(filled($quote->payment_terms))
                            <section class="quote-detail-terms" aria-labelledby="quote-payment-title">
                                <h2 id="quote-payment-title" class="quote-detail-heading">Forma di pagamento</h2>
                                <p class="quote-detail-copy">{{ $quote->payment_terms }}</p>
                            </section>
                        @endif
                        @if(filled($quote->notes))
                            <section class="quote-detail-terms" aria-labelledby="quote-notes-title">
                                <h2 id="quote-notes-title" class="quote-detail-heading">Condizioni e note</h2>
                                <p class="quote-detail-copy">{{ $quote->notes }}</p>
                            </section>
                        @endif
                    </x-panel>
                @endif
            </div>

            <aside class="quote-detail-sidebar" aria-label="Informazioni e gestione dell’offerta">
                <x-panel padded>
                    <h2 class="quote-detail-heading">Gestione offerta</h2>
                    <dl class="quote-detail-facts">
                        <div>
                            <dt>Presentata il</dt>
                            <dd>{{ $quote->presented_at?->format('d/m/Y H:i') ?? 'Non ancora presentata' }}</dd>
                        </div>
                        @if($quote->accepted_at)
                            <div><dt>Accettata il</dt><dd>{{ $quote->accepted_at->format('d/m/Y H:i') }}</dd></div>
                        @endif
                        @if($quote->rejected_at)
                            <div><dt>Rifiutata il</dt><dd>{{ $quote->rejected_at->format('d/m/Y H:i') }}</dd></div>
                        @endif
                    </dl>
                    @canany(['present', 'respond', 'revise', 'createProject'], $quote)
                        <div class="quote-workflow-actions">
                            @can('present', $quote)
                                <form method="POST" action="{{ route('quotes.present', $quote) }}">
                                    @csrf
                                    <button class="btn btn-p" type="submit">Registra come presentata</button>
                                </form>
                            @endcan
                            @can('respond', $quote)
                                <form method="POST" action="{{ route('quotes.accept', $quote) }}">
                                    @csrf
                                    <button class="btn btn-p" type="submit">Registra accettazione</button>
                                </form>
                                <form method="POST" action="{{ route('quotes.reject', $quote) }}">
                                    @csrf
                                    <button class="btn btn-g" type="submit">Registra rifiuto</button>
                                </form>
                            @endcan
                            @can('revise', $quote)
                                <form method="POST" action="{{ route('quotes.revise', $quote) }}">
                                    @csrf
                                    <button class="btn btn-g" type="submit">Prepara revisione</button>
                                </form>
                            @endcan
                            @can('createProject', $quote)
                                <a href="{{ route('quotes.project.create', $quote) }}" class="btn btn-p">{{ $quote->project_id ? 'Apri progetto' : 'Crea progetto' }}</a>
                            @endcan
                        </div>
                    @endcanany
                    <nav class="quote-detail-links" aria-label="Collegamenti dell’offerta">
                        @can('create', \App\Models\Quote::class)
                            <a href="{{ route('quotes.create', ['from_quote_id' => $quote->id]) }}">Usa come modello</a>
                        @endcan
                        @if($quote->ticket)
                            @can('view', $quote->ticket)<a href="{{ route('tickets.show', $quote->ticket) }}">Richiesta originale</a>@endcan
                        @endif
                        @if($quote->previousQuote)
                            @can('view', $quote->previousQuote)<a href="{{ route('quotes.show', $quote->previousQuote) }}">Consulta la revisione precedente</a>@endcan
                        @endif
                        @if($quote->nextQuote)
                            @can('view', $quote->nextQuote)<a href="{{ route('quotes.show', $quote->nextQuote) }}">Consulta la revisione successiva</a>@endcan
                        @endif
                    </nav>
                    @can('delete', $quote)
                        <div class="quote-detail-delete">
                            <x-delete-modal :action="route('quotes.destroy', $quote)" title="Elimina offerta"
                                message="Eliminare questa offerta? Verrà rimossa dalle liste e dallo storico visibile. Gli eventuali progetti collegati resteranno disponibili.">
                                <button class="btn btn-g btn-danger-outline" type="button">Elimina offerta</button>
                            </x-delete-modal>
                        </div>
                    @endcan
                </x-panel>
            </aside>
        </div>

        <div class="quote-detail-attachments">
            <livewire:shared.attachment-manager :model="$quote" />
        </div>
    </div>
</x-app-layout>
