@php
    $initialItems = $quote?->items->map->only(['name', 'description', 'quantity', 'unit_price'])->all()
        ?? $ticket?->requestedServices->map(fn ($service) => ['name' => $service->name, 'description' => $service->description, 'quantity' => '1', 'unit_price' => ''])->all();
    $initialItems = old('items', $initialItems);
    $initialItems = collect(is_array($initialItems) ? $initialItems : [])->filter(fn ($row) => is_array($row))
        ->map(fn ($row) => collect(['name' => '', 'description' => '', 'quantity' => '1', 'unit_price' => ''])
            ->map(fn ($default, $field) => is_scalar($row[$field] ?? null) ? (string) $row[$field] : $default)->all())
        ->values()->all() ?: [['name' => '', 'description' => '', 'quantity' => '1', 'unit_price' => '']];
@endphp
<x-app-layout :title="$quote ? 'Modifica bozza offerta' : 'Prepara offerta'">
    <x-page-header><x-slot:title>{{ $quote ? 'Modifica bozza offerta' : 'Prepara offerta' }}</x-slot:title></x-page-header>
    <x-panel padded>
        <p class="u-mb-md">Cliente: <strong>{{ $client->name }}</strong></p>
        @if($errors->any())<div role="alert" class="ca-error-text">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="POST" action="{{ $quote ? route('quotes.update', $quote) : route('quotes.store') }}" x-data="{ items: @js($initialItems) }">
            @csrf
            @if($quote) @method('PUT') @else
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                @if($ticket)<input type="hidden" name="ticket_id" value="{{ $ticket->id }}">@endif
            @endif
            <x-form-group label="Oggetto dell’offerta" name="title" required>
                <input name="title" class="form-in" value="{{ old('title', $quote?->title ?? $ticket?->title) }}" maxlength="255" required>
            </x-form-group>
            <fieldset class="commercial-services">
                <legend>Servizi e importi</legend>
                <template x-for="(item, index) in items" :key="index">
                    <div class="commercial-service-row">
                        <label :for="'item-name-' + index">Servizio</label>
                        <input :id="'item-name-' + index" :name="'items[' + index + '][name]'" x-model="item.name" class="form-in" maxlength="255" required>
                        <label :for="'item-description-' + index">Descrizione</label>
                        <textarea :id="'item-description-' + index" :name="'items[' + index + '][description]'" x-model="item.description" class="form-ta" rows="3" maxlength="3000"></textarea>
                        <div class="form-row">
                            <div><label :for="'item-quantity-' + index">Quantità</label>
                                <input :id="'item-quantity-' + index" :name="'items[' + index + '][quantity]'" x-model="item.quantity" class="form-in" type="number" min="0.01" max="9999.99" step="0.01" required></div>
                            <div><label :for="'item-price-' + index">Prezzo unitario (€)</label>
                                <input :id="'item-price-' + index" :name="'items[' + index + '][unit_price]'" x-model="item.unit_price" class="form-in" type="number" min="0" max="999999.99" step="0.01" required></div>
                        </div>
                        <button class="btn btn-g btn-sm" type="button" @click="items.splice(index, 1)" x-show="items.length > 1">Rimuovi servizio</button>
                    </div>
                </template>
                <button type="button" class="btn btn-g" @click="items.push({name: '', description: '', quantity: '1', unit_price: ''})" :disabled="items.length >= 50">Aggiungi servizio</button>
            </fieldset>
            <x-form-group label="Condizioni e note dell’offerta" name="notes">
                <textarea name="notes" class="form-ta" rows="4" maxlength="10000">{{ old('notes', $quote?->notes) }}</textarea>
            </x-form-group>
            <div class="modal-ft u-section-sep commercial-actions">
                <a href="{{ $quote ? route('quotes.show', $quote) : route('clients.show', $client) }}" class="btn btn-g">Annulla</a>
                <button class="btn btn-p" type="submit">Salva bozza</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>
