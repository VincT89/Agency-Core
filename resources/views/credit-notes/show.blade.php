<x-app-layout title="Nota di credito {{ $creditNote->number }}">
    <x-page-header><x-slot:title>Nota di credito {{ $creditNote->number }}</x-slot:title><x-slot:actions><a href="{{ route('credit-notes.document', $creditNote) }}" class="btn btn-p">Visualizza nota di credito</a><a href="{{ route('credit-notes.index') }}" class="btn btn-g">Note di credito</a><a href="{{ route('invoices.index') }}" class="btn btn-g">Fatture</a></x-slot:actions></x-page-header>
    @if($errors->any())<div class="invalid-feedback u-mb-md" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <x-panel title="Documento originale" padded>
        <dl class="ticket-request-details">
            <div><dt>Cliente</dt><dd>{{ $creditNote->client->company_name ?: $creditNote->client->name }}</dd></div>
            @if($customerIdentity['vat'])<div><dt>Partita IVA cliente nel documento</dt><dd>{{ $customerIdentity['country'] }} {{ $customerIdentity['vat'] }}</dd></div>
            @elseif($customerIdentity['tax_code'])<div><dt>Codice fiscale cliente nel documento</dt><dd>{{ $customerIdentity['tax_code'] }}</dd></div>@endif
            <div><dt>Tipo documento</dt><dd>TD04 - Nota di credito</dd></div>
            <div><dt>Data</dt><dd>{{ $creditNote->issue_date->format('d/m/Y') }}</dd></div>
            <div><dt>Totale</dt><dd>{{ number_format($creditNote->total, 2, ',', '.') }} €</dd></div>
            <div><dt>Netto da collegare</dt><dd>{{ number_format($creditNote->unallocated_total, 2, ',', '.') }} €</dd></div>
        </dl>
        @foreach($creditNote->attachments as $attachment)<a href="{{ route('attachments.download', $attachment) }}" class="btn btn-g u-mt-md">Scarica XML originale</a>@endforeach
        <details class="u-mt-md"><summary>Righe della nota di credito</summary>@foreach($creditNote->import_metadata['items'] ?? [] as $item)<div class="commercial-history-row"><p>{{ $item['description'] }}</p><p>Imponibile {{ $item['total'] }} €; IVA {{ $item['vat_rate'] }}%</p></div>@endforeach</details>
    </x-panel>
    @if($references->isNotEmpty())
        <x-panel title="Riferimenti da verificare" padded class="u-mt-lg">
            <p>Le proposte confrontano paese e identificativo fiscale di cliente ed emittente, numero e anno della fattura. Usa un suggerimento per compilare il modulo, poi conferma il collegamento.</p>
            @foreach($references as $reference)
                <div class="commercial-history-row"><strong>{{ $reference['number'] }}</strong><p>{{ $reference['source'] === 'description' ? 'Riferimento scritto nella descrizione' : 'Riferimento XML' }}</p>
                @if($reference['suggested_amount'] !== null)<p>Quota indicata dalle righe XML: <strong>{{ number_format((float) $reference['suggested_amount'], 2, ',', '.') }} €</strong></p>@endif
                @if($reference['candidates']->count() > 1)<p class="u-text-meta u-mt-md">Il riferimento corrisponde a più fatture: verifica quale collegare.</p>@endif
                @forelse($reference['candidates'] as $candidate)<a href="{{ route('invoices.show', $candidate) }}" class="btn btn-g u-mt-md">Verifica {{ $candidate->display_number }}</a>
                @empty<p class="u-text-meta">Nessuna fattura compatibile con i dati fiscali e il riferimento. Se manca, importala e poi collegala.</p>@endforelse
                @if($reference['already_linked'])<p class="u-text-meta u-mt-md">La fattura è già collegata a questa nota.</p>
                @elseif($reference['can_prefill'])<button type="button" class="btn btn-p u-mt-md" x-data @click="$dispatch('credit-suggestion', { invoiceId: {{ $reference['candidates']->first()->id }}, amount: '{{ $reference['suggested_amount'] }}' })">Usa suggerimento {{ $reference['candidates']->first()->display_number }}</button>@endif
                </div>
            @endforeach
        </x-panel>
    @endif
    <x-panel title="Fatture collegate" class="u-mt-lg">
        <div class="t-table-wrap" role="region" aria-label="Fatture collegate alla nota di credito" tabindex="0"><table class="t-table"><thead><tr><th>Fattura</th><th>Importo collegato</th><th>Azioni</th></tr></thead><tbody>
        @forelse($creditNote->allocations as $allocation)
            <tr><td><a href="{{ route('invoices.show', $allocation->invoice) }}">{{ $allocation->invoice->display_number }}</a></td><td>{{ number_format($allocation->amount, 2, ',', '.') }} €</td><td>
                <form id="credit-remove-{{ $allocation->id }}" method="POST" action="{{ route('credit-notes.allocations.destroy', [$creditNote, $allocation]) }}">@csrf @method('DELETE')
                    <x-confirm-modal title="Rimuovi collegamento" message="La fattura e la nota di credito resteranno conservate. Il residuo della fattura verrà ricalcolato." confirmText="Rimuovi collegamento" :form-id="'credit-remove-'.$allocation->id"><button type="button" class="btn btn-g">Rimuovi collegamento</button></x-confirm-modal>
                </form>
            </td></tr>
        @empty<tr><td colspan="3">Nessuna fattura collegata.</td></tr>@endforelse
        </tbody></table></div>
    </x-panel>
    @if($creditNote->unallocated_total > 0)
        <x-panel title="Collega una fattura" padded class="u-mt-lg">
            <form method="POST" action="{{ route('credit-notes.allocate', $creditNote) }}"
                  x-data="{ invoiceId: @js((string) old('invoice_id', '')), amount: @js((string) old('amount', '')) }"
                  @credit-suggestion.window="invoiceId = String($event.detail.invoiceId); amount = $event.detail.amount; $nextTick(() => $refs.creditInvoice.focus())">@csrf
                <div class="form-row">
                    <x-form-group label="Fattura dello stesso cliente" name="invoice_id" for="credit-invoice" required><select id="credit-invoice" name="invoice_id" class="form-sel" x-ref="creditInvoice" x-model="invoiceId" required><option value="">Seleziona fattura</option>@foreach($invoices as $invoice)<option value="{{ $invoice->id }}" @selected((int) old('invoice_id') === $invoice->id)>{{ $invoice->display_number }} - {{ $invoice->issue_date->format('d/m/Y') }}</option>@endforeach</select></x-form-group>
                    <x-form-group label="Importo da stornare dal dovuto (€)" name="amount" for="credit-amount" required><input id="credit-amount" name="amount" type="number" min="0.01" step="0.01" max="{{ number_format($creditNote->unallocated_total, 2, '.', '') }}" value="{{ old('amount') }}" x-model="amount" class="form-in" required></x-form-group>
                </div>
                <p class="u-text-meta u-mb-md">Gli incassi già registrati restano invariati. Se la fattura è stata pagata, il credito del cliente sarà mostrato separatamente per la successiva regolazione.</p>
                <button class="btn btn-p">Collega fattura</button>
            </form>
        </x-panel>
    @endif
</x-app-layout>
