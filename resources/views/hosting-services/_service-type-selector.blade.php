<div class="form-row full">
    <x-form-group label="Tipi servizio" name="service_types" required for="hosting-service-type-domain">
        <div
            class="hosting-type-options @error('service_types') is-invalid @enderror"
            data-required-checkbox-group
            data-required-message="Seleziona almeno un tipo di servizio."
        >
            @foreach(\App\Models\HostingService::TYPE_LABELS as $typeValue => $typeLabel)
                <label class="hosting-type-option">
                    <input
                        id="hosting-service-type-{{ $typeValue }}"
                        type="checkbox"
                        name="service_types[]"
                        value="{{ $typeValue }}"
                        x-model="serviceTypes"
                        {{ in_array($typeValue, $selectedServiceTypes, true) ? 'checked' : '' }}
                    >
                    <span>{{ $typeLabel }}</span>
                </label>
            @endforeach
        </div>
        <div class="hosting-services-help-text">
            Seleziona tutti i servizi compresi nello stesso rinnovo.
        </div>
        @error('service_types.*')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </x-form-group>
</div>
