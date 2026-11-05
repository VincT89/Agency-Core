@php
    $presence = app(\App\Domain\Availability\Queries\PresenceSummary::class)->personal(auth()->user(), hash('sha256', session()->getId()), session('presence.login_at'), (bool) session('presence.login_prompt', false), session('presence.prompt_answered_on'));
@endphp
<div x-data="presenceControl(@js($presence), @js(route('presence.state')), @js(route('presence.update')))">
<section class="presence-control" aria-label="La tua disponibilità" x-show="state.show || error" x-cloak>
    <div class="presence-control-info">
        <strong>La tua disponibilità</strong>
        <span class="presence-status" :data-state="connected ? state.state : 'disconnected'"><span class="presence-dot" aria-hidden="true"></span><span x-text="connected ? state.label : 'Collegamento da verificare'"></span></span>
        <span x-show="state.slot">Fascia <span x-text="state.slot"></span></span>
        <span x-show="state.open">Disponibilità: <span x-text="state.available"></span>. Oltre fascia: <span x-text="state.extra"></span>.</span>
    </div>
    <div class="presence-control-actions">
        <button type="button" class="btn btn-p btn-sm" x-show="state.can_start" :disabled="busy || !connected" @click="act('start')">Sono disponibile</button>
        <button type="button" class="btn btn-p btn-sm" x-show="state.can_start_early" :disabled="busy || !connected" @click="act('start_early')">Sono disponibile ora</button>
        <button type="button" class="btn btn-g btn-sm" x-show="state.open && state.owned && state.state === 'available'" :disabled="busy || !connected" @click="act('pause')">Pausa</button>
        <button type="button" class="btn btn-p btn-sm" x-show="state.open && state.owned && state.state === 'paused'" :disabled="busy || !connected" @click="act('resume')">Riprendi disponibilità</button>
        <button type="button" class="btn btn-p btn-sm" x-show="state.open && state.owned && state.state === 'awaiting_confirmation'" :disabled="busy || !connected" @click="act('extend')">Resto disponibile</button>
        <button type="button" class="btn btn-g btn-sm" x-show="state.open" :disabled="busy || !connected" @click="act('finish')">Termina disponibilità</button>
        <a href="{{ route('availability.index') }}" wire:navigate class="presence-details-link">Le mie disponibilità</a>
    </div>
    <p class="presence-control-message" x-show="state.state === 'awaiting_confirmation'">La fascia è terminata. Il tempo aggiuntivo parte dalla tua conferma.</p>
    <p class="presence-control-message" x-show="state.can_start_early">Confermando adesso, l’anticipo rispetto alla fascia prevista viene contato come oltre fascia.</p>
    <p class="presence-control-message" x-show="state.open && !state.owned">Presenza confermata in un’altra sessione. Puoi terminarla per confermarla qui.</p>
    <p class="presence-control-error" role="alert" x-show="error" x-text="error"></p>
</section>
<dialog class="presence-login-dialog" x-ref="loginPrompt" aria-labelledby="presence-login-title" aria-describedby="presence-login-description" @cancel.prevent="postpone()" @keydown.tab="trapLoginFocus($event)">
    <h2 id="presence-login-title">Sei disponibile?</h2>
    <div id="presence-login-description">
        <p x-show="state.accessed">Accesso registrato alle <strong x-text="state.accessed"></strong>.</p>
        <p x-show="state.can_start">Conferma per iniziare la disponibilità nella fascia <strong x-text="state.slot"></strong>.</p>
        <p x-show="state.can_start_early">Puoi iniziare ora: il tempo prima della fascia <strong x-text="state.slot"></strong> viene contato come oltre fascia.</p>
        <p x-show="!state.can_start && !state.can_start_early && !state.open">Non hai altre fasce previste per oggi. Puoi impostarle nelle tue disponibilità.</p>
        <p x-show="state.open">Hai già una presenza aperta. Puoi gestirla dal riquadro della tua disponibilità.</p>
    </div>
    <p class="presence-control-error" role="alert" x-show="error" x-text="error"></p>
    <div class="presence-login-actions">
        <button type="button" class="btn btn-p" x-show="state.can_start" :disabled="busy || !connected" @click="act('start')">Sono disponibile</button>
        <button type="button" class="btn btn-p" x-show="state.can_start_early" :disabled="busy || !connected" @click="act('start_early')">Sono disponibile ora</button>
        <button type="button" class="btn btn-g" x-show="!state.can_start && !state.can_start_early && !state.open" :disabled="busy || !connected" @click="openAvailability(@js(route('availability.index')))">Le mie disponibilità</button>
        <button type="button" class="btn btn-g" :disabled="busy" @click="postpone()">Non ancora</button>
    </div>
</dialog>
</div>
