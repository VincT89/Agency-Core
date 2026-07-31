<x-app-layout title="Dati fiscali dell’agenzia">
    <x-page-header eyebrow="Amministrazione">
        <x-slot:title><strong>Dati fiscali</strong> dell’agenzia</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('invoices.index') }}" class="btn btn-g">Torna alle fatture</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <div class="u-mb-lg">
            <div class="u-text-strong">Dati dell’emittente</div>
        </div>

        <form action="{{ route('billing-profile.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="sec-lbl">Identità fiscale</div>
            <div class="form-row">
                <x-form-group label="Denominazione o ragione sociale" name="legal_name" required>
                    <input name="legal_name" class="form-in @error('legal_name') is-invalid @enderror"
                           value="{{ old('legal_name', $billingProfile->legal_name) }}">
                </x-form-group>
                <x-form-group label="Regime fiscale" name="fiscal_regime" required>
                    <input name="fiscal_regime" list="fiscal-regimes"
                           class="form-in @error('fiscal_regime') is-invalid @enderror"
                           value="{{ old('fiscal_regime', $billingProfile->fiscal_regime) }}"
                           placeholder="RF01" maxlength="4">
                    <datalist id="fiscal-regimes">
                        <option value="RF01">Regime ordinario</option>
                        <option value="RF02">Contribuenti minimi</option>
                        <option value="RF19">Regime forfettario</option>
                    </datalist>
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Stato partita IVA" name="vat_country_code" required>
                    <input name="vat_country_code" class="form-in @error('vat_country_code') is-invalid @enderror"
                           value="{{ old('vat_country_code', $billingProfile->vat_country_code) }}"
                           maxlength="2" placeholder="IT">
                </x-form-group>
                <x-form-group label="Partita IVA" name="vat_number" required>
                    <input name="vat_number" class="form-in @error('vat_number') is-invalid @enderror"
                           value="{{ old('vat_number', $billingProfile->vat_number) }}">
                </x-form-group>
                <x-form-group label="Codice fiscale" name="tax_code">
                    <input name="tax_code" class="form-in @error('tax_code') is-invalid @enderror"
                           value="{{ old('tax_code', $billingProfile->tax_code) }}">
                </x-form-group>
            </div>

            <div class="sec-lbl u-mt-md">Sede</div>
            <div class="form-row full">
                <x-form-group label="Indirizzo" name="address" required>
                    <input name="address" class="form-in @error('address') is-invalid @enderror"
                           value="{{ old('address', $billingProfile->address) }}">
                </x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="CAP" name="postal_code" required>
                    <input name="postal_code" class="form-in @error('postal_code') is-invalid @enderror"
                           value="{{ old('postal_code', $billingProfile->postal_code) }}">
                </x-form-group>
                <x-form-group label="Comune" name="city" required>
                    <input name="city" class="form-in @error('city') is-invalid @enderror"
                           value="{{ old('city', $billingProfile->city) }}">
                </x-form-group>
                <x-form-group label="Provincia" name="province">
                    <input name="province" class="form-in @error('province') is-invalid @enderror"
                           value="{{ old('province', $billingProfile->province) }}" maxlength="2">
                </x-form-group>
                <x-form-group label="Codice Stato" name="country_code" required>
                    <input name="country_code" class="form-in @error('country_code') is-invalid @enderror"
                           value="{{ old('country_code', $billingProfile->country_code) }}"
                           maxlength="2" placeholder="IT">
                </x-form-group>
            </div>

            <div class="sec-lbl u-mt-md">Contatti e pagamenti</div>
            <div class="form-row">
                <x-form-group label="Email" name="email">
                    <input type="email" name="email" class="form-in @error('email') is-invalid @enderror"
                           value="{{ old('email', $billingProfile->email) }}">
                </x-form-group>
                <x-form-group label="PEC" name="pec">
                    <input type="email" name="pec" class="form-in @error('pec') is-invalid @enderror"
                           value="{{ old('pec', $billingProfile->pec) }}">
                </x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Codice destinatario dell’agenzia" name="recipient_code">
                    <input name="recipient_code" class="form-in @error('recipient_code') is-invalid @enderror"
                           value="{{ old('recipient_code', $billingProfile->recipient_code) }}"
                           maxlength="7">
                </x-form-group>
                <x-form-group label="Modalità di pagamento predefinita" name="default_payment_method" required>
                    <select name="default_payment_method"
                            class="form-in @error('default_payment_method') is-invalid @enderror">
                        @foreach($paymentMethods as $paymentMethod)
                            <option value="{{ $paymentMethod->value }}"
                                @selected(old('default_payment_method', $billingProfile->default_payment_method) === $paymentMethod->value)>
                                {{ $paymentMethod->value }} — {{ $paymentMethod->label() }}
                            </option>
                        @endforeach
                    </select>
                </x-form-group>
                <x-form-group label="IBAN" name="iban">
                    <input name="iban" class="form-in @error('iban') is-invalid @enderror"
                           value="{{ old('iban', $billingProfile->iban) }}">
                </x-form-group>
            </div>

            <div class="sec-lbl u-mt-md">Numerazione fiscale</div>
            <div class="form-row">
                <x-form-group label="Serie" name="invoice_series" required>
                    <input name="invoice_series" class="form-in @error('invoice_series') is-invalid @enderror"
                           value="{{ old('invoice_series', $billingProfile->invoice_series) }}"
                           placeholder="FE">
                </x-form-group>
                <x-form-group label="Primo numero della serie" name="initial_sequence" required>
                    <input type="number" name="initial_sequence"
                           class="form-in @error('initial_sequence') is-invalid @enderror"
                           value="{{ old('initial_sequence', $billingProfile->initial_sequence) }}"
                           min="1" step="1">
                </x-form-group>
            </div>
            <div class="u-text-meta u-mb-lg">
                Esempio: serie FE, anno 2026 e progressivo 1 producono FE-2026-0001.
            </div>

            <div class="modal-ft form-footer-sep">
                <a href="{{ route('invoices.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva dati fiscali</button>
            </div>
        </form>
    </x-panel>

    <div class="inv-panel-gap">
        <x-panel title="Collegamento Aruba" dot="var(--accent)" padded>
            <div class="inv-section-copy u-mb-lg">
                <div class="u-text-strong">Stato del collegamento</div>
            </div>

            <div class="aruba-status-grid">
                <div class="aruba-status-card">
                    <div class="form-lbl inv-lbl">Connessione</div>
                    <x-badge
                        :status="$arubaStatus['ready_for_validation'] ? 'paid' : 'pending'"
                        :label="$arubaStatus['ready_for_validation'] ? 'Disponibile' : 'Non disponibile'" />
                </div>
                <div class="aruba-status-card">
                    <div class="form-lbl inv-lbl">Ricezione esiti</div>
                    <x-badge
                        :status="$arubaStatus['callback_configured'] ? 'paid' : 'pending'"
                        :label="$arubaStatus['callback_configured'] ? 'Attiva' : 'Non disponibile'" />
                </div>
                <div class="aruba-status-card">
                    <div class="form-lbl inv-lbl">Invio fatture</div>
                    <x-badge
                        :status="$arubaStatus['ready_for_send'] ? 'paid' : 'pending'"
                        :label="$arubaStatus['ready_for_send'] ? 'Disponibile' : 'Non disponibile'" />
                </div>
            </div>

            @if(isset($arubaStatus['configuration_error']))
                <div class="inv-fiscal-message has-errors">
                    <div class="u-text-strong">Collegamento non disponibile</div>
                </div>
            @endif

            @if($errors->has('aruba'))
                <div class="inv-fiscal-message has-errors">
                    <div class="u-text-strong">Collegamento non verificato</div>
                    <div class="u-text-meta">{{ $errors->first('aruba') }}</div>
                </div>
            @endif

            <div class="fiscal-action-row">
                <form action="{{ route('billing-profile.aruba.test') }}" method="POST"
                      x-data="{ submitting: false }"
                      @submit="if (submitting) { $event.preventDefault(); return; } submitting = true">
                    @csrf
                    <button type="submit" class="btn btn-p"
                            :disabled="submitting || {{ $arubaStatus['ready_for_validation'] ? 'false' : 'true' }}">
                        <span x-show="!submitting">Verifica collegamento</span>
                        <span x-show="submitting" x-cloak class="btn-loading-copy">
                            <span class="inline-loader" aria-hidden="true"></span>
                            Collegamento in verifica
                        </span>
                    </button>
                </form>
            </div>
        </x-panel>
    </div>
</x-app-layout>
