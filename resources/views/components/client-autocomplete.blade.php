@props([
    'name' => 'client_id',
    'required' => false,
    'value' => null,
    'text' => null,
    'canCreate' => auth()->user()->can('create', \App\Models\Client::class),
])

<div x-data="clientAutocomplete({
        initialValue: @js($value),
        initialText: @js($text),
        canCreate: @js($canCreate),
        searchEndpoint: @js(route('api.clients.search')),
        storeEndpoint: @js(route('api.clients.quick-store'))
    })"
    class="ca-wrapper"
    @click.outside="close()"
>
    <div class="ca-input-container">
        <input type="text"
               id="{{ $name }}_search"
               x-model="search"
               @input.debounce.300ms="fetchResults()"
               @focus="open()"
               class="form-in ca-create-input @error($name) is-invalid @enderror"
               placeholder="Cerca cliente (min. 2 caratteri)..."
               autocomplete="off"
        >
        
        <input type="hidden" name="{{ $name }}" x-model="value">
        
        <div x-show="loading" class="ca-spinner-container">
            <svg style="animation: spin 1s linear infinite; height: 16px; width: 16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
    </div>

    {{-- Dropdown --}}
    <div x-show="isOpen" 
         x-transition
         class="ca-dropdown"
         style="display: none;">
         
         <ul class="ca-results-list">
             <template x-for="result in results" :key="result.id">
                 <li @click="selectClient(result)" class="ca-result-item">
                     <div class="ca-result-name" x-text="result.name"></div>
                     <div class="ca-result-meta">
                        <span x-show="result.company_name" x-text="result.company_name + (result.vat_number ? ' · ' : '')"></span>
                        <span x-show="result.vat_number" x-text="'P.IVA: ' + result.vat_number"></span>
                     </div>
                 </li>
             </template>
             
             <li x-show="!loading && search.length >= 2 && results.length === 0" class="ca-empty-state">
                 Nessun cliente trovato.
             </li>
             
             <li x-show="search.length < 2 && search.length > 0" class="ca-empty-state">
                 Digita almeno 2 caratteri per cercare.
             </li>
         </ul>
         
         {{-- Pulsante per aprire il form (mostrato solo nel dropdown) --}}
         <div x-show="canCreate && search.length >= 2 && results.length === 0" class="ca-create-box">
             <button type="button" 
                     @click="openQuickCreate()" 
                     class="btn btn-p" style="width: 100%;">
                 Crea nuovo cliente
             </button>
         </div>
    </div>

    {{-- Form Creazione Inline (fuori dal dropdown, spinge il layout) --}}
    <div x-show="showQuickCreate" style="display: none; margin-top: 12px; padding: 16px; background: var(--bg2); border: 1px dashed var(--line); border-radius: var(--r);">
         <div class="ca-create-form">
             <div class="ca-create-title">Nuovo Cliente</div>
             
             {{-- Row 1: Nome & Azienda --}}
             <div class="form-row">
                 <div>
                     <input type="text" x-model="newClient.name" class="form-in ca-create-input" :class="errors.name ? 'is-invalid' : ''" placeholder="Nome *">
                     <div x-show="errors.name" class="ca-error-text" x-text="errors.name"></div>
                 </div>
                 <div>
                     <input type="text" x-model="newClient.company_name" class="form-in ca-create-input" :class="errors.company_name ? 'is-invalid' : ''" placeholder="Ragione Sociale">
                     <div x-show="errors.company_name" class="ca-error-text" x-text="errors.company_name"></div>
                 </div>
             </div>

             {{-- Row 2: Email & Telefono --}}
             <div class="form-row">
                 <div>
                     <input type="email" x-model="newClient.email" class="form-in ca-create-input" :class="errors.email ? 'is-invalid' : ''" placeholder="Email">
                     <div x-show="errors.email" class="ca-error-text" x-text="errors.email"></div>
                 </div>
                 <div>
                     <input type="text" x-model="newClient.phone" class="form-in ca-create-input" :class="errors.phone ? 'is-invalid' : ''" placeholder="Telefono">
                     <div x-show="errors.phone" class="ca-error-text" x-text="errors.phone"></div>
                 </div>
             </div>

             {{-- Row 3: P.IVA & Indirizzo --}}
             <div class="form-row">
                 <div>
                     <input type="text" x-model="newClient.vat_number" class="form-in ca-create-input" :class="errors.vat_number ? 'is-invalid' : ''" placeholder="Partita IVA">
                     <div x-show="errors.vat_number" class="ca-error-text" x-text="errors.vat_number"></div>
                 </div>
                 <div>
                     <input type="text" x-model="newClient.address" class="form-in ca-create-input" :class="errors.address ? 'is-invalid' : ''" placeholder="Indirizzo">
                     <div x-show="errors.address" class="ca-error-text" x-text="errors.address"></div>
                 </div>
             </div>

             {{-- Notice for Default Status --}}
             <div class="ca-info-text">
                Il cliente verrà creato automaticamente con stato <strong>Attivo</strong>.
             </div>

             {{-- Actions --}}
             <div class="ca-actions">
                 <button type="button" @click="quickStoreClient()" class="btn btn-p ca-action-btn" :disabled="loading">
                     <span x-show="!loading">Salva Cliente</span>
                     <span x-show="loading">Salvataggio...</span>
                 </button>
                 <button type="button" @click="showQuickCreate = false" class="btn btn-g ca-action-btn">Annulla</button>
             </div>
             
             <div x-show="genericError" class="ca-error-text" x-text="genericError"></div>
         </div>
    </div>
</div>
