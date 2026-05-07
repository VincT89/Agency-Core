<x-app-layout title="Modifica Servizio">
    <x-page-header eyebrow="Servizi IT" >
        <x-slot:title><strong>Modifica</strong> {{ $hostingService->name }}</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('hosting-services.show', $hostingService) }}" class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('hosting-services.update', $hostingService) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="sec-lbl">Dettagli Servizio</div>
            
            <div class="form-row">
                <x-form-group label="Tipo Servizio" name="type" required>
                    <select name="type" class="form-sel @error('type') is-invalid @enderror" required>
                        <option value="domain" {{ old('type', $hostingService->type) === 'domain' ? 'selected' : '' }}>Dominio</option>
                        <option value="hosting" {{ old('type', $hostingService->type) === 'hosting' ? 'selected' : '' }}>Hosting</option>
                        <option value="website" {{ old('type', $hostingService->type) === 'website' ? 'selected' : '' }}>Website</option>
                        <option value="maintenance" {{ old('type', $hostingService->type) === 'maintenance' ? 'selected' : '' }}>Manutenzione</option>
                        <option value="email" {{ old('type', $hostingService->type) === 'email' ? 'selected' : '' }}>Email</option>
                        <option value="dns" {{ old('type', $hostingService->type) === 'dns' ? 'selected' : '' }}>DNS</option>
                        <option value="other" {{ old('type', $hostingService->type) === 'other' ? 'selected' : '' }}>Altro</option>
                    </select>
                </x-form-group>
            </div>

            <div class="form-row full">
                <x-form-group label="Nome Identificativo" name="name" required>
                    <input name="name" class="form-in @error('name') is-invalid @enderror" value="{{ old('name', $hostingService->name) }}" required>
                </x-form-group>
            </div>

            <div class="form-row full">
                <div class="form-g" x-data="{
                    search: '{{ addslashes($hostingService->client->name ?? '') }}',
                    selectedId: '{{ $hostingService->client_id }}',
                    results: [],
                    isOpen: false,
                    async fetchResults() {
                        if (this.search.length < 2) { this.results = []; return; }
                        const res = await fetch(`{{ route('api.clients.search') }}?q=${this.search}`);
                        this.results = await res.json();
                    },
                    selectResult(r) {
                        this.selectedId = r.id;
                        this.search = r.name;
                        this.isOpen = false;
                    }
                }" @click.outside="isOpen = false">
                    <div class="form-lbl">Cliente <span style="color:var(--red)">*</span></div>
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
                    <input name="domain" class="form-in @error('domain') is-invalid @enderror" value="{{ old('domain', $hostingService->domain) }}">
                </x-form-group>
                <x-form-group label="Provider" name="provider">
                    <input name="provider" class="form-in @error('provider') is-invalid @enderror" value="{{ old('provider', $hostingService->provider) }}">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Location Server" name="location">
                    <input name="location" class="form-in @error('location') is-invalid @enderror" value="{{ old('location', $hostingService->location) }}" placeholder="Es. Arezzo, IT">
                </x-form-group>
                <x-form-group label="URL Pannello Controllo" name="access_url">
                    <input type="url" name="access_url" class="form-in @error('access_url') is-invalid @enderror" value="{{ old('access_url', $hostingService->access_url) }}" placeholder="Es. https://admin.aruba.it">
                </x-form-group>
            </div>

            <div class="sec-lbl hosting-services-section-title">Credenziali Accesso</div>
            <div class="form-row">
                <x-form-group label="Username" name="username">
                    <input name="username" class="form-in @error('username') is-invalid @enderror" value="{{ old('username', $hostingService->username) }}">
                </x-form-group>
                <x-form-group label="Password" name="password">
                    <input name="password" class="form-in @error('password') is-invalid @enderror" placeholder="Lascia vuoto per mantenere inalterata">
                    <div class="hosting-services-help-text">Lascia il campo vuoto se non vuoi sovrascrivere la password attuale.</div>
                </x-form-group>
            </div>

            <div class="sec-lbl hosting-services-section-title">Amministrazione</div>
            <div class="form-row">
                <x-form-group label="Stato" name="status" required>
                    <select name="status" class="form-sel @error('status') is-invalid @enderror" required>
                        <option value="active" {{ old('status', $hostingService->status) === 'active' ? 'selected' : '' }}>Attivo</option>
                        <option value="suspended" {{ old('status', $hostingService->status) === 'suspended' ? 'selected' : '' }}>Sospeso</option>
                        <option value="cancelled" {{ old('status', $hostingService->status) === 'cancelled' ? 'selected' : '' }}>Cancellato</option>
                    </select>
                </x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Costo di Rinnovo (€)" name="renewal_cost">
                    <input type="number" step="0.01" name="renewal_cost" class="form-in @error('renewal_cost') is-invalid @enderror" value="{{ old('renewal_cost', $hostingService->renewal_cost) }}">
                </x-form-group>
                <x-form-group label="Data Scadenza / Rinnovo" name="renewal_date">
                    <input type="date" name="renewal_date" class="form-in @error('renewal_date') is-invalid @enderror" value="{{ old('renewal_date', optional($hostingService->renewal_date)->format('Y-m-d')) }}">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Costo Risorse (€)" name="resource_cost">
                    <input type="number" step="0.01" name="resource_cost" class="form-in @error('resource_cost') is-invalid @enderror" value="{{ old('resource_cost', $hostingService->resource_cost) }}">
                </x-form-group>
                <x-form-group label="Ciclo di Fatturazione" name="billing_cycle">
                    <select name="billing_cycle" class="form-sel @error('billing_cycle') is-invalid @enderror">
                        <option value="">-- Seleziona --</option>
                        <option value="monthly" {{ old('billing_cycle', $hostingService->billing_cycle) === 'monthly' ? 'selected' : '' }}>Mensile</option>
                        <option value="yearly" {{ old('billing_cycle', $hostingService->billing_cycle) === 'yearly' ? 'selected' : '' }}>Annuale</option>
                        <option value="one_time" {{ old('billing_cycle', $hostingService->billing_cycle) === 'one_time' ? 'selected' : '' }}>Una tantum</option>
                        <option value="other" {{ old('billing_cycle', $hostingService->billing_cycle) === 'other' ? 'selected' : '' }}>Altro</option>
                    </select>
                </x-form-group>
            </div>

            <div class="form-row full hosting-services-mt-4">
                <x-form-group label="Note aggiuntive" name="notes">
                    <textarea name="notes" class="form-ta @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $hostingService->notes) }}</textarea>
                </x-form-group>
            </div>

            <div class="modal-ft hosting-services-footer">
                <a href="{{ route('hosting-services.show', $hostingService) }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva Modifiche</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>
