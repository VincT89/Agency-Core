<div class="quote-library" @click.outside="libraryOpen = false" @keydown.escape.stop.prevent="$refs.librarySearch.focus(); libraryOpen = false">
    <div class="quote-library-search">
        <div class="form-g">
            <label for="library-search">Aggiungi dalle voci salvate</label>
            <input id="library-search" x-ref="librarySearch" class="form-in" x-model="libraryQuery" @focus="openLibrary()"
                @input="libraryOpen = true; libraryLoading = true" @input.debounce.200ms="searchLibrary()" @keydown.enter.prevent="chooseFirstService()"
                @keydown.arrow-down.prevent="$refs.libraryResults.querySelector('button')?.focus()"
                :aria-expanded="libraryOpen.toString()" aria-controls="quote-library-results" autocomplete="off" maxlength="100" placeholder="Cerca un servizio: testi e prezzo sono già pronti">
        </div>
        <a class="btn btn-g btn-sm" href="{{ route('quote-services.index') }}" target="_blank" rel="noopener">Gestisci voci</a>
    </div>
    <div id="quote-library-results" x-ref="libraryResults" x-show="libraryOpen" x-cloak class="quote-library-results">
        <p x-show="libraryLoading" role="status">Ricerca in corso...</p>
        <p x-show="!libraryLoading && libraryResults.length === 0">Nessuna voce trovata. Puoi compilare un servizio e salvarlo in libreria.</p>
        <ul x-show="!libraryLoading && libraryResults.length">
            <template x-for="service in libraryResults" :key="service.id"><li>
                <button type="button" @click="useService(service)" :disabled="items.length >= 50" :aria-label="'Inserisci ' + service.name">
                    <span><strong x-text="service.name"></strong><span class="quote-library-description" x-text="service.summary || service.description"></span></span>
                    <span class="quote-library-price" x-text="money(cents(service.unit_price)) + ' / unità'"></span>
                </button>
            </li></template>
        </ul>
    </div>
</div>
