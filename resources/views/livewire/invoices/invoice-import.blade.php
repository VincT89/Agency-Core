<div class="invoice-import" x-data="{ uploading: false }"
     x-on:livewire-upload-start="uploading = true"
     x-on:livewire-upload-finish="uploading = false"
     x-on:livewire-upload-error="uploading = false"
     x-on:livewire-upload-cancel="uploading = false">
    <div class="page-back-row"><a href="{{ route('invoices.index') }}" class="btn btn-g">Torna alle fatture</a></div>
    <x-page-header><x-slot:title>Importa fattura</x-slot:title></x-page-header>
    <p class="u-mb-md">Acquisisci una fattura esistente da file o da Aruba. Verifica l’anteprima e conferma il salvataggio. L’importazione non invia fatture allo SdI e non registra incassi o pagamenti.</p>
    @if($errors->any())<div class="invalid-feedback u-mb-md" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <x-panel padded>
        <div class="form-row">
            <x-form-group label="Fattura" for="import-direction">
                <select id="import-direction" wire:model.live="direction" class="form-sel"><option value="out">Emessa a un cliente</option><option value="in">Ricevuta da un fornitore</option></select>
            </x-form-group>
            <x-form-group label="Provenienza" for="import-source">
                <select id="import-source" wire:model.live="source" class="form-sel"><option value="file">File dal computer</option><option value="aruba">Aruba tramite API</option></select>
            </x-form-group>
        </div>
        @if($source === 'file')
            <x-form-group label="Documento originale" name="file" for="invoice-import-file" required>
                <input id="invoice-import-file" type="file" wire:model="file" accept=".xml,.pdf,.p7m" class="form-in">
                <p class="u-text-meta u-mt-md">XML o XML.P7M per leggere i dati automaticamente; PDF per compilare e verificare i dati manualmente. Massimo 10 MB.</p>
            </x-form-group>
            <p x-show="uploading" x-cloak role="status" class="u-text-meta u-mt-md">Caricamento del documento in corso…</p>
            <button type="button" wire:click="previewFile" wire:loading.attr="disabled" :disabled="uploading || !$wire.file" class="btn btn-p">Leggi documento</button>
        @else
            <p class="u-text-meta u-mb-md">La ricerca usa le credenziali Aruba già configurate. Seleziona al massimo due giorni consecutivi di acquisizione su Aruba.</p>
            <div class="form-row">
                <x-form-group label="Acquisite dal" name="from" for="invoice-import-from"><input id="invoice-import-from" type="date" wire:model="from" class="form-in"></x-form-group>
                <x-form-group label="Acquisite fino al" name="to" for="invoice-import-to"><input id="invoice-import-to" type="date" wire:model="to" class="form-in"></x-form-group>
            </div>
            <button type="button" wire:click="searchAruba" wire:loading.attr="disabled" class="btn btn-p">Cerca in Aruba</button>
            @if($searched)
                <div class="u-mt-lg">
                    @forelse($arubaResults as $row => $entry)
                        <article class="finance-list-item" wire:key="aruba-import-{{ $direction }}-{{ $arubaPage }}-{{ $row }}">
                            <div><strong>{{ $entry['name'] }}</strong><p>Fattura {{ $entry['number'] }} · {{ substr($entry['date'], 0, 10) }}</p>
                                @if($entry['total'] !== '')<p>{{ number_format((float)$entry['total'], 2, ',', '.') }} (valuta verificata nell’anteprima)</p>@endif
                                @if($entry['status'])<p>{{ $entry['status'] }}</p>@endif
                            </div>
                            <button type="button" class="btn btn-g" wire:click="previewAruba({{ $row }})" wire:loading.attr="disabled">Verifica fattura</button>
                        </article>
                    @empty<p>Nessuna fattura trovata nel periodo selezionato.</p>@endforelse
                    <nav class="commercial-actions u-mt-md" aria-label="Risultati Aruba">
                        @if($arubaPage > 1)<button type="button" class="btn btn-g" wire:click="searchAruba({{ $arubaPage - 1 }})" wire:loading.attr="disabled">Pagina precedente</button>@endif
                        @if(!$arubaLast)<button type="button" class="btn btn-g" wire:click="searchAruba({{ $arubaPage + 1 }})" wire:loading.attr="disabled">Pagina successiva</button>@endif
                    </nav>
                </div>
            @endif
        @endif
        <p role="status" class="u-text-meta u-mt-md" wire:loading>Operazione in corso…</p>
    </x-panel>
    @if($sourceToken)
        <p class="u-text-meta u-mt-md invoice-import-filename">Documento selezionato: {{ $sourceLabel }}</p>
    @endif
    @if($pdfForm)
        <x-panel title="Dati della fattura PDF" padded class="u-mt-lg">
            <p class="u-mb-md">Riporta i dati del documento. Il PDF viene conservato come allegato; il contenuto non viene letto automaticamente.</p>
            <div class="form-row">
                @foreach(['number' => 'Numero fattura', 'issue_date' => 'Data fattura'] as $field => $label)
                    <x-form-group :label="$label" :name="'manual.'.$field" :for="'import-manual-'.$field" required><input id="import-manual-{{ $field }}" class="form-in" type="{{ $field === 'issue_date' ? 'date' : 'text' }}" wire:model="manual.{{ $field }}" maxlength="100"></x-form-group>
                @endforeach
            </div>
            @if($direction === 'in')
                <div class="form-row">
                    @foreach(['issuer' => 'Fornitore', 'identifier' => 'Partita IVA o codice fiscale', 'country' => 'Paese fiscale (es. IT)'] as $field => $label)
                        <x-form-group :label="$label" :name="'manual.'.$field" :for="'import-manual-'.$field" required><input id="import-manual-{{ $field }}" class="form-in" wire:model="manual.{{ $field }}" maxlength="{{ $field === 'country' ? 2 : ($field === 'issuer' ? 255 : 50) }}"></x-form-group>
                    @endforeach
                </div>
            @else
                @include('invoices.partials.import-client')
            @endif
            <div class="form-row">
                @foreach(($direction === 'out' ? ['subtotal' => 'Imponibile (€)', 'tax_amount' => 'IVA (€)', 'total' => 'Totale fattura (€)'] : ['total' => 'Totale fattura (€)']) as $field => $label)
                    <x-form-group :label="$label" :name="'manual.'.$field" :for="'import-manual-'.$field" required><input id="import-manual-{{ $field }}" type="number" min="0" max="99999999.99" step="0.01" class="form-in" wire:model="manual.{{ $field }}"></x-form-group>
                @endforeach
            </div>
            <button type="button" class="btn btn-p" wire:click="reviewPdf" wire:loading.attr="disabled">Verifica dati inseriti</button>
        </x-panel>
    @endif
    @if($selected)
        <x-panel title="Anteprima da verificare" padded class="u-mt-lg">
            @if(count($documents) > 1)
                <x-form-group label="Fattura nel file" for="import-body"><select id="import-body" wire:model.live="bodyIndex" class="form-sel">@foreach($documents as $index => $document)<option value="{{ $index }}">{{ $document['number'] }} — {{ $document['issue_date'] }}</option>@endforeach</select></x-form-group>
            @endif
            <dl class="ticket-request-details">
                <div><dt>{{ $direction === 'in' ? 'Fornitore' : 'Destinatario' }}</dt><dd>{{ $selected['counterparty']['name'] }}</dd></div>
                <div><dt>Identificativo fiscale</dt><dd>{{ ($selected['counterparty']['vat_number'] ?? '') ?: ($selected['counterparty']['tax_code'] ?? 'Non indicato') }}</dd></div>
                <div><dt>Numero fattura</dt><dd>{{ $selected['number'] }}</dd></div>
                <div><dt>Data</dt><dd>{{ \Carbon\Carbon::parse($selected['issue_date'])->format('d/m/Y') }}</dd></div>
                @if($selected['subtotal'] !== null)<div><dt>Imponibile</dt><dd>{{ number_format((float)$selected['subtotal'], 2, ',', '.') }} €</dd></div>@endif
                @if($selected['tax_amount'] !== null)<div><dt>IVA</dt><dd>{{ number_format((float)$selected['tax_amount'], 2, ',', '.') }} €</dd></div>@endif
                <div><dt>Totale fattura</dt><dd><strong>{{ number_format((float)$selected['total'], 2, ',', '.') }} €</strong></dd></div>
            </dl>
            @if($direction === 'out') @include('invoices.partials.import-client') @endif
            @if(count($selected['payments']) > 1)
                <p class="u-text-meta u-mt-md">Il documento contiene più rate. Il gestionale usa una sola scadenza per questa registrazione: scegli la data da usare nel prospetto. Le scadenze originali restano conservate.</p>
                <ul class="invoice-import-payments">@foreach($selected['payments'] as $payment)<li>{{ $payment['date'] ?: 'Data non indicata' }} — {{ $payment['amount'] }} €</li>@endforeach</ul>
            @endif
            <x-form-group label="Scadenza da usare nel gestionale" name="dueDate" for="import-due-date">
                <input id="import-due-date" type="date" wire:model="dueDate" class="form-in">
                <p class="u-text-meta">Se non disponibile, lascia vuoto: la fattura non verrà collocata in una data inventata.</p>
            </x-form-group>
            @if(count($selected['items']))
                <details class="u-mt-md u-mb-md"><summary>Consulta le righe del documento</summary>
                    @foreach($selected['items'] as $item)<div class="commercial-history-row"><p>{{ $item['description'] }}</p><p class="u-text-meta">Quantità {{ $item['quantity'] ?: 'non indicata' }}; prezzo unitario {{ $item['unit_price'] }}; imponibile {{ $item['total'] }}; IVA {{ $item['vat_rate'] }}%</p></div>@endforeach
                </details>
            @endif
            <label class="invoice-import-check u-mt-lg"><input type="checkbox" wire:model="confirmed"> Ho verificato i dati e confermo che il documento corrisponde a una fattura {{ $direction === 'out' ? 'già emessa' : 'ricevuta' }}.</label>
            @if($direction === 'in')<p class="u-text-meta u-mt-md">Dopo l’importazione collega il documento a una spesa esistente oppure crea una nuova uscita.</p>@endif
            <div class="commercial-actions u-mt-lg">
                <button type="button" class="btn btn-p" wire:click="import" wire:loading.attr="disabled">Conferma importazione</button>
                @if($sourceFormat === 'pdf')<button type="button" class="btn btn-g" wire:click="editPdf" wire:loading.attr="disabled">Correggi dati PDF</button>@endif
                <button type="button" class="btn btn-g" wire:click="clearPreview" wire:loading.attr="disabled">Annulla anteprima</button>
            </div>
        </x-panel>
    @endif
</div>
