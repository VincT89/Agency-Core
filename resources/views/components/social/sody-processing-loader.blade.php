@props([
    'active' => false,
])

<template x-teleport="body">
    <div
        wire:ignore
        x-cloak
        class="cmp-sody-loader"
        :class="{
            'is-visible': visible,
            'is-delayed': mode === 'delayed',
            'is-error': mode === 'error',
            'is-success': mode === 'success'
        }"
        :aria-hidden="visible ? 'false' : 'true'"
        x-data="{
            visible: {{ $active ? 'true' : 'false' }},
            mode: {{ $active ? "'working'" : "'idle'" }},
            currentMessage: 0,
            messageTimer: null,
            delayTimer: null,
            closeTimer: null,
            cleanupPageHandler: null,
            messages: [
                'Sto analizzando le informazioni del post.',
                'Sto preparando il contenuto.',
                'Sto adattando testo e immagini ai social.',
                'Sto completando gli ultimi controlli.'
            ],
            errorMessage: 'La richiesta non è partita. Controlla i campi evidenziati e riprova.',

            init() {
                this.cleanupPageHandler = () => this.unlockPage();

                document.addEventListener('livewire:navigating', this.cleanupPageHandler);
                window.addEventListener('pagehide', this.cleanupPageHandler);

                this.messageTimer = window.setInterval(() => {
                    if (this.visible && this.mode === 'working') {
                        this.currentMessage = (this.currentMessage + 1) % this.messages.length;
                    }
                }, 3200);

                if (this.visible) {
                    this.open();
                }
            },

            destroy() {
                window.clearInterval(this.messageTimer);
                window.clearTimeout(this.delayTimer);
                window.clearTimeout(this.closeTimer);

                if (this.cleanupPageHandler) {
                    document.removeEventListener('livewire:navigating', this.cleanupPageHandler);
                    window.removeEventListener('pagehide', this.cleanupPageHandler);
                }

                this.unlockPage();
            },

            start() {
                this.mode = 'working';
                this.currentMessage = 0;
                this.errorMessage = 'La richiesta non è partita. Controlla i campi evidenziati e riprova.';
                this.open();
            },

            open() {
                window.clearTimeout(this.closeTimer);
                window.clearTimeout(this.delayTimer);
                this.visible = true;
                this.lockPage();

                if (this.mode === 'working') {
                    this.delayTimer = window.setTimeout(() => this.markDelayed(), 30000);
                }

                this.$nextTick(() => this.$refs.panel?.focus({ preventScroll: true }));
            },

            markDelayed() {
                if (this.visible && this.mode === 'working') {
                    this.mode = 'delayed';
                }
            },

            fail(message) {
                window.clearTimeout(this.delayTimer);
                this.errorMessage = message || 'La richiesta non è partita. Riprova tra poco.';
                this.mode = 'error';
                this.open();
            },

            complete() {
                window.clearTimeout(this.delayTimer);

                if (!this.visible) {
                    this.unlockPage();
                    return;
                }

                this.mode = 'success';
                this.closeTimer = window.setTimeout(() => this.dismiss(), 900);
            },

            dismiss() {
                window.clearTimeout(this.delayTimer);
                window.clearTimeout(this.closeTimer);
                this.visible = false;
                this.unlockPage();
            },

            lockPage() {
                document.documentElement.classList.add('sody-loader-active');
                document.body.classList.add('sody-loader-active');
            },

            unlockPage() {
                document.documentElement.classList.remove('sody-loader-active');
                document.body.classList.remove('sody-loader-active');
            }
        }"
        x-on:sody-processing-started.window="start()"
        x-on:sody-processing-delayed.window="markDelayed()"
        x-on:sody-processing-failed.window="fail($event.detail?.message)"
        x-on:sody-processing-completed.window="complete()"
        x-on:keydown.escape.window="if (mode === 'delayed' || mode === 'error') dismiss()"
    >
        <section
            x-ref="panel"
            class="cmp-sody-loader-card"
            role="dialog"
            aria-modal="true"
            aria-labelledby="sody-loader-title"
            aria-describedby="sody-loader-description"
            tabindex="-1"
        >
            <img src="{{ asset('images/logo.png') }}" alt="Sodano Consulting" class="cmp-sody-loader-logo">

            <div class="cmp-sody-loader-indicator" aria-hidden="true">
                <span></span>
            </div>

            <div class="cmp-sody-loader-copy" aria-live="polite" aria-atomic="true">
                <p class="cmp-sody-loader-eyebrow" x-show="mode === 'working'">Elaborazione in corso</p>
                <p class="cmp-sody-loader-eyebrow" x-show="mode === 'delayed'">Elaborazione ancora in corso</p>
                <p class="cmp-sody-loader-eyebrow" x-show="mode === 'error'">Richiesta non completata</p>
                <p class="cmp-sody-loader-eyebrow" x-show="mode === 'success'">Elaborazione completata</p>

                <h2 id="sody-loader-title" class="cmp-sody-loader-title">
                    <span x-show="mode === 'working'">Sody sta preparando il contenuto</span>
                    <span x-show="mode === 'delayed'">Serve ancora un po' di tempo</span>
                    <span x-show="mode === 'error'">Sody non è stata avviata</span>
                    <span x-show="mode === 'success'">Contenuto pronto</span>
                </h2>

                <p id="sody-loader-description" class="cmp-sody-loader-description">
                    <span x-show="mode === 'working'" x-text="messages[currentMessage]"></span>
                    <span x-show="mode === 'delayed'">
                        Puoi chiudere questo pannello e continuare a lavorare. Sody proseguirà in automatico e il post si aggiornerà quando sarà pronto.
                    </span>
                    <span x-show="mode === 'error'" x-text="errorMessage"></span>
                    <span x-show="mode === 'success'">Il post è stato aggiornato.</span>
                </p>
            </div>

            <div class="cmp-sody-loader-progress" x-show="mode === 'working'" aria-hidden="true">
                <span></span>
            </div>

            <button
                type="button"
                class="btn btn-s cmp-sody-loader-dismiss"
                x-show="mode === 'delayed'"
                x-on:click="dismiss()"
            >
                Chiudi questo pannello
            </button>

            <button
                type="button"
                class="btn btn-p cmp-sody-loader-dismiss"
                x-show="mode === 'error'"
                x-on:click="dismiss()"
            >
                Torna al post
            </button>
        </section>
    </div>
</template>
