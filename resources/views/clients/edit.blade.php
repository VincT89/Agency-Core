<x-app-layout title="Modifica Cliente">
    <x-page-header eyebrow="Modulo · Core" >
    <x-slot:title><strong>Modifica</strong> cliente</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('clients.show', $client) }}" class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('clients.update', $client) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PATCH')

            {{-- Sezione: Anagrafica principale --}}
            <div style="margin-bottom:16px;font-size:12px;color:var(--text3);">
                <i data-lucide="info" style="width:14px;height:14px;display:inline-block;vertical-align:text-bottom;margin-right:4px;"></i>
                I campi non contrassegnati da <b>*</b> sono opzionali.
            </div>

            <div class="sec-lbl">Anagrafica</div>
            <div class="form-row full">
                <x-form-group label="Nome / Ragione Sociale" name="name" required>
                    <input name="name" class="form-in @error('name') is-invalid @enderror"
                           value="{{ old('name', $client->name) }}" placeholder="Es. Acme S.r.l.">
                </x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Nome commerciale" name="company_name">
                    <input name="company_name" class="form-in @error('company_name') is-invalid @enderror"
                           value="{{ old('company_name', $client->company_name) }}">
                </x-form-group>
                <x-form-group label="Referente principale" name="reference_person">
                    <input name="reference_person" class="form-in @error('reference_person') is-invalid @enderror"
                           value="{{ old('reference_person', $client->reference_person) }}" placeholder="Nome Cognome">
                </x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Email" name="email">
                    <input type="email" name="email" class="form-in @error('email') is-invalid @enderror"
                           value="{{ old('email', $client->email) }}" placeholder="info@azienda.it">
                </x-form-group>
                <x-form-group label="Telefono" name="phone">
                    <input name="phone" class="form-in @error('phone') is-invalid @enderror"
                           value="{{ old('phone', $client->phone) }}" placeholder="+39 000 0000000">
                </x-form-group>
            </div>

            {{-- Sezione: Dati fiscali --}}
            <div class="sec-lbl" style="margin-top:16px">Dati fiscali</div>
            <div class="form-row">
                <x-form-group label="Partita IVA" name="vat_number">
                    <input name="vat_number" class="form-in @error('vat_number') is-invalid @enderror"
                           value="{{ old('vat_number', $client->vat_number) }}" placeholder="IT01234567890">
                </x-form-group>
                <x-form-group label="Codice Fiscale" name="tax_code">
                    <input name="tax_code" class="form-in @error('tax_code') is-invalid @enderror"
                           value="{{ old('tax_code', $client->tax_code) }}">
                </x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Email fatturazione" name="billing_email">
                    <input type="email" name="billing_email" class="form-in @error('billing_email') is-invalid @enderror"
                           value="{{ old('billing_email', $client->billing_email) }}" placeholder="fatture@azienda.it">
                </x-form-group>
                <x-form-group label="PEC" name="pec">
                    <input type="email" name="pec" class="form-in @error('pec') is-invalid @enderror"
                           value="{{ old('pec', $client->pec) }}" placeholder="azienda@pec.it">
                </x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Codice SDI" name="sdi_code">
                    <input name="sdi_code" class="form-in @error('sdi_code') is-invalid @enderror"
                           value="{{ old('sdi_code', $client->sdi_code) }}" placeholder="Es. XXXXXXX">
                </x-form-group>
                <x-form-group label="Stato cliente" name="status">
                    <select name="status" class="form-sel @error('status') is-invalid @enderror">
                        <option value="active"   {{ old('status', $client->status) == 'active'   ? 'selected' : '' }}>Attivo</option>
                        <option value="inactive" {{ old('status', $client->status) == 'inactive' ? 'selected' : '' }}>Inattivo</option>
                    </select>
                </x-form-group>
            </div>

            {{-- Sezione: Indirizzo --}}
            <div class="sec-lbl" style="margin-top:16px">Indirizzo</div>
            <div class="form-row full">
                <x-form-group label="Via / Indirizzo" name="address">
                    <input name="address" class="form-in @error('address') is-invalid @enderror"
                           value="{{ old('address', $client->address) }}" placeholder="Via Roma 1">
                </x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Città" name="city">
                    <input name="city" class="form-in @error('city') is-invalid @enderror"
                           value="{{ old('city', $client->city) }}" placeholder="Milano">
                </x-form-group>
                <x-form-group label="CAP" name="postal_code">
                    <input name="postal_code" class="form-in @error('postal_code') is-invalid @enderror"
                           value="{{ old('postal_code', $client->postal_code) }}" placeholder="20100">
                </x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Provincia" name="province">
                    <input name="province" class="form-in @error('province') is-invalid @enderror"
                           value="{{ old('province', $client->province) }}" placeholder="MI" maxlength="5">
                </x-form-group>
                <x-form-group label="Paese" name="country">
                    <input name="country" class="form-in @error('country') is-invalid @enderror"
                           value="{{ old('country', $client->country ?? 'Italia') }}">
                </x-form-group>
            </div>

            {{-- Note --}}
            <div class="form-row full" style="margin-top:4px">
                <x-form-group label="Note interne" name="notes">
                    <textarea name="notes" class="form-ta @error('notes') is-invalid @enderror"
                              rows="3" placeholder="Note riservate...">{{ old('notes', $client->notes) }}</textarea>
                </x-form-group>
            </div>

            {{-- Sezione: Identità marketing --}}
            <div class="sec-lbl" style="margin-top:16px">Identità marketing</div>
            <div class="form-row">
                <x-form-group label="Logo cliente" name="logo">
                    @if($client->logo_url)
                        <div style="margin-bottom:8px">
                            <img src="{{ $client->logo_url }}"
                                 alt="Logo {{ $client->name }}"
                                 style="max-height:70px;max-width:180px;object-fit:contain;"
                                 onerror="this.style.display='none'">
                        </div>
                    @endif
                    <input type="file"
                           name="logo"
                           accept="image/jpeg,image/png,image/webp"
                           class="form-in @error('logo') is-invalid @enderror">
                    <div style="font-size:11px;color:var(--text3);margin-top:4px;">Formati ammessi: JPG, PNG, WEBP. Max 4MB.</div>
                </x-form-group>

                <x-form-group label="Descrizione attività cliente" name="activity_description">
                    <textarea name="activity_description"
                              class="form-ta @error('activity_description') is-invalid @enderror"
                              rows="4"
                              placeholder="Es. Ristorante di cucina mediterranea a Roma, specializzato in pesce fresco.">{{ old('activity_description', $client->activity_description ?? '') }}</textarea>
                </x-form-group>
            </div>
            
            <div class="sec-lbl" style="margin-top:16px">Integrazione Nextcloud</div>
            <div class="form-row full">
                <x-form-group label="Nome Cartella Nextcloud" name="nextcloud_folder_name">
                    <input name="nextcloud_folder_name" class="form-in @error('nextcloud_folder_name') is-invalid @enderror"
                           value="{{ old('nextcloud_folder_name', $client->nextcloud_folder_name) }}" placeholder="Es. acme-srl">
                    <div style="font-size:11px;color:var(--text3);margin-top:4px;">
                        <strong>Modificabile solo con cautela.</strong> La modifica non sposta i file esistenti (nemmeno le foto già selezionate nei post associati), ma crea o punta a una nuova cartella in <code>/Photos/{nome_cartella}</code>. Usare solo lettere, numeri, trattini e underscore.
                    </div>
                </x-form-group>
            </div>

            <div class="modal-ft" style="border-top:1px solid var(--line);padding-top:16px;margin-top:16px">
                <a href="{{ route('clients.show', $client) }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Aggiorna Cliente</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>