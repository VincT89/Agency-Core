<div class="u-mt-md" wire:key="import-client-{{ $bodyIndex }}-{{ $createClient ? 'new' : 'existing' }}">
    @if($sourceFormat !== 'pdf' && $selected && (($selected['counterparty']['vat_number'] ?? '') ?: ($selected['counterparty']['tax_code'] ?? '')))
        @can('create', \App\Models\Client::class)
            <label class="invoice-import-check"><input type="checkbox" wire:model.live="createClient"> Crea una nuova anagrafica con i dati del destinatario letti dalla fattura</label>
        @endcan
    @endif
    @if($sourceFormat === 'pdf' && !$pdfForm)
        <p>Cliente selezionato: <strong>{{ $selected['counterparty']['name'] }}</strong></p>
    @elseif(!$createClient)
        <div x-on:client-updated="$wire.set('clientId', $event.detail)" wire:key="import-client-search-{{ $bodyIndex }}-{{ $selectedClient?->id ?? 'empty' }}">
            <x-form-group label="Cliente nel gestionale" name="clientId" for="import_client_id_search" required>
                <x-client-autocomplete name="import_client_id" :value="$selectedClient?->id" :text="$selectedClient?->name" :extended="true" />
            </x-form-group>
        </div>
        <p class="u-text-meta">Seleziona il cliente esistente oppure aggiungilo con la ricerca clienti. Per un XML i dati fiscali devono corrispondere al destinatario.</p>
    @else
        <p class="u-text-meta">L’anagrafica verrà creata solo confermando l’importazione. I clienti già presenti non vengono modificati.</p>
    @endif
</div>
