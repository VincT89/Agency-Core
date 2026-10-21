<div class="inv-line-field finance-line-service finance-lookup"
    x-data="financeLookup({ endpoint: @js(route('finance.lookup')), type: 'services', value: line.hosting_service_id, text: line.service_name, client: document.getElementById('client_sel')?.value })"
    x-init="$watch('value', id => line.hosting_service_id = id)"
    @invoice-client-changed.window="client = $event.detail; clear()"
    @click.outside="open = false" @keydown.escape="open = false">
    <label class="form-lbl" :for="'line-service-' + line.key">Dominio o hosting del cliente</label>
    <input type="hidden" :name="`items[${index}][hosting_service_id]`" :value="value">
    <div class="finance-inline-form">
        <input :id="'line-service-' + line.key" class="form-in" type="search" x-model="text" @input="invalidate()" @input.debounce.300ms="search()" @focus="search()"
            autocomplete="off" role="combobox" :aria-expanded="open" :aria-controls="'line-services-' + line.key" placeholder="Cerca un servizio esistente">
        <button type="button" class="btn btn-g btn-sm" @click="clear()">Azzera</button>
    </div>
    <div class="finance-lookup-results" x-show="open" x-cloak :id="'line-services-' + line.key" role="listbox">
        <template x-for="option in options" :key="option.id"><button type="button" role="option" :aria-selected="String(value) === String(option.id)" @click="select(option)" x-text="option.name"></button></template>
        <p x-show="!loading && !options.length">Nessun servizio trovato per questo cliente.</p>
    </div>
    <p class="form-err" x-show="error" x-text="error" role="status"></p>
</div>
