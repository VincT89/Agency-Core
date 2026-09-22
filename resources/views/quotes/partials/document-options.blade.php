<details class="quote-options" @if($errors->any()) open @endif>
    <summary>Data, intestazione e indicazione IVA</summary>
    <div class="quote-option-fields">
        <p class="quote-help">Data e dati Sodano sono già compilati. Il riferimento è automatico; l’anagrafica cliente viene riportata nel documento.</p>
        <div class="quote-two-columns">
            <x-form-group label="Numero o riferimento" name="document_reference"><input id="document_reference" name="document_reference" class="form-in" value="{{ old('document_reference', $quote?->document_reference) }}" maxlength="80" placeholder="Automatico se vuoto"></x-form-group>
            <x-form-group label="Data preventivo" name="document_date"><input id="document_date" name="document_date" class="form-in" type="date" value="{{ old('document_date', ($quote?->document_date ?? $quote?->created_at ?? now())->format('Y-m-d')) }}"></x-form-group>
        </div>
        <x-form-group label="Indicazione accanto al totale" name="price_note">
            <input id="price_note" name="price_note" class="form-in" value="{{ old('price_note', $defaults ? $defaults->price_note : 'IVA esclusa') }}" maxlength="150">
        </x-form-group>
        @php($issuer = old('issuer', $quote?->issuer_snapshot ?? \App\Domain\Quotes\QuoteDocument::defaultIssuer()))
        <details class="quote-issuer" @if($errors->any()) open @endif><summary>Dati Sodano nell’intestazione</summary>
            <p class="quote-help">Le modifiche valgono per questa offerta.</p>
            <div class="quote-issuer-grid">
                @foreach(['legal_name' => 'Ragione sociale', 'address' => 'Indirizzo', 'postal_code' => 'CAP', 'city' => 'Comune', 'province' => 'Provincia', 'vat_number' => 'Partita IVA', 'tax_code' => 'Codice fiscale'] as $field => $label)
                    <div class="form-g"><label for="issuer_{{ $field }}">{{ $label }}</label><input id="issuer_{{ $field }}" name="issuer[{{ $field }}]" class="form-in" value="{{ is_array($issuer) && is_scalar($issuer[$field] ?? null) ? $issuer[$field] : '' }}" maxlength="{{ ['legal_name' => 255, 'address' => 255, 'postal_code' => 20, 'city' => 100, 'province' => 50, 'vat_number' => 50, 'tax_code' => 50][$field] }}" @required($field === 'legal_name')></div>
                @endforeach
            </div>
        </details>
    </div>
</details>
