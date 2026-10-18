<div class="invoice-import" x-data="{ uploading: false }" x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-cancel="uploading = false">
    <x-page-header><x-slot:title>Importa XML da ZIP</x-slot:title><x-slot:actions><a href="{{ route('invoices.index') }}" class="btn btn-g">Fatture</a><a href="{{ route('credit-notes.index') }}" class="btn btn-g">Note di credito</a></x-slot:actions></x-page-header>
    <p class="u-mb-md">Carica l’esportazione XML di Aruba. Le fatture mantengono il numero originale e vengono registrate da incassare; le note di credito sono conservate separatamente. I documenti già presenti vengono saltati, senza modificare dati o incassi.</p>
    @if($errors->any())<div class="invalid-feedback u-mb-md" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <x-panel padded>
        <x-form-group label="Archivio ZIP" name="file" for="invoice-zip" required>
            <input id="invoice-zip" type="file" wire:model="file" accept=".zip" class="form-in">
            <p class="u-text-meta u-mt-md">Massimo 10 MB per ZIP, 100 documenti XML o XML.P7M e 20 MB complessivi dopo la decompressione.</p>
        </x-form-group>
        <button type="button" class="btn btn-p" wire:click="preview" wire:loading.attr="disabled" :disabled="uploading || !$wire.file">Leggi ZIP</button>
        <p x-show="uploading" x-cloak role="status" class="u-text-meta u-mt-md">Caricamento in corso.</p>
        <p wire:loading role="status" class="u-text-meta u-mt-md">Elaborazione dei documenti in corso.</p>
    </x-panel>
    @if($rows)
        <x-panel :title="$completed ? 'Esito importazione' : 'Documenti da verificare'" class="u-mt-lg">
            @if($completed)
                <p role="status" class="u-p-md">{{ collect($rows)->where('status', 'imported')->count() }} importati, {{ collect($rows)->where('status', 'skipped')->count() }} già presenti, {{ collect($rows)->where('status', 'error')->count() }} da verificare. Nessun documento esistente sovrascritto.</p>
            @endif
            <div class="t-table-wrap" role="region" aria-label="Documenti nello ZIP" tabindex="0">
                <table class="t-table"><thead><tr><th>Documento</th><th>Tipo</th><th>Cliente</th><th>Totale</th><th>Netto</th><th>{{ $completed ? 'Esito' : 'Verifica' }}</th></tr></thead><tbody>
                @foreach($rows as $index => $row)
                    <tr wire:key="zip-row-{{ $index }}">
                        <td>@if(isset($row['url']))<a href="{{ $row['url'] }}">{{ $row['number'] }}</a>@else{{ $row['number'] ?? $row['filename'] }}@endif</td>
                        <td>{{ ($row['document_type'] ?? '') === 'TD04' ? 'TD04 - Nota di credito' : ($row['document_type'] ?? '') }}</td>
                        <td>{{ $row['client'] ?? '' }}</td>
                        <td>{{ isset($row['total']) ? number_format((float) $row['total'], 2, ',', '.').' €' : '' }}</td>
                        <td>{{ isset($row['payable_total']) ? number_format((float) $row['payable_total'], 2, ',', '.').' €' : '' }}</td>
                        <td>{{ $row['message'] }}</td>
                    </tr>
                @endforeach
                </tbody></table>
            </div>
            @if(!$completed && $token)
                <div class="u-p-md">
                    <label class="invoice-import-check"><input type="checkbox" wire:model="confirmed"> Confermo l’importazione dei nuovi documenti e la creazione dei clienti mancanti. Le anagrafiche esistenti restano invariate.</label>
                    <button type="button" class="btn btn-p u-mt-md" wire:click="import" wire:loading.attr="disabled">Importa nuovi documenti</button>
                </div>
            @endif
        </x-panel>
    @endif
</div>
