@php
    $serviceRows = old('requested_services', $ticket?->requestedServices?->map->only(['name', 'description'])->all());
    $serviceRows = collect(is_array($serviceRows) ? $serviceRows : [])->filter(fn ($row) => is_array($row))
        ->map(fn ($row) => ['name' => is_string($row['name'] ?? null) ? $row['name'] : '', 'description' => is_string($row['description'] ?? null) ? $row['description'] : ''])
        ->values()->all() ?: [['name' => '', 'description' => '']];
@endphp
<fieldset class="commercial-services" x-show="requestType === 'quote'" x-cloak :disabled="requestType !== 'quote'"
    x-data="{ services: @js(array_values($serviceRows)) }">
    <legend>Servizi richiesti</legend>
    <p class="u-text-meta">Indica i servizi da preventivare e i dettagli utili, come quantità, frequenza o tempi richiesti.</p>
    @error('requested_services')<p class="ca-error-text">{{ $message }}</p>@enderror
    @foreach($errors->get('requested_services.*') as $messages)
        @foreach($messages as $message)<p class="ca-error-text">{{ $message }}</p>@endforeach
    @endforeach
    <template x-for="(service, index) in services" :key="index">
        <div class="commercial-service-row">
            <label :for="'service-name-' + index">Servizio</label>
            <input :id="'service-name-' + index" :name="'requested_services[' + index + '][name]'" x-model="service.name" class="form-in" maxlength="255" :required="requestType === 'quote'">
            <label :for="'service-description-' + index">Dettagli del servizio</label>
            <textarea :id="'service-description-' + index" :name="'requested_services[' + index + '][description]'" x-model="service.description" class="form-ta" rows="3" maxlength="3000"></textarea>
            <button class="btn btn-g btn-sm" type="button" @click="services.splice(index, 1)" x-show="services.length > 1">Rimuovi servizio</button>
        </div>
    </template>
    <button class="btn btn-g" type="button" @click="services.push({name: '', description: ''})" :disabled="services.length >= 50">Aggiungi servizio</button>
</fieldset>
