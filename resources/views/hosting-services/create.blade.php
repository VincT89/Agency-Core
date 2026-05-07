<x-app-layout title="Nuovo Servizio">
    <x-page-header eyebrow="Servizi IT" >
        <x-slot:title><strong>Nuovo</strong> {{ request('type') === 'domain' ? 'dominio' : 'servizio' }}</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('hosting-services.index', ['type' => request('type'), 'exclude_type' => request('exclude_type')]) }}" class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('hosting-services.store') }}" method="POST">
            @csrf
            
            @if(request()->has('type') && request('type') === 'domain')
                <input type="hidden" name="type" value="domain">
            @elseif(request()->has('exclude_type') && request('exclude_type') === 'domain')
                <input type="hidden" name="type" value="hosting">
            @endif

            <div class="hosting-services-help">
                <i data-lucide="info" class="hosting-services-help-icon"></i>
                I campi non contrassegnati da <b>*</b> sono opzionali.
            </div>

            <div class="sec-lbl">Dettagli Servizio</div>
            
            <div class="form-row {{ request()->has('type') ? 'u-hidden' : '' }}">
                <x-form-group label="Tipo Servizio" name="type" required>
                    <select name="type" class="form-sel @error('type') is-invalid @enderror" required {{ request()->has('type') ? 'disabled' : '' }}>
                        <option value="domain" {{ old('type', request('type')) === 'domain' ? 'selected' : '' }}>Dominio</option>
                        <option value="hosting" {{ old('type', request('type', 'hosting')) === 'hosting' ? 'selected' : '' }}>Hosting</option>
                        <option value="website" {{ old('type') === 'website' ? 'selected' : '' }}>Website</option>
                        <option value="maintenance" {{ old('type') === 'maintenance' ? 'selected' : '' }}>Manutenzione</option>
                        <option value="email" {{ old('type') === 'email' ? 'selected' : '' }}>Email</option>
                        <option value="dns" {{ old('type') === 'dns' ? 'selected' : '' }}>DNS</option>
                        <option value="other" {{ old('type') === 'other' ? 'selected' : '' }}>Altro</option>
                    </select>
                </x-form-group>
            </div>

            <div class="form-row full">
                <x-form-group label="Nome Identificativo" name="name" required>
                    <input name="name" class="form-in @error('name') is-invalid @enderror" value="{{ old('name') }}" required placeholder="Es. Hosting Sito Web Srl">
                </x-form-group>
            </div>

            <div class="form-row full">
                <div class="form-g" x-data="clientAutocomplete('{{ route('api.clients.search') }}')" @click.outside="isOpen = false">
                    <div class="form-lbl">Cliente <span class="u-text-red">*</span></div>
                    <div class="hosting-services-relative">
                        <input type="hidden" name="client_id" x-model="selectedId">
                        <input type="text" class="form-in @error('client_id') is-invalid @enderror" x-model="search" @focus="isOpen = true; if(search.length === 0) fetchResults()" @input.debounce.300ms="fetchResults" placeholder="Cerca cliente..." autocomplete="off">
                        
                        <div class="autocomplete-dropdown u-hidden" :class="{'u-hidden': !(isOpen && results.length > 0)}">
                            <template x-for="result in results" :key="result.id">
                                <div class="autocomplete-item" @click="selectResult(result)">
                                    <span x-text="result.name"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                    @error('client_id') <div class="hosting-services-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-row">
                <x-form-group label="Dominio / URL" name="domain">
                    <input name="domain" class="form-in @error('domain') is-invalid @enderror" value="{{ old('domain') }}">
                </x-form-group>
                <x-form-group label="Provider" name="provider">
                    <input name="provider" class="form-in @error('provider') is-invalid @enderror" value="{{ old('provider') }}" placeholder="Es. Aruba, SiteGround">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Location Server" name="location">
                    <input name="location" class="form-in @error('location') is-invalid @enderror" value="{{ old('location') }}" placeholder="Es. Arezzo, IT">
                </x-form-group>
                <x-form-group label="URL Pannello Controllo" name="access_url">
                    <input type="url" name="access_url" class="form-in @error('access_url') is-invalid @enderror" value="{{ old('access_url') }}" placeholder="Es. https://admin.aruba.it">
                </x-form-group>
            </div>

            <div class="sec-lbl hosting-services-section-title">Credenziali Accesso</div>
            <div class="form-row">
                <x-form-group label="Username" name="username">
                    <input name="username" class="form-in @error('username') is-invalid @enderror" value="{{ old('username') }}">
                </x-form-group>
                <x-form-group label="Password" name="password">
                    <input name="password" class="form-in @error('password') is-invalid @enderror" placeholder="Inserisci password in chiaro">
                </x-form-group>
            </div>

            <div class="sec-lbl hosting-services-section-title">Amministrazione</div>
            <div class="form-row">
                <x-form-group label="Costo di Rinnovo (€)" name="renewal_cost">
                    <input type="number" step="0.01" name="renewal_cost" class="form-in @error('renewal_cost') is-invalid @enderror" value="{{ old('renewal_cost') }}">
                </x-form-group>
                <x-form-group label="Data Scadenza / Rinnovo" name="renewal_date">
                    <input type="date" name="renewal_date" class="form-in @error('renewal_date') is-invalid @enderror" value="{{ old('renewal_date') }}">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Costo Risorse (€)" name="resource_cost">
                    <input type="number" step="0.01" name="resource_cost" class="form-in @error('resource_cost') is-invalid @enderror" value="{{ old('resource_cost') }}">
                </x-form-group>
                <x-form-group label="Ciclo di Fatturazione" name="billing_cycle">
                    <select name="billing_cycle" class="form-sel @error('billing_cycle') is-invalid @enderror">
                        <option value="">-- Seleziona --</option>
                        <option value="monthly" {{ old('billing_cycle') === 'monthly' ? 'selected' : '' }}>Mensile</option>
                        <option value="yearly" {{ old('billing_cycle') === 'yearly' ? 'selected' : '' }}>Annuale</option>
                        <option value="one_time" {{ old('billing_cycle') === 'one_time' ? 'selected' : '' }}>Una tantum</option>
                        <option value="other" {{ old('billing_cycle') === 'other' ? 'selected' : '' }}>Altro</option>
                    </select>
                </x-form-group>
            </div>

            <div class="form-row full hosting-services-mt-4">
                <x-form-group label="Note aggiuntive" name="notes">
                    <textarea name="notes" class="form-ta @error('notes') is-invalid @enderror" rows="3">{{ old('notes') }}</textarea>
                </x-form-group>
            </div>

            <div class="modal-ft hosting-services-footer">
                <a href="{{ route('hosting-services.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva Servizio</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>
