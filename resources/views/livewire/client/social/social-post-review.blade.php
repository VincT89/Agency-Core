<div class="client-review-page">
    <header class="client-review-header">
        <h1>Revisione Post</h1>
        <p>Controlla il contenuto prima della pubblicazione. Puoi approvarlo o richiedere modifiche al team.</p>
    </header>

    <main class="client-review-layout">
        {{-- Colonna Sinistra: Contenuto --}}
        <section class="cr-card cr-content">
            <div class="cr-section-title">
                <i data-lucide="eye" width="16" height="16"></i>
                <span>Anteprima del post</span>
            </div>

            <div class="cr-media-wrap">
                @if($post->currentVersion?->preview_url)
                    <img
                        src="{{ $post->currentVersion->preview_url }}"
                        alt="Anteprima contenuto"
                        class="cr-media"
                    >
                @else
                    <div class="cr-media-placeholder" style="padding: 100px 20px; text-align: center; color: var(--text3); font-family: var(--mono); font-size: 13px;">
                        Nessuna anteprima multimediale disponibile
                    </div>
                @endif

                <div class="cr-format-badge">
                    1080 × 1350<br>
                    <small>Formato verticale 4:5</small>
                </div>
            </div>

            <div class="cr-caption-block">
                <div class="cr-label">Testo del post (Caption)</div>
                <div class="cr-caption">{{ $post->currentVersion?->caption ?? 'Nessun testo inserito.' }}</div>
            </div>
        </section>

        {{-- Colonna Destra: Azioni --}}
        <aside class="cr-card cr-actions">
            <div class="cr-identity">
                <div class="cr-section-title">
                    <i data-lucide="user-check" width="16" height="16" style="color: var(--blue);"></i>
                    <span>Conferma la tua identità</span>
                </div>

                <label>Il tuo nome e cognome</label>
                <input
                    type="text"
                    wire:model.defer="clientName"
                    class="form-in"
                    readonly
                    style="opacity: 0.7; cursor: not-allowed; pointer-events: none;"
                >
                @error('clientName') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
            </div>

            @if($tokenObj->used_at)
                <div style="margin-top: 32px; padding: 20px; border-radius: var(--r); background: rgba(62, 207, 142, 0.08); border: 1px solid rgba(62, 207, 142, 0.2);">
                    <h3 style="color: var(--green); margin: 0 0 8px 0; font-family: var(--serif); font-size: 20px;">Risposta registrata</h3>
                    <p style="margin: 0; font-size: 14px; color: var(--text2);">Questa pagina è ora in modalità sola lettura poiché hai già inviato una risposta.</p>
                </div>
            @elseif($isExpired)
                <div style="margin-top: 32px; padding: 20px; border-radius: var(--r); background: rgba(245, 75, 75, 0.08); border: 1px solid rgba(245, 75, 75, 0.2);">
                    <h3 style="color: var(--red); margin: 0 0 8px 0; font-family: var(--serif); font-size: 20px;">Link scaduto</h3>
                    <p style="margin: 0; font-size: 14px; color: var(--text2);">Questo link non è più valido o è stato revocato dal team marketing.</p>
                </div>
            @else
                <div class="cr-action-box cr-approve">
                    <h3>Approva per la pubblicazione</h3>
                    <p>Il contenuto verrà siglato e passerà direttamente al team per la pianificazione.</p>

                    <x-confirm-modal 
                        title="Approva Contenuto" 
                        message="Confermi di voler approvare definitivamente questo contenuto? Passerà al team per la pubblicazione." 
                        confirmText="Sì, Approva" 
                        confirmMethod="approve"
                        btnClass="btn btn-p cr-btn"
                        icon="check-circle"
                    >
                        <button type="button" class="btn btn-p cr-btn">
                            Approva e continua
                        </button>
                    </x-confirm-modal>
                </div>

                <div class="cr-action-box cr-change">
                    <h3>Richiedi modifiche</h3>
                    <p>Se qualcosa non va, scrivi qui le tue indicazioni per il team.</p>

                    @if(!$showChangesForm)
                        <button
                            type="button"
                            wire:click="$toggle('showChangesForm')"
                            class="btn btn-g cr-btn"
                            style="border: 1px solid var(--orange); color: var(--orange);"
                        >
                            <i data-lucide="edit-3" width="14" height="14" style="margin-right: 4px; display: inline-block; vertical-align: -2px;"></i> Richiedi modifiche
                        </button>
                    @endif

                    @if($showChangesForm)
                        <textarea
                            wire:model.defer="feedback"
                            class="form-ta"
                            style="min-height: 120px; margin-top: 16px;"
                            placeholder="Descrivi cosa vuoi modificare..."
                        ></textarea>
                        @error('feedback') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror

                        <button
                            wire:click="requestChanges"
                            wire:loading.attr="disabled"
                            wire:target="requestChanges"
                            class="btn btn-p cr-btn"
                            style="background: var(--orange); color: white; border-color: var(--orange); margin-top: 16px;"
                        >
                            Invia richiesta al team
                        </button>
                    @endif
                </div>
            @endif
        </aside>
    </main>
</div>
