@props(['type', 'name', 'value' => '', 'text' => '', 'client' => '', 'label', 'id' => null, 'serviceType' => '', 'freeText' => false])
@php($lookupId = $id ?? $name)
<div class="finance-lookup" x-data="financeLookup({ endpoint: @js(route('finance.lookup')), type: @js($type), value: @js($value), text: @js($text), client: @js($client), serviceType: @js($serviceType), freeText: @js($freeText) })"
    @click.outside="close()" @keydown.escape.stop.prevent="close(true)" @resize.window="place()" @scroll.window.capture="place()"
    @finance-selected.window="if ($event.detail.type === 'clients' && @js($type) !== 'clients') { client = $event.detail.id; clear(); }">
    <label class="form-lbl" for="{{ $lookupId }}-lookup">{{ $label }}</label>
    @unless($freeText)<input type="hidden" name="{{ $name }}" :value="value" value="{{ $value }}">@endunless
    <div class="finance-inline-form">
        <input id="{{ $lookupId }}-lookup" x-ref="search" type="search" x-model="text" value="{{ $text }}" @input="invalidate()" @input.debounce.250ms="search()" @focus="onFocus()" @keydown.arrow-down.prevent="firstOption()"
            @if($freeText) name="{{ $name }}" data-finance-live @endif
            class="form-in" autocomplete="off" maxlength="100" role="combobox" :aria-expanded="open" aria-controls="{{ $lookupId }}-options" aria-autocomplete="list">
        <button type="button" class="btn btn-g btn-sm finance-lookup-toggle" @click="open ? close() : search(true)" :aria-expanded="open" aria-controls="{{ $lookupId }}-options" aria-label="Mostra opzioni {{ mb_strtolower($label) }}"><span aria-hidden="true"></span></button>
        <button type="button" class="btn btn-g btn-sm" @click="clear()" aria-label="Rimuovi {{ mb_strtolower($label) }}">Azzera</button>
    </div>
    <div class="finance-lookup-results" id="{{ $lookupId }}-options" x-ref="options" x-show="open" x-cloak :style="optionsStyle" role="listbox" aria-label="{{ $label }}" :aria-busy="loading ? 'true' : 'false'">
        <template x-for="option in options" :key="option.id">
            <button type="button" role="option" :aria-selected="String(value) === String(option.id)" @click="select(option)"
                @keydown.arrow-down.prevent="$el.nextElementSibling?.focus()" @keydown.arrow-up.prevent="$el.previousElementSibling?.focus()" x-text="option.name"></button>
        </template>
        <p x-show="loading" role="status">Ricerca in corso…</p>
        <p x-show="!loading && !options.length">Nessun risultato. Prova un altro nome.</p>
        <p x-show="!loading && options.length === 30">Scrivi per restringere i risultati.</p>
    </div>
    <p class="form-err" x-show="error" x-text="error" role="status"></p>
    @error($name)<p class="form-err">{{ $message }}</p>@enderror
</div>
