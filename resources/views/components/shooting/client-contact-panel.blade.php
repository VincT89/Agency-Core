@props([
    'shoot',
    'communication',
    'clientChannels',
])

@php
    $client = $communication['client'];
    $channelLabel = $shoot->client_confirmation_channel
        ? \App\Enums\Shooting\ShootClientContactChannel::tryFrom($shoot->client_confirmation_channel)?->label()
        : null;
@endphp

<div class="shooting-client-contact" x-data="{ copied: false }">
    <div class="shooting-contact-summary">
        <div>
            <div class="shooting-lbl-caps">Cliente</div>
            <div class="shooting-text-val-bold">{{ $client?->name ?? 'Cliente non disponibile' }}</div>
            @if($client?->reference_person)
                <div class="u-text-meta">{{ $client->reference_person }}</div>
            @endif
        </div>
        <div>
            <div class="shooting-lbl-caps">Contatti</div>
            <div class="shooting-text-val">{{ $client?->email ?: 'Email non disponibile' }}</div>
            <div class="u-text-meta">{{ $client?->phone ?: 'Telefono non disponibile' }}</div>
        </div>
        @if($shoot->selectedSlot)
            <div>
                <div class="shooting-lbl-caps">Data confermata dal fotografo</div>
                <div class="shooting-text-val-bold">
                    {{ $shoot->selectedSlot->date->format('d/m/Y') }}
                    · {{ substr($shoot->selectedSlot->starts_at, 0, 5) }}
                    - {{ substr($shoot->selectedSlot->ends_at, 0, 5) }}
                </div>
            </div>
        @endif
    </div>

    <div class="shooting-contact-message">
        <label class="form-lbl" for="shoot-client-message-{{ $shoot->id }}">Messaggio pronto</label>
        <textarea id="shoot-client-message-{{ $shoot->id }}"
                  x-ref="clientMessage"
                  class="form-in shooting-contact-textarea"
                  rows="8"
                  readonly>{{ $communication['message'] }}</textarea>
        <button type="button"
                class="btn btn-g btn-sm shooting-copy-btn"
                @click="navigator.clipboard.writeText($refs.clientMessage.value).then(() => { copied = true; setTimeout(() => copied = false, 1800) })">
            <i data-lucide="copy" class="u-icon-sm"></i>
            <span x-text="copied ? 'Messaggio copiato' : 'Copia messaggio'"></span>
        </button>
    </div>

    <div class="shooting-contact-actions">
        @if($communication['email_url'])
            <a href="{{ $communication['email_url'] }}" class="btn btn-g">
                <i data-lucide="mail" class="u-icon-sm"></i>
                Apri email
            </a>
        @endif
        @if($communication['whatsapp_url'])
            <a href="{{ $communication['whatsapp_url'] }}"
               target="_blank"
               rel="noopener noreferrer"
               class="btn btn-g">
                <i data-lucide="message-circle" class="u-icon-sm"></i>
                Apri WhatsApp
            </a>
        @endif
    </div>

    @if(! $shoot->client_notified_at)
        <div class="shooting-contact-register">
            <div class="form-g">
                <label class="form-lbl" for="client-channel-{{ $shoot->id }}">Canale utilizzato</label>
                <select id="client-channel-{{ $shoot->id }}"
                        wire:model="clientChannel"
                        class="form-sel @error('clientChannel') is-invalid @enderror">
                    @foreach($clientChannels as $channel)
                        <option value="{{ $channel->value }}">{{ $channel->label() }}</option>
                    @endforeach
                </select>
                @error('clientChannel')
                    <div class="shooting-err-msg">{{ $message }}</div>
                @enderror
            </div>
            <button type="button"
                    wire:click="markClientInformed"
                    wire:loading.attr="disabled"
                    wire:target="markClientInformed"
                    class="btn btn-p">
                Registra cliente informato
            </button>
        </div>
    @else
        <div class="shooting-contact-recorded">
            <div>
                <div class="shooting-lbl-caps">Comunicazione registrata</div>
                <div class="shooting-text-val-bold">
                    {{ $channelLabel ?? 'Canale registrato' }}
                    · {{ $shoot->client_notified_at->format('d/m/Y H:i') }}
                </div>
                @if($shoot->client_notification_recipient)
                    <div class="u-text-meta">{{ $shoot->client_notification_recipient }}</div>
                @endif
            </div>
            <div class="shooting-client-response-actions">
                <button type="button"
                        wire:click="confirmForClient"
                        wire:confirm="Confermi che il cliente ha accettato? Verranno creati calendario e task."
                        wire:loading.attr="disabled"
                        class="btn btn-success">
                    Cliente ha accettato
                </button>
                <button type="button"
                        wire:click="rejectForClient"
                        wire:confirm="Confermi che il cliente ha rifiutato? Potrai proporre nuove date."
                        wire:loading.attr="disabled"
                        class="btn btn-outline btn-outline-danger">
                    Cliente ha rifiutato
                </button>
            </div>
            @error('clientResponse')
                <div class="shooting-err-msg">{{ $message }}</div>
            @enderror
        </div>
    @endif
</div>
