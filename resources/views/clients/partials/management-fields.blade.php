<h2 class="sec-lbl u-mt-md">Gestione interna</h2>
<div class="form-row">
    <x-form-group label="Stato cliente" name="status" required>
        <select id="field-status" name="status" class="form-sel @error('status') is-invalid @enderror" required>
            <option value="active" @selected(old('status', $client?->status ?? 'active') === 'active')>Attivo</option>
            <option value="inactive" @selected(old('status', $client?->status) === 'inactive')>Inattivo</option>
        </select>
    </x-form-group>
</div>
<div class="form-row full">
    <x-form-group label="Note interne" name="notes">
        <textarea id="field-notes" name="notes" class="form-ta @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $client?->notes) }}</textarea>
    </x-form-group>
</div>

<h2 class="sec-lbl u-mt-md">Identità marketing</h2>
<div class="form-row">
    <x-form-group label="Logo cliente" name="logo">
        @if($client?->logo_url)
            <img src="{{ $client->logo_url }}" alt="Logo {{ $client->name }}" class="client-logo-preview u-mb-sm">
        @endif
        <input id="field-logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="form-in @error('logo') is-invalid @enderror">
        <p class="u-text-meta">Formati ammessi: JPG, PNG, WEBP. Max 4 MB.</p>
    </x-form-group>
    <x-form-group label="Descrizione attività cliente" name="activity_description">
        <textarea id="field-activity-description" name="activity_description" class="form-ta @error('activity_description') is-invalid @enderror"
                  rows="4" maxlength="2000">{{ old('activity_description', $client?->activity_description) }}</textarea>
    </x-form-group>
</div>

<h2 class="sec-lbl u-mt-md">Documenti del cliente</h2>
<div class="form-row full">
    <x-form-group label="Nome cartella Nextcloud" name="nextcloud_folder_name">
        <input id="field-nextcloud-folder-name" name="nextcloud_folder_name" class="form-in @error('nextcloud_folder_name') is-invalid @enderror"
               value="{{ old('nextcloud_folder_name', $client?->nextcloud_folder_name) }}" maxlength="100">
        <p class="u-text-meta">Facoltativa: puoi collegare la cartella documenti anche in seguito. Usa lettere, numeri, trattini o underscore.</p>
        @if($client?->nextcloud_folder_name)
            <p class="u-text-meta">Se cambi cartella, i documenti già presenti restano nella cartella precedente.</p>
        @endif
    </x-form-group>
</div>
