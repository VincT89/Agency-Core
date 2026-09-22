@php
    $sourceQuote = $sourceQuote ?? null;
    $defaults = $quote ?? $sourceQuote;
    $initialItems = $defaults?->items->map->only(\App\Models\QuoteService::FIELDS)->all()
        ?? $ticket?->requestedServices->map(fn ($service) => ['name' => $service->name, 'description' => $service->description, 'quantity' => '1', 'unit_price' => ''])->all();
    $initialItems = old('items', $initialItems);
    $initialItems = collect(is_array($initialItems) ? $initialItems : [])->filter(fn ($row) => is_array($row))
        ->map(fn ($row) => collect(['name' => '', 'summary' => '', 'description' => '', 'delivery_summary' => '', 'delivery_terms' => '', 'quantity' => '1', 'unit_price' => ''])
            ->map(fn ($default, $field) => is_scalar($row[$field] ?? null) ? (string) $row[$field] : $default)->all())
        ->values()->all() ?: [['name' => '', 'description' => '', 'quantity' => '1', 'unit_price' => '']];
    $editorOptions = ['items' => $initialItems, 'introduction' => old('introduction', $defaults?->introduction),
        'instructions' => old('ai_instructions', $defaults?->ai_instructions), 'libraryUrl' => route('quote-services.index'),
        'aiUrl' => route('quotes.text-suggestion'), 'hasErrors' => $errors->any()];
@endphp
<x-app-layout :title="$quote ? 'Modifica bozza offerta' : 'Prepara offerta'">
    <x-page-header>
        <x-slot:title>{{ $quote ? 'Modifica bozza offerta' : 'Prepara offerta' }}</x-slot:title>
        <x-slot:actions><a href="{{ $quote ? route('quotes.show', $quote) : ($client ? route('clients.show', $client) : route('quotes.index')) }}" class="btn btn-g">Annulla</a></x-slot:actions>
    </x-page-header>
    <x-panel padded>
        @if($sourceQuote)
            <p class="quote-help">Stai preparando una nuova offerta da <strong>{{ $sourceQuote->title }}</strong>. Puoi cambiare cliente, servizi e condizioni prima di salvarla.</p>
        @endif
        @if($errors->any())<div role="alert" class="ca-error-text">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="POST" action="{{ $quote ? route('quotes.update', $quote) : route('quotes.store') }}" class="quote-editor" x-data="quoteEditor(@js($editorOptions))" @invalid.capture="revealInvalid($event.target)">
            @csrf
            @if($quote)
                @method('PUT')
            @elseif($ticket)
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="ticket_id" value="{{ $ticket->id }}">
            @endif
            <div class="quote-basics">
                @if($quote || $ticket)
                    <div class="form-g"><span class="form-lbl">Cliente</span><strong class="quote-client-name">{{ $client->name }}</strong></div>
                @else
                    <x-form-group label="Cliente" name="client_id" for="client_id_search" required>
                        <x-client-autocomplete name="client_id" :required="true" :extended="true" :value="$client?->id"
                            :text="$client ? $client->name . ($client->company_name ? ' - ' . $client->company_name : '') : null" />
                    </x-form-group>
                @endif
                <x-form-group label="Oggetto dell’offerta" name="title" required>
                    <input id="title" name="title" class="form-in" value="{{ old('title', $defaults?->title ?? $ticket?->title) }}" maxlength="255" required>
                </x-form-group>
            </div>
            <section aria-labelledby="quote-services-title" class="quote-services">
                <h2 id="quote-services-title">Servizi e importi</h2>
                @include('quotes.partials.service-library')
                <div role="status" class="quote-feedback" x-show="message" x-text="message" x-cloak></div>
                <div role="alert" class="ca-error-text quote-feedback" x-show="error" x-text="error" x-cloak></div>
                <template x-for="(item, index) in items" :key="item.key">
                    <div class="quote-service-row" :data-service-key="item.key">
                        <div class="quote-service-main">
                            <div class="form-g quote-service-name"><label :for="'item-name-' + index">Servizio</label>
                                <input :id="'item-name-' + index" :name="'items[' + index + '][name]'" x-model="item.name" class="form-in" maxlength="255" required></div>
                            <div class="form-g"><label :for="'item-quantity-' + index">Quantità</label>
                                <input :id="'item-quantity-' + index" :name="'items[' + index + '][quantity]'" x-model="item.quantity" class="form-in" type="number" min="0.01" max="9999.99" step="0.01" required></div>
                            <div class="form-g"><label :for="'item-price-' + index">Prezzo unitario (€)</label>
                                <input :id="'item-price-' + index" :name="'items[' + index + '][unit_price]'" x-model="item.unit_price" class="form-in" type="number" min="0" max="999999.99" step="0.01" required></div>
                            <div class="quote-line-total"><span>Importo</span><strong x-text="money(lineCents(item))"></strong></div>
                        </div>
                        <details class="quote-service-details" :open="item.expanded" @toggle="item.expanded = $el.open">
                            <summary>Descrizione e tempi<span class="quote-detail-excerpt" x-text="item.summary || item.description || 'Facoltativi'"></span></summary>
                            <div class="quote-detail-fields">
                                <div class="form-g"><label :for="'item-summary-' + index">Servizi inclusi nel riepilogo</label>
                                    <textarea :id="'item-summary-' + index" :name="'items[' + index + '][summary]'" x-model="item.summary" class="form-ta" rows="2" maxlength="1500"></textarea></div>
                                <div class="form-g"><label :for="'item-description-' + index">Descrizione dettagliata</label>
                                    <textarea :id="'item-description-' + index" :name="'items[' + index + '][description]'" x-model="item.description" class="form-ta" rows="3" maxlength="3000"></textarea></div>
                                <div class="quote-two-columns">
                                    <div class="form-g"><label :for="'item-delivery-summary-' + index">Tempi nel riepilogo</label>
                                        <input :id="'item-delivery-summary-' + index" :name="'items[' + index + '][delivery_summary]'" x-model="item.delivery_summary" class="form-in" maxlength="255"></div>
                                    <div class="form-g"><label :for="'item-delivery-' + index">Tempi e condizioni di consegna</label>
                                        <textarea :id="'item-delivery-' + index" :name="'items[' + index + '][delivery_terms]'" x-model="item.delivery_terms" class="form-ta" rows="2" maxlength="500"></textarea></div>
                                </div>
                                <div class="quote-row-actions">
                                    <button class="btn btn-g btn-sm" type="button" @click="move(index, -1)" :disabled="index === 0">Sposta su</button>
                                    <button class="btn btn-g btn-sm" type="button" @click="move(index, 1)" :disabled="index === items.length - 1">Sposta giù</button>
                                </div>
                            </div>
                        </details>
                        <div class="quote-row-actions">
                            <button class="btn btn-g btn-sm" type="button" @click="saveService(item)" :disabled="savingService !== null" x-text="savingService === item.key ? 'Salvataggio...' : 'Salva voce in libreria'"></button>
                            <button class="btn btn-g btn-sm" type="button" @click="duplicate(index)" :disabled="items.length >= 50">Duplica</button>
                            <button class="btn btn-g btn-sm" type="button" @click="remove(index)" :disabled="items.length === 1">Rimuovi</button>
                        </div>
                    </div>
                </template>
                <button type="button" class="btn btn-g quote-add-service" @click="add()" :disabled="items.length >= 50">Aggiungi servizio</button>
            </section>
            <details class="quote-options" @if($errors->any()) open @endif>
                <summary>Testi, pagamento e note</summary>
                <div class="quote-option-fields">
                    @include('quotes.partials.text-assistant')
                    <x-form-group label="Forma di pagamento" name="payment_terms">
                        <textarea id="payment_terms" name="payment_terms" class="form-ta" rows="2" maxlength="5000">{{ old('payment_terms', $defaults?->payment_terms) }}</textarea>
                    </x-form-group>
                    <x-form-group label="Condizioni e note dell’offerta" name="notes">
                        <textarea id="notes" name="notes" class="form-ta" rows="3" maxlength="10000">{{ old('notes', $defaults?->notes) }}</textarea>
                    </x-form-group>
                </div>
            </details>
            @include('quotes.partials.document-options')
            <div class="quote-savebar">
                <p class="quote-total">Totale servizi <strong x-text="total()"></strong></p>
                <div class="quote-save-actions">
                    <button class="btn btn-g" type="submit" :disabled="aiBusy || savingService !== null">Salva bozza</button>
                    <button class="btn btn-p" type="submit" name="after_save" value="preview" :disabled="aiBusy || savingService !== null">Salva e anteprima</button>
                </div>
            </div>
        </form>
    </x-panel>
</x-app-layout>
