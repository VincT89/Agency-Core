@php
    $versionImages = [];
    if ($post->currentVersion) {
        if (is_array($post->currentVersion->image_urls) && count($post->currentVersion->image_urls) > 0) {
            $versionImages = $post->currentVersion->image_urls;
        } elseif (! empty($post->currentVersion->image_url)) {
            $versionImages = [$post->currentVersion->image_url];
        }
    }
@endphp
<div class="client-review-page">
    <div class="client-review-header">
        <h1>Revisione Post</h1>
        <p>Campagna: <strong class="cr-page-accent">{{ $post->campaign->name }}</strong></p>
    </div>

    @if(session('success'))
        <div class="cr-card cr-success-card">
            <div class="cr-success-icon-wrap">
                <i data-lucide="check-circle" class="cr-success-icon"></i>
            </div>
            <h3 class="cr-success-title">{{ session('success') }}</h3>
            <p class="cr-success-desc">Puoi chiudere questa finestra in modo sicuro. Il nostro team è stato avvisato e procederà con gli step successivi.</p>
        </div>
    @else
        <div class="client-review-layout">
            
            <div class="cr-card cr-content">
                <div class="cr-section-title">
                    <i data-lucide="image" class="cr-icon-sm"></i> Anteprima Creatività
                </div>
                
                @if(count($versionImages) > 0)
                    <div class="cr-media-wrap" @if(count($versionImages) > 1) x-data="{ currentSlide: 0, slides: {{ count($versionImages) }} }" @endif>
                        @if(count($versionImages) == 1)
                            <img src="{{ $versionImages[0] }}" alt="Anteprima Post" class="cr-media">
                        @else
                            <div class="sody-media-grid">
                                <div class="sody-media-grid-inner" :style="`transform: translateX(-${currentSlide * 100}%);`">
                                    @foreach($versionImages as $index => $vImg)
                                        <div class="sody-media-grid-item">
                                            <img src="{{ $vImg }}" alt="Anteprima Post {{ $index + 1 }}" class="cr-media">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <button type="button" class="sody-carousel-btn prev" x-show="currentSlide > 0" @click="currentSlide--">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left"><polyline points="15 18 9 12 15 6"></polyline></svg>
                            </button>
                            <button type="button" class="sody-carousel-btn next" x-show="currentSlide < slides - 1" @click="currentSlide++">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </button>
                            <div class="sody-carousel-dots">
                                <template x-for="i in slides">
                                    <span class="sody-carousel-dot" :class="currentSlide === i - 1 ? 'active' : ''" @click="currentSlide = i - 1"></span>
                                </template>
                            </div>
                        @endif
                        <div class="cr-format-badge">
                            {{ $post->content_type->label() }}
                            <small>Formato</small>
                        </div>
                    </div>
                @endif
                
                <div class="cr-caption-block">
                    <div class="cr-label">Titolo Interno</div>
                    <div class="cr-caption-title">{{ $post->currentVersion?->title ?: 'N/A' }}</div>

                    <div class="cr-label">Testo del Post (Caption)</div>
                    <div class="cr-caption">{{ $post->currentVersion?->caption ?: 'N/A' }}</div>
                </div>

                @if($post->currentVersion?->hashtags)
                    <div class="cr-caption-block">
                        <div class="cr-label">Hashtags</div>
                        <div class="cr-hashtag-wrap">
                            @foreach($post->currentVersion->hashtags as $hashtag)
                                <span class="cr-hashtag">
                                    #{{ str_replace('#', '', $hashtag) }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="cr-card cr-actions">
                <div class="cr-section-title">
                    <i data-lucide="edit-3" class="cr-icon-sm"></i> Azioni e Feedback
                </div>

                <div class="cr-identity">
                    <label>Il tuo Nominativo</label>
                    <input type="text" wire:model="clientName" class="form-in cr-identity-input" readonly>
                </div>

                <div class="cr-identity cr-identity-wrap">
                    <label>La tua Email</label>
                    <input type="email" wire:model="clientEmail" class="form-in cr-identity-input" readonly>
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
                    
                    <button wire:click="approve" wire:loading.attr="disabled" class="btn btn-p cr-btn cr-btn-approve">
                        <span wire:loading.remove wire:target="approve" class="cr-btn-approve-inner">
                            <i data-lucide="check" class="cr-icon-sm"></i> Approva Definitivamente
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
