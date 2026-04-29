<div>
<style>
    html, body.guest-body {
        overflow-y: auto !important;
        height: 100% !important;
        background: var(--bg);
        margin: 0;
        padding: 0;
    }
    
    .review-wrapper {
        max-width: 800px;
        margin: 0 auto;
        padding: 40px 20px;
        display: flex;
        flex-direction: column;
        gap: 30px;
    }
    
    .review-header {
        text-align: center;
        margin-bottom: 10px;
    }
    
    .review-header-title {
        font-family: var(--serif);
        font-size: 28px;
        color: var(--text);
        margin-bottom: 4px;
        line-height: 1.2;
    }
    
    .review-header-subtitle {
        font-size: 14px;
        color: var(--text2);
    }

    .review-card {
        background: var(--bg1);
        border: 1px solid var(--line);
        border-radius: 12px;
        overflow: hidden;
    }

    .plan-slot {
        display: flex;
        flex-direction: column;
        border-bottom: 1px solid var(--line);
    }
    
    .plan-slot:last-child {
        border-bottom: none;
    }

    .slot-header {
        background: var(--bg2);
        padding: 12px 20px;
        border-bottom: 1px solid var(--line);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .slot-body {
        display: flex;
        gap: 20px;
        padding: 20px;
    }

    .slot-media {
        width: 150px;
        flex-shrink: 0;
        background: #000;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    
    .slot-media img {
        width: 100%;
        height: auto;
        display: block;
    }

    .slot-caption {
        flex: 1;
        background: var(--bg2);
        padding: 16px;
        border-radius: 8px;
        font-size: 14px;
        line-height: 1.6;
        color: var(--text);
        white-space: pre-wrap;
    }

    .review-actions {
        padding: 24px;
        border-top: 1px solid var(--line);
        background: var(--bg1);
    }

    .btn-massive {
        padding: 14px 20px;
        font-size: 15px;
        font-weight: 600;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.2s;
        border: 1px solid transparent;
        width: 100%;
    }

    .btn-approve { background: var(--green); color: #fff; }
    .btn-approve:hover { opacity: 0.9; transform: translateY(-2px); }
    .btn-reject { background: transparent; border-color: var(--line2); color: var(--text); }
    .btn-reject:hover { background: var(--bg2); border-color: var(--line); }

    .btn-submit-changes { background: var(--orange); color: #fff; }

    @media (max-width: 600px) {
        .review-header-title { font-size: 24px; }
        .review-wrapper { padding: 20px 15px; }
        .btn-massive { padding: 12px 16px; font-size: 14px; }
        .slot-body { flex-direction: column; }
        .slot-media { width: 100%; }
    }
</style>

<div class="review-wrapper">
    
    <div class="review-header">
        <h1 class="review-header-title">Controlla il Piano Editoriale prima della pubblicazione</h1>
        <p class="review-header-subtitle">Puoi approvarlo oppure richiedere modifiche al team.</p>
    </div>

    @if(session('success'))
        <x-alert type="success">
            {{ session('success') }}
        </x-alert>
    @endif

    <div class="review-card">
        
        {{-- LISTA POST --}}
        <div>
            @foreach($plan->slots as $slot)
                <div class="plan-slot">
                    <div class="slot-header">
                        <strong style="color:var(--text); font-size: 14px;">{{ $slot->scheduled_date?->format('l, d F Y') }} - {{ \Carbon\Carbon::parse($slot->scheduled_time)->format('H:i') }}</strong>
                        <div style="display:flex; gap:5px;">
                            @foreach($slot->platforms ?? [] as $plat)
                                <span style="background:var(--bg3); padding:4px 8px; border-radius:4px; font-size:11px; text-transform:uppercase;">{{ $plat }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="slot-body">
                        @if($slot->socialPost && $slot->socialPost->currentVersion)
                            @if($slot->socialPost->currentVersion->image_path)
                                <div class="slot-media">
                                    <img src="{{ Storage::url($slot->socialPost->currentVersion->image_path) }}" alt="Anteprima Post">
                                </div>
                            @endif
                            <div class="slot-caption">
                                {{ $slot->socialPost->currentVersion->caption ?? 'Nessuna caption fornita.' }}
                            </div>
                        @else
                            <div style="color:var(--text3); font-style:italic; padding: 20px;">Contenuto in fase di elaborazione...</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- AZIONI O STATO FINALE --}}
        <div class="review-actions">
            
            @if($tokenObj && $tokenObj->used_at)
                <x-alert type="info" icon="check-circle" title="Risposta inviata">
                    Hai già inviato una risposta per questo piano editoriale. Questa pagina è ora in sola lettura.
                </x-alert>
            @elseif($isExpired)
                <x-alert type="error" icon="alert-triangle" title="Link non valido">
                    Questo link non è più valido. Contatta il team marketing per riceverne uno nuovo.
                </x-alert>
            @elseif($plan->status->value === 'sent_to_client')
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; color: var(--text2); margin-bottom: 8px; font-weight: 500;">Conferma il tuo nome per continuare</label>
                    <input type="text" wire:model="clientName" style="width: 100%; background: var(--bg); border: 1px solid var(--line2); color: var(--text); padding: 12px 16px; border-radius: 8px; font-size: 15px;" placeholder="Tuo Nome e Cognome" required>
                    @error('clientName') <span style="color: var(--red); font-size: 12px; margin-top: 4px; display: block;">{{ $message }}</span> @enderror
                </div>
                
                @if($showChangesForm)
                    <div style="margin-bottom: 20px; animation: fadeIn 0.3s ease;">
                        <label style="display: block; font-size: 12px; color: var(--text2); margin-bottom: 8px; font-weight: 500;">Cosa desideri modificare?</label>
                        <textarea wire:model="comment" style="width: 100%; background: var(--bg); border: 1px solid var(--line2); color: var(--text); padding: 12px 16px; border-radius: 8px; font-size: 14px; height: 100px; resize: vertical;" placeholder="Descrivi cosa vuoi modificare sui post del piano..."></textarea>
                        @error('comment') <span style="color: var(--red); font-size: 12px; margin-top: 4px; display: block;">{{ $message }}</span> @enderror
                        
                        <div style="display: flex; gap: 12px; margin-top: 16px;">
                            <button wire:click="$set('showChangesForm', false)" class="btn-massive btn-reject" style="flex: 1;">
                                Annulla
                            </button>
                            <button wire:click="requestChanges" wire:loading.attr="disabled" class="btn-massive btn-submit-changes" style="flex: 2;">
                                Invia richiesta modifiche
                            </button>
                        </div>
                    </div>
                @else
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <x-confirm-modal 
                            title="Approva Piano Editoriale" 
                            message="Confermi di voler approvare l'intero piano editoriale? I contenuti verranno programmati per la pubblicazione." 
                            confirmText="Sì, approva tutto" 
                            confirmMethod="approve" 
                            btnClass="btn-massive btn-approve" 
                            btnStyle="border: none;"
                            icon="check-circle" 
                            iconColor="var(--green)" 
                            iconBg="rgba(0, 200, 83, 0.1)">
                            <button type="button" class="btn-massive btn-approve">
                                Approva Piano Editoriale
                            </button>
                        </x-confirm-modal>
                        
                        <button wire:click="$set('showChangesForm', true)" class="btn-massive btn-reject">
                            Richiedi modifiche
                        </button>
                    </div>
                @endif
                
            @else
                <x-alert type="info" icon="info" title="Contenuto in lavorazione">
                    Questo piano non è attualmente in fase di revisione da parte del cliente.
                </x-alert>
            @endif

        </div>
    </div>
</div>
</div>
