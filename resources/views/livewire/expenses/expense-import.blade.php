<div x-data="{ uploading: false }" x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-cancel="uploading = false">
    <x-page-header><x-slot:title>Importa spese</x-slot:title><x-slot:actions><a href="{{ route('expenses.index') }}" class="btn btn-g">Torna alle spese</a></x-slot:actions></x-page-header>
    @include('expenses.partials.navigation')
    <p class="u-mb-md">Carica XLS, XLSX, CSV UTF-8, XML FatturaPA ricevuti o uno ZIP contenente questi formati. Massimo 10 MB per file, 30 elementi nello ZIP, 20 MB decompressi e 500 righe complessive.</p>
    <details class="finance-advanced u-mb-lg"><summary>Colonne per Excel e CSV</summary>
        <p class="u-mb-md">Obbligatorie: <strong>titolo, importo, data_spesa</strong>. Facoltative: fornitore, scadenza, categoria, sottocategoria, descrizione, note, riferimento, stato, data_pagamento, documento_id, tipo_documento.</p>
        <p class="u-mb-md">Date: GG/MM/AAAA o AAAA-MM-GG. Importi in euro, IVA compresa se dovuta, con massimo due decimali. In Excel usa valori, senza formule. Le categorie devono già esistere. Il riferimento distingue spese diverse con gli stessi dati.</p>
        <p>Se lo stato manca, viene proposta una spesa da pagare. Per importare una spesa pagata servono stato “pagata”, data_pagamento e documento_id di un giustificativo già caricato. Tipo_documento: invoice, payslip, receipt oppure other; se omesso, invoice. Gli XML vengono registrati da pagare e conservati come documenti del fornitore.</p>
    </details>
    @if($errors->any())<div class="invalid-feedback u-mb-md" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <x-panel padded>
        <x-form-group label="File delle spese" name="file" for="expense-import-file" required><input id="expense-import-file" type="file" wire:model="file" accept=".xls,.xlsx,.csv,.xml,.zip" class="form-in"></x-form-group>
        <button type="button" class="btn btn-p" wire:click="preview" wire:loading.attr="disabled" :disabled="uploading || !$wire.file">Leggi file</button>
        <p x-show="uploading" x-cloak role="status">Caricamento in corso.</p><p wire:loading role="status">Verifica delle spese in corso.</p>
    </x-panel>
    @if($rows)
        <x-panel :title="$completed ? 'Esito importazione' : 'Spese da verificare'" class="u-mt-lg">
            @if($completed)<p class="u-p-md" role="status">{{ collect($rows)->where('status', 'imported')->count() }} importate, {{ collect($rows)->where('status', 'skipped')->count() }} già presenti, {{ collect($rows)->where('status', 'error')->count() }} da verificare.</p>@endif
            <div class="t-table-wrap" role="region" aria-label="Anteprima delle spese" tabindex="0"><table class="t-table"><thead><tr><th>Spesa e origine</th><th>Fornitore</th><th>Data</th><th>Importo</th><th>Esito</th></tr></thead><tbody>
                @foreach($rows as $index => $row)<tr wire:key="expense-import-row-{{ $index }}"><td>@if(isset($row['url']))<a href="{{ $row['url'] }}">{{ $row['title'] }}</a>@else{{ $row['title'] }}@endif<p class="u-text-meta">{{ $row['filename'] }}, riga {{ $row['line'] }}</p></td><td>{{ $row['supplier'] ?? '' }}</td><td>{{ $row['expense_date'] ?? '' }}</td><td>{{ isset($row['amount']) ? number_format((float) $row['amount'], 2, ',', '.').' €' : '' }}</td><td>{{ $row['message'] }}</td></tr>@endforeach
            </tbody></table></div>
            @if(!$completed && collect($rows)->contains('status', 'ready'))<div class="u-p-md"><label><input type="checkbox" wire:model="confirmed"> Confermo l’importazione delle righe valide. Le righe con errori sono escluse; le spese esistenti non vengono sovrascritte.</label><button type="button" class="btn btn-p u-mt-md" wire:click="import" wire:loading.attr="disabled">Importa spese valide</button></div>@endif
        </x-panel>
    @endif
</div>
