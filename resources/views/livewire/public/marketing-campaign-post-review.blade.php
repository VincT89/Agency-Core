<div class="client-review-page">
    <div class="client-review-header">
        <h1>Revisione Post</h1>
        <p>Campagna: <strong style="color:var(--accent);">{{ $post->campaign->name }}</strong></p>
    </div>

    @if(session('success'))
        <div class="cr-card" style="max-width:600px; margin:0 auto; text-align:center; padding:48px 32px; border-top:4px solid var(--green);">
            <div style="display:flex; justify-content:center; margin-bottom:16px;">
                <i data-lucide="check-circle" style="width:48px; height:48px; color:var(--green);"></i>
            </div>
            <h3 style="font-size:24px; font-family:var(--serif); margin-bottom:8px; color:var(--text);">{{ session('success') }}</h3>
            <p style="color:var(--text2); font-family:var(--mono); font-size:13px;">Puoi chiudere questa finestra in modo sicuro. Il nostro team è stato avvisato e procederà con gli step successivi.</p>
        </div>
    @else
        <div class="client-review-layout">
            
            <div class="cr-card cr-content">
                <div class="cr-section-title">
                    <i data-lucide="image" style="width:16px; height:16px;"></i> Anteprima Creatività
                </div>
                
                @if($post->currentVersion?->image_url)
                    <div class="cr-media-wrap">
                        <img src="{{ $post->currentVersion->image_url }}" alt="Anteprima Post" class="cr-media">
                        <div class="cr-format-badge">
                            {{ $post->content_type->label() }}
                            <small>Formato</small>
                        </div>
                    </div>
                @endif
                
                <div class="cr-caption-block">
                    <div class="cr-label">Titolo Interno</div>
                    <div style="font-size:18px; font-family:var(--serif); color:var(--text); margin-bottom:24px;">{{ $post->currentVersion?->title ?: 'N/A' }}</div>

                    <div class="cr-label">Testo del Post (Caption)</div>
                    <div class="cr-caption">{{ $post->currentVersion?->caption ?: 'N/A' }}</div>
                </div>

                @if($post->currentVersion?->hashtags)
                    <div class="cr-caption-block">
                        <div class="cr-label">Hashtags</div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:8px;">
                            @foreach($post->currentVersion->hashtags as $hashtag)
                                <span style="background:var(--bg3); border:1px solid var(--line); color:var(--text2); font-family:var(--mono); font-size:12px; padding:4px 8px; border-radius:12px;">
                                    #{{ str_replace('#', '', $hashtag) }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="cr-card cr-actions">
                <div class="cr-section-title">
                    <i data-lucide="edit-3" style="width:16px; height:16px;"></i> Azioni e Feedback
                </div>

                <div class="cr-identity">
                    <label>Il tuo Nominativo</label>
                    <input type="text" wire:model="clientName" class="form-in" readonly style="background:var(--bg); color:var(--text2);">
                </div>

                <div class="cr-identity" style="margin-top:16px;">
                    <label>La tua Email</label>
                    <input type="email" wire:model="clientEmail" class="form-in" readonly style="background:var(--bg); color:var(--text2);">
                </div>

                <div class="cr-action-box cr-change">
                    <h3>Richiedi Modifiche</h3>
                    <p>Scrivi di seguito cosa vorresti modificare nel post prima di procedere alla pubblicazione.</p>
                    
                    <textarea wire:model="commentBody" rows="3" class="form-ta" placeholder="Es: Cambiamo il colore dello sfondo, accorciamo il testo..."></textarea>
                    @error('commentBody') <span class="form-err">{{ $message }}</span> @enderror
                    
                    <button wire:click="requestChanges" wire:loading.attr="disabled" class="btn btn-g cr-btn">
                        <span wire:loading.remove wire:target="requestChanges">Invia Richiesta di Modifica</span>
                        <span wire:loading wire:target="requestChanges">Invio in corso...</span>
                    </button>
                </div>

                <div class="cr-action-box cr-approve">
                    <h3>Approva Post</h3>
                    <p>Se il contenuto è pronto e perfetto, clicca il pulsante qui sotto per approvarlo definitivamente.</p>
                    
                    <button wire:click="approve" wire:loading.attr="disabled" class="btn btn-p cr-btn" style="background:var(--green); border-color:var(--green);">
                        <span wire:loading.remove wire:target="approve" style="display:flex; align-items:center; justify-content:center; gap:6px;">
                            <i data-lucide="check" style="width:16px; height:16px;"></i> Approva Definitivamente
                        </span>
                        <span wire:loading wire:target="approve">Approvazione...</span>
                    </button>
                </div>

            </div>

        </div>
    @endif
</div>

<script>
    document.addEventListener('livewire:initialized', () => {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => {
                queueMicrotask(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                })
            })
        });
    });
</script>
