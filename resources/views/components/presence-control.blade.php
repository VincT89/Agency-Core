@php
    $presence = app(\App\Domain\Availability\Queries\PresenceSummary::class)->personal(auth()->user(), hash('sha256', session()->getId()));
@endphp
<section class="presence-control" aria-label="La tua disponibilità" x-data="presenceControl(@js($presence), @js(route('presence.state')), @js(route('presence.update')))" x-show="state.show || error" x-cloak>
    <div class="presence-control-info">
        <strong>La tua disponibilità</strong>
        <span class="presence-status" :data-state="connected ? state.state : 'disconnected'"><span class="presence-dot" aria-hidden="true"></span><span x-text="connected ? state.label : 'Collegamento da verificare'"></span></span>
        <span x-show="state.slot">Fascia <span x-text="state.slot"></span></span>
        <span x-show="state.open">Disponibilità: <span x-text="state.available"></span>. Oltre fascia: <span x-text="state.extra"></span>.</span>
    </div>
    <div class="presence-control-actions">
        <button type="button" class="btn btn-p btn-sm" x-show="state.can_start" :disabled="busy || !connected" @click="act('start')">Sono disponibile</button>
        <button type="button" class="btn btn-g btn-sm" x-show="state.open && state.owned && state.state === 'available'" :disabled="busy || !connected" @click="act('pause')">Pausa</button>
        <button type="button" class="btn btn-p btn-sm" x-show="state.open && state.owned && state.state === 'paused'" :disabled="busy || !connected" @click="act('resume')">Riprendi disponibilità</button>
        <button type="button" class="btn btn-p btn-sm" x-show="state.open && state.owned && state.state === 'awaiting_confirmation'" :disabled="busy || !connected" @click="act('extend')">Resto disponibile</button>
        <button type="button" class="btn btn-g btn-sm" x-show="state.open" :disabled="busy || !connected" @click="act('finish')">Termina disponibilità</button>
        <a href="{{ route('availability.index') }}" wire:navigate class="presence-details-link">Le mie disponibilità</a>
    </div>
    <p class="presence-control-message" x-show="state.state === 'awaiting_confirmation'">La fascia è terminata. Il tempo aggiuntivo parte dalla tua conferma.</p>
    <p class="presence-control-message" x-show="state.open && !state.owned">Presenza confermata in un’altra sessione. Puoi terminarla per confermarla qui.</p>
    <p class="presence-control-error" role="alert" x-show="error" x-text="error"></p>
</section>
