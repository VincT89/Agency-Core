@php
    $client = $client ?? null;
    $registryValue = function (string $field, $default = '') use ($client) {
        $value = old($field, $client ? $client->$field : $default);
        return is_scalar($value) ? $value : '';
    };
@endphp

<h2 class="sec-lbl">Anagrafica</h2>
<div class="form-row full">
    <x-form-group label="Nome cliente" name="name" required>
        <input id="field-name" name="name" class="form-in @error('name') is-invalid @enderror"
               value="{{ $registryValue('name') }}" maxlength="255" required>
    </x-form-group>
</div>
<div class="form-row">
    @foreach(['company_name' => 'Ragione sociale', 'reference_person' => 'Referente principale'] as $field => $label)
        <x-form-group :label="$label" :name="$field">
            <input id="field-{{ \Illuminate\Support\Str::slug($field) }}" name="{{ $field }}" class="form-in @error($field) is-invalid @enderror"
                   value="{{ $registryValue($field) }}" maxlength="255">
        </x-form-group>
    @endforeach
</div>

@include('clients.partials.commercial-field')

<h2 class="sec-lbl u-mt-md">Contatti</h2>
<div class="form-row">
    <x-form-group label="Email" name="email">
        <input id="field-email" type="email" name="email" class="form-in @error('email') is-invalid @enderror"
               value="{{ $registryValue('email') }}" autocomplete="email" maxlength="255">
    </x-form-group>
    <x-form-group label="Telefono" name="phone">
        <input id="field-phone" type="tel" name="phone" class="form-in @error('phone') is-invalid @enderror"
               value="{{ $registryValue('phone') }}" autocomplete="tel" maxlength="50">
    </x-form-group>
</div>

<h2 class="sec-lbl u-mt-md">Dati fiscali e fatturazione</h2>
<div class="form-row">
    @foreach(['vat_number' => 'Partita IVA', 'tax_code' => 'Codice fiscale'] as $field => $label)
        <x-form-group :label="$label" :name="$field">
            <input id="field-{{ \Illuminate\Support\Str::slug($field) }}" name="{{ $field }}" class="form-in @error($field) is-invalid @enderror"
                   value="{{ $registryValue($field) }}" maxlength="20">
        </x-form-group>
    @endforeach
</div>
<div class="form-row">
    @foreach(['billing_email' => 'Email fatturazione', 'pec' => 'PEC'] as $field => $label)
        <x-form-group :label="$label" :name="$field">
            <input id="field-{{ \Illuminate\Support\Str::slug($field) }}" type="email" name="{{ $field }}" class="form-in @error($field) is-invalid @enderror"
                   value="{{ $registryValue($field) }}" maxlength="255">
        </x-form-group>
    @endforeach
</div>
<div class="form-row">
    <x-form-group label="Codice SDI" name="sdi_code">
        <input id="field-sdi-code" name="sdi_code" class="form-in @error('sdi_code') is-invalid @enderror"
               value="{{ $registryValue('sdi_code') }}" minlength="6" maxlength="7">
    </x-form-group>
</div>

<h2 class="sec-lbl u-mt-md">Indirizzo</h2>
<div class="form-row full">
    <x-form-group label="Via / Indirizzo" name="address">
        <input id="field-address" name="address" class="form-in @error('address') is-invalid @enderror"
               value="{{ $registryValue('address') }}" autocomplete="street-address" maxlength="255">
    </x-form-group>
</div>
<div class="form-row">
    @foreach(['city' => ['Comune', 100], 'postal_code' => ['CAP', 10], 'province' => ['Provincia', 5]] as $field => [$label, $length])
        <x-form-group :label="$label" :name="$field">
            <input id="field-{{ \Illuminate\Support\Str::slug($field) }}" name="{{ $field }}" class="form-in @error($field) is-invalid @enderror"
                   value="{{ $registryValue($field) }}" maxlength="{{ $length }}">
        </x-form-group>
    @endforeach
</div>
<div class="form-row">
    <x-form-group label="Codice Stato" name="country_code">
        <input id="field-country-code" name="country_code" class="form-in @error('country_code') is-invalid @enderror"
               value="{{ $registryValue('country_code', 'IT') }}" minlength="2" maxlength="2">
    </x-form-group>
    <x-form-group label="Paese" name="country">
        <input id="field-country" name="country" class="form-in @error('country') is-invalid @enderror"
               value="{{ $registryValue('country', 'Italia') }}" maxlength="100">
    </x-form-group>
</div>

<h2 class="sec-lbl u-mt-md">Note commerciali</h2>
<div class="form-row full">
    <x-form-group label="Note sul cliente" name="commercial_notes">
        <textarea id="field-commercial-notes" name="commercial_notes" class="form-ta @error('commercial_notes') is-invalid @enderror"
                  rows="4" maxlength="10000">{{ $registryValue('commercial_notes') }}</textarea>
        <p class="u-text-meta">Informazioni utili per seguire il rapporto commerciale con il cliente.</p>
    </x-form-group>
</div>
