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
<div>
    @if(in_array($post->status->value, ['pending_n8n', 'submitted_to_n8n', 'regenerating']))
        <div wire:poll.2s="checkRegenerationStatus" style="display: none;"></div>
    @endif
    
    <template x-teleport="body">
        <div
            wire:ignore
            class="cmp-regeneration-loader"
            :class="{ 'is-visible': sodyLoaderVisible }"
            x-data="{
                sodyLoaderVisible: {{ in_array($post->status->value, ['pending_n8n', 'submitted_to_n8n', 'regenerating']) ? 'true' : 'false' }},
                showCancel: false,
                messages: [
                    'Sody sta ragionando...',
                    'Analisi del contesto in corso...',
                    'Generazione dei contenuti...',
                    'Ottimizzazione per i social...',
                    'Quasi pronto...'
                ],
                currentMsg: 0,

                showLoader() {
                    window.scrollTo({ top: 0, behavior: 'instant' });
                    this.sodyLoaderVisible = true;
                    this.showCancel = false;
                    document.documentElement.classList.add('sody-loader-active');
                    document.body.classList.add('sody-loader-active');
                },

                hideLoader() {
                    this.sodyLoaderVisible = false;
                    this.showCancel = false;
                    document.documentElement.classList.remove('sody-loader-active');
                    document.body.classList.remove('sody-loader-active');
                },

                init() {
                    const showHandler = () => this.showLoader();
                    const completeHandler = () => this.hideLoader();
                    const cancelBtnHandler = () => { this.showCancel = true; };

                    window.addEventListener('show-sody-loader', showHandler);
                    window.addEventListener('marketing-post-regeneration-completed', completeHandler);
                    window.addEventListener('marketing-post-regeneration-cancelled', completeHandler);
                    window.addEventListener('show-sody-cancel-button', cancelBtnHandler);

                    if (this.sodyLoaderVisible) {
                        this.showLoader();
                    }

                    let interval = setInterval(() => {
                        this.currentMsg = (this.currentMsg + 1) % this.messages.length;
                    }, 3500);

                    this.$cleanup(() => {
                        clearInterval(interval);
                        window.removeEventListener('show-sody-loader', showHandler);
                        window.removeEventListener('marketing-post-regeneration-completed', completeHandler);
                        window.removeEventListener('marketing-post-regeneration-cancelled', completeHandler);
                        window.removeEventListener('show-sody-cancel-button', cancelBtnHandler);
                        this.hideLoader();
                    });
                }
            }"
        >
            <div class="cmp-regeneration-loader-card">
                <div class="cmp-loader-logo-wrap">
                    <img src="{{ asset('images/logo.png') }}" alt="Sodano Logo" class="cmp-loader-logo">
                </div>

                <div class="mkt-loader-shimmer">
                    <div class="mkt-loader-shimmer-bar"></div>
                </div>

                <div class="u-mt-md u-text-center">
                    <strong class="mkt-loader-text" x-text="messages[currentMsg]">Sody sta ragionando...</strong>
                </div>

                <template x-if="showCancel">
                    <div>
                        <div class="u-mt-md u-text-orange u-text-center mkt-info-box">
                            L'operazione sta richiedendo più tempo del previsto. Puoi chiudere questa schermata, la generazione continuerà in background. Se Sody risponde, il post verrà aggiornato.
                        </div>
                        <div class="u-flex u-justify-center u-w-full">
                            <button
                                type="button"
                                class="btn btn-sec cmp-loader-cancel-btn"
                                @click="$wire.cancelRegeneration(); hideLoader();"
                            >
                                Interrompi
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>
    <div class="u-mb-lg">
        <a href="{{ route('marketing-campaigns.show', $campaign->id) }}"
            class="btn btn-g u-inline-flex-center u-gap-xs">
            <i data-lucide="arrow-left" class="u-icon-sm"></i> Torna al progetto
        </a>
    </div>

    <x-page-header eyebrow="Gestione Post">
        <x-slot:title>
            <strong>{{ $post->title ?: 'Senza Titolo' }}</strong>
        </x-slot:title>
        <x-slot:actions>
            <x-badge :status="$post->status->value" :label="$post->status->label()" />
            <x-delete-modal wireClick="deletePost" title="Elimina Post"
                message="Sei sicuro di voler eliminare questo post?">
                <button type="button" class="btn btn-d btn-sm u-inline-flex-center u-gap-xs">
                    <i data-lucide="trash-2" class="u-icon-sm"></i> Elimina
                </button>
            </x-delete-modal>
        </x-slot:actions>
    </x-page-header>

    <div class="cmp-post-detail-layout relative">



        {{-- Colonna Sinistra (2fr): Modulo di modifica e Versioni --}}
        <div
            class="u-flex-col u-gap-lg {{ in_array($post->status->value, ['pending_n8n', 'submitted_to_n8n', 'regenerating']) ? 'u-opacity-50 u-pointer-events-none' : '' }}">

            {{-- Modulo Modifica Post --}}
            <div class="panel u-overflow-hidden">
                <div class="lw-modal-hd">
                    <div class="cmp-panel-title">Dati Principali</div>
                </div>
                <div class="u-p-lg relative">

                    <form wire:submit.prevent class="form-stack">

                        {{-- Blocco 1: Piattaforme --}}
                        <div class="panel cmp-panel-pad">
                            <div class="cmp-section-label mb-2">Piattaforme di pubblicazione</div>
                            <div class="cmp-platform-options">
                                <label class="cmp-platform-option"
                                    x-bind:class="($wire.form.publishing_platforms || []).includes('instagram') ? 'active' : ''">
                                    <input type="checkbox" wire:model="form.publishing_platforms" value="instagram"
                                        class="hidden">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" class="cmp-platform-icon">
                                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                                        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                                    </svg>
                                    <span class="cmp-platform-label">Instagram</span>
                                </label>
                                <label class="cmp-platform-option"
                                    x-bind:class="($wire.form.publishing_platforms || []).includes('facebook') ? 'active' : ''">
                                    <input type="checkbox" wire:model="form.publishing_platforms" value="facebook"
                                        class="hidden">
                                    <svg viewBox="0 0 24 24" fill="currentColor" stroke="none"
                                        class="cmp-platform-icon">
                                        <path
                                            d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                    </svg>
                                    <span class="cmp-platform-label">Facebook</span>
                                </label>
                                <label class="cmp-platform-option"
                                    x-bind:class="($wire.form.publishing_platforms || []).includes('tiktok') ? 'active' : ''">
                                    <input type="checkbox" wire:model="form.publishing_platforms" value="tiktok"
                                        class="hidden">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" class="cmp-platform-icon">
                                        <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5" />
                                    </svg>
                                    <span class="cmp-platform-label">TikTok</span>
                                </label>
                            </div>
                            @error('form.publishing_platforms') <span class="form-err">{{ $message }}</span> @enderror
                        </div>

                        {{-- Box 1: Dati Editoriali --}}
                        <div class="panel cmp-panel-pad u-mb-md">
                            <div class="cmp-section-label mb-2">Dati Editoriali</div>
                            {{-- Blocco 3: Tipo + Stato + Data/Ora --}}
                            <div class="u-flex u-gap-lg">
                                <div class="form-g mb-0 u-flex-1">
                                    <label class="form-lbl">Tipo Contenuto <span class="mkt-text-red">*</span></label>
                                    <select class="form-sel" wire:model="form.content_type" required>
                                        <option value="post">Post</option>
                                        <option value="story">Story</option>
                                        <option value="reel">Reel</option>
                                    </select>
                                    @error('form.content_type') <span class="form-err">{{ $message }}</span> @enderror
                                </div>

                                <div class="form-g mb-0 u-flex-1">
                                    <label class="form-lbl">Stato <span class="mkt-text-red">*</span></label>
                                    <select class="form-sel" wire:model="form.status" required {{ $post->currentVersion ? 'disabled' : '' }}>
                                        <option value="draft">Bozza</option>
                                        <option value="pending_n8n">In Coda Sody</option>
                                        <option value="submitted_to_n8n">In Elaborazione Sody</option>
                                        <option value="generated">Generato</option>
                                        <option value="approved">Approvato</option>
                                        <option value="published">Pubblicato</option>
                                        <option value="cancelled">Annullato</option>
                                    </select>
                                    @error('form.status') <span class="form-err">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="u-flex u-gap-lg u-mt-md">
                                <div class="form-g mb-0 u-flex-1">
                                    <label class="form-lbl">Data Pubblicazione</label>
                                    <input type="date" class="form-in" wire:model="form.scheduled_date">
                                    @error('form.scheduled_date') <span class="form-err">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-g mb-0 u-flex-1">
                                    <label class="form-lbl">Ora Pubblicazione</label>
                                    <input type="time" class="form-in" wire:model="form.scheduled_time">
                                    @error('form.scheduled_time') <span class="form-err">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Box 2: Contenuto Sody --}}
                        <div class="panel cmp-panel-pad u-mb-md">
                            <div class="cmp-section-label mb-2">{{ $post->currentVersion ? 'Versione Sody Attiva' : 'Contenuto Sody' }}</div>
                            
                            {{-- Blocco 2: Titolo --}}
                            <div class="form-g u-border-b u-border-line u-pb-md">
                                <label class="form-lbl">Titolo Post</label>
                                <input type="text" class="form-in" wire:model="form.title"
                                    placeholder="Es: Lancio prodotto X">
                                @error('form.title') <span class="form-err">{{ $message }}</span> @enderror
                            </div>

                            @if($post->currentVersion && count($versionImages) > 0)
                                <div class="cmp-section-label mb-2 u-mt-md">Media Sody (Attivo)</div>
                                <div class="cmp-media-preview-box u-flex u-gap-sm u-flex-wrap u-mb-md">
                                    @foreach($versionImages as $idx => $vImg)
                                        <div class="cmp-media-preview-item">
                                            <img src="{{ $vImg }}" class="cmp-media-preview-img cmp-local-preview-img">
                                            <div class="u-text-truncate u-w-full u-text-meta u-mt-xs" title="Immagine Sody">Sody (v{{ $post->currentVersion->version_number }}) - {{ $idx + 1 }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Wrapper Media Sorgente --}}
                            @if($post->currentVersion && count($versionImages) > 0)
                                <div x-data="{ showOriginals: false }" class="u-mt-md u-border-t u-border-line u-pt-md">
                                    <div class="u-flex u-align-center u-gap-md u-mb-sm u-cursor-pointer" @click="showOriginals = !showOriginals">
                                        <div class="cmp-section-label mb-0">Media Originali (Sorgente)</div>
                                        <button type="button" class="btn btn-p btn-sm mkt-btn-s-pad u-ml-auto">
                                            <span x-text="showOriginals ? 'Nascondi' : 'Mostra'"></span>
                                        </button>
                                    </div>
                                    <div x-show="showOriginals" x-collapse x-cloak>
                            @endif

                            {{-- Blocco 4: Preview Media Esistenti --}}
                            @if(count($existing_media) > 0)
                                <div class="cmp-section-label mb-2 u-mt-md">Media Attuali (Salvati)</div>
                                <div class="cmp-media-preview-box u-flex u-gap-sm u-flex-wrap">
                                    @foreach($existing_media as $item)
                                        <div class="cmp-media-preview-item {{ $post->currentVersion ? 'u-opacity-70 hover:u-opacity-100 transition-opacity' : '' }}">
                                            @if(\Illuminate\Support\Str::startsWith($item['mime_type'], 'video/'))
                                                <video src="{{ $item['preview_url'] }}"
                                                    class="cmp-media-preview-video cmp-local-preview-img" controls></video>
                                            @else
                                                <img src="{{ $item['preview_url'] }}"
                                                    class="cmp-media-preview-img cmp-local-preview-img">
                                            @endif
                                            <div class="u-text-truncate u-w-full u-text-meta u-mt-xs"
                                                title="{{ $item['original_name'] }}">{{ $item['original_name'] }}</div>
                                            @if(!$post->currentVersion)
                                                <button type="button" wire:click="removeExistingMedia({{ $item['id'] }})"
                                                    class="btn btn-xs btn-sec u-w-full u-mt-xs">Rimuovi</button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Blocco 4.5: Preview Nuovi Media in Upload --}}
                            @if((is_array($media) && count($media) > 0) || !empty($selected_nextcloud_files))
                                <div class="cmp-section-label mb-2 u-mt-md">Nuovi Media da aggiungere</div>

                                {{-- Local Previews --}}
                                @if(is_array($media) && count($media) > 0)
                                        <div class="cmp-media-preview-box u-flex u-gap-sm u-flex-wrap u-mb-md" x-data="{
                                        draggingIndex: null,
                                        dropIndex: null,
                                        dragStart(index) { this.draggingIndex = index; },
                                        dragOver(event, index) { event.preventDefault(); this.dropIndex = index; },
                                        drop(index) {
                                            if (this.draggingIndex !== null && this.draggingIndex !== index) {
                                                $wire.reorderLocalMedia(this.draggingIndex, index);
                                            }
                                            this.draggingIndex = null;
                                            this.dropIndex = null;
                                        }
                                     }">
                                            @foreach($media as $index => $localFile)
                                                <div class="cmp-media-preview-item" wire:key="media-{{ $index }}" draggable="true"
                                                    @dragstart="dragStart({{ $index }})" @dragover="dragOver($event, {{ $index }})"
                                                    @drop="drop({{ $index }})" @dragend="draggingIndex = null; dropIndex = null"
                                                    :class="{ 'cmp-dragging': draggingIndex === {{ $index }}, 'cmp-drag-over': dropIndex === {{ $index }} && draggingIndex !== {{ $index }} }">
                                                    <div class="cmp-drag-handle"><i data-lucide="grip-vertical" class="u-icon-sm"></i>
                                                    </div>
                                                    @if(\Illuminate\Support\Str::startsWith($localFile->getMimeType(), 'video/'))
                                                        <video src="{{ $localFile->temporaryUrl() }}"
                                                            class="cmp-media-preview-video cmp-local-preview-img" controls></video>
                                                    @else
                                                        <img src="{{ $localFile->temporaryUrl() }}"
                                                            class="cmp-media-preview-img cmp-local-preview-img">
                                                    @endif
                                                    <div class="cmp-media-preview-label">Upload {{ $index + 1 }}</div>
                                                    <button type="button" wire:click="removeLocalMedia({{ $index }})"
                                                        class="btn btn-xs btn-sec u-w-full u-mt-xs">Rimuovi</button>
                                                </div>
                                            @endforeach
                                        </div>
                                @endif

                                {{-- Nextcloud Previews --}}
                                @if(!empty($selected_nextcloud_files))
                                        <div class="cmp-media-preview-box u-flex u-gap-sm u-flex-wrap u-mb-sm" x-data="{
                                        draggingIndex: null,
                                        dropIndex: null,
                                        dragStart(index) { this.draggingIndex = index; },
                                        dragOver(event, index) { event.preventDefault(); this.dropIndex = index; },
                                        drop(index) {
                                            if (this.draggingIndex !== null && this.draggingIndex !== index) {
                                                $wire.reorderNextcloudMedia(this.draggingIndex, index);
                                            }
                                            this.draggingIndex = null;
                                            this.dropIndex = null;
                                        }
                                     }">
                                            @foreach($selected_nextcloud_files as $index => $ncFile)
                                                <div class="cmp-nc-preview-item" wire:key="nc-{{ $index }}" draggable="true"
                                                    x-data="{ imageFailed: false }"
                                                    @dragstart="dragStart({{ $index }})" @dragover="dragOver($event, {{ $index }})"
                                                    @drop="drop({{ $index }})" @dragend="draggingIndex = null; dropIndex = null"
                                                    :class="{ 'cmp-dragging': draggingIndex === {{ $index }}, 'cmp-drag-over': dropIndex === {{ $index }} && draggingIndex !== {{ $index }} }">
                                                    <div class="cmp-drag-handle"><i data-lucide="grip-vertical" class="u-icon-sm"></i>
                                                    </div>
                                                    <img src="{{ route('nextcloud.preview', ['path' => $ncFile['path'], 'w' => 150, 'h' => 150]) }}"
                                                        class="cmp-nc-preview-img" :class="imageFailed ? 'u-hidden' : ''" x-on:error="imageFailed = true">
                                                    <div x-show="imageFailed" class="marketing-media-placeholder" x-cloak>
                                                        <i data-lucide="image-off" class="u-icon-md"></i>
                                                    </div>
                                                    <div class="u-text-truncate u-w-full u-text-meta u-mt-xs"
                                                        title="{{ $ncFile['name'] }}">{{ $index + 1 }}. {{ $ncFile['name'] }}</div>
                                                    <button type="button"
                                                        wire:click="removeNextcloudFile('{{ addslashes($ncFile['path']) }}')"
                                                        class="btn btn-xs btn-sec u-w-full u-mt-xs">Rimuovi</button>
                                                </div>
                                            @endforeach
                                        </div>
                                @endif
                            @endif

                            {{-- Blocco 4.6: Sorgente Nuovi Media --}}
                            <div class="u-mt-md u-border-t u-border-line u-pt-md">
                                <div class="cmp-media-source-hd u-mt-md">
                                    <label class="form-lbl mb-0">Aggiungi Media</label>
                                    <div class="cmp-media-source-options">
                                        <label class="cmp-radio-label">
                                            <input type="radio" wire:model.live="form.media_source" value="local">
                                            Upload Locale
                                        </label>
                                        <label class="cmp-radio-label">
                                            <input type="radio" wire:model.live="form.media_source" value="nextcloud">
                                            Da Nextcloud
                                        </label>
                                    </div>
                                </div>

                                @if($form['media_source'] === 'local')
                                    <input type="file" wire:model="media" multiple class="form-in p-2 text-sm"
                                        accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm">
                                    <div wire:loading wire:target="media" class="text-xs text-blue-500 mt-1">Caricamento
                                        anteprima...</div>
                                    <div class="text-xs text-gray-500 mt-1 u-mb-md">Puoi selezionare più file (max 10 in
                                        totale).</div>
                                    @error('media') <span class="form-err">{{ $message }}</span> @enderror
                                    @error('media.*') <span class="form-err">{{ $message }}</span> @enderror
                                @else
                                    {{-- Nextcloud Section --}}
                                    <div class="form-g u-mb-md">
                                        <label class="form-lbl cmp-lbl-sm">Sfoglia Cartelle Nextcloud</label>
                                        <div class="cmp-nc-browse-group">
                                            <input type="text" wire:model="nextcloud_browse_path" class="form-in"
                                                placeholder="/" disabled>
                                            <div class="u-flex u-gap-xs">
                                                <button type="button" wire:click="openNextcloudPicker('photo')"
                                                    class="btn btn-sec">Esplora Foto</button>
                                            </div>
                                        </div>
                                        @error('form.nextcloud_path') <div class="form-err">{{ $message }}</div> @enderror

                                        @if($nextcloud_error)
                                            <div class="form-err">{{ $nextcloud_error }}</div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            @if($post->currentVersion && count($versionImages) > 0)
                                    </div> {{-- chiusura x-show="showOriginals" --}}
                                </div> {{-- chiusura wrapper x-data --}}
                            @endif

                                {{-- Blocco 4.7: Copy / Descrizione --}}
                                <div class="form-g mb-0 u-border-t u-border-line u-pt-md">
                                    <label class="form-lbl">Copy / Descrizione</label>
                                    <textarea class="form-ta" wire:model="form.description" rows="5"
                                        placeholder="Inserisci il testo del post..."></textarea>
                                    @error('form.description') <span class="form-err">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            {{-- Blocco 5: Identità Cliente per Sody --}}
                            <div class="panel cmp-panel-pad cmp-identity-panel u-mt-md" @if($post->currentVersion) x-data="{ showAiSettings: false }" @endif>
                                @if($post->currentVersion)
                                    <div class="u-flex u-align-center u-gap-md u-cursor-pointer" @click="showAiSettings = !showAiSettings">
                                        <div class="cmp-section-label mb-0">Briefing sorgente usato da Sody</div>
                                        <button type="button" class="btn btn-p btn-sm mkt-btn-s-pad u-ml-auto">
                                            <span x-text="showAiSettings ? 'Nascondi' : 'Mostra'"></span>
                                        </button>
                                    </div>
                                @endif
                                <div @if($post->currentVersion) x-show="showAiSettings" x-collapse x-cloak class="u-mt-md u-border-t u-border-line u-pt-md" @endif>
                                    <label class="cmp-ai-check-wrap">
                                        <input type="checkbox" wire:model.live="form.ai_analysis_enabled"
                                            class="cmp-ai-check-input">
                                        <div class="cmp-ai-check-content">
                                            <div class="cmp-ai-check-title">Richiedi Analisi Sody</div>
                                            <div class="cmp-ai-check-desc">Se abilitato, Sody analizzerà il media e genererà
                                                un copy se assente.</div>
                                        </div>
                                    </label>

                                    @if($form['ai_analysis_enabled'])
                                        <div class="cmp-identity-body">
                                            {{-- Riga logo --}}
                                            <div class="cmp-identity-row">
                                                <div class="cmp-identity-col-check">
                                                    <label class="cmp-check-label u-mb-sm">
                                                        <input type="checkbox" wire:model.live="include_client_logo"
                                                            class="u-cursor-pointer">
                                                        Includi logo cliente nel briefing
                                                    </label>
                                                    @if($campaign->client->logo_path)
                                                        <div x-show="$wire.include_client_logo" class="cmp-identity-logo-preview">
                                                            <span class="u-text-meta muted">Logo attuale:</span>
                                                            @if($runtime_logo && method_exists($runtime_logo, 'temporaryUrl'))
                                                                <img src="{{ $runtime_logo->temporaryUrl() }}" alt="Logo Caricato"
                                                                    class="cmp-identity-logo-img">
                                                            @else
                                                                <img src="{{ $campaign->client->logo_url }}" alt="Logo Cliente"
                                                                    class="cmp-identity-logo-img">
                                                            @endif
                                                        </div>
                                                    @else
                                                        <div x-show="$wire.include_client_logo">
                                                            <div class="u-text-meta u-text-orange u-mb-sm">Nessun logo presente.
                                                                Caricane uno.</div>
                                                            <input type="file" wire:model="runtime_logo" class="form-in cmp-file-sm"
                                                                accept="image/jpeg,image/png,image/webp">
                                                            @error('runtime_logo') <div class="form-err form-err-sm">{{ $message }}
                                                            </div> @enderror
                                                            <label class="cmp-save-label u-mt-sm">
                                                                <input type="checkbox" wire:model="save_runtime_logo_to_client"
                                                                    class="u-cursor-pointer">
                                                                Salva e imposta come logo ufficiale
                                                            </label>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Riga attività --}}
                                            <div class="cmp-identity-row">
                                                <div class="cmp-identity-col-check">
                                                    <label class="cmp-check-label u-mb-sm">
                                                        <input type="checkbox" wire:model.live="include_client_header"
                                                            class="u-cursor-pointer">
                                                        Includi descrizione attività nel briefing
                                                    </label>
                                                    @if($campaign->client->activity_description)
                                                        <div x-show="$wire.include_client_header" x-data="{ expActivity: false }">
                                                            <div class="u-text-meta muted u-mb-xs">Testo attuale:</div>
                                                            <div class="cmp-identity-activity-full custom-scrollbar"
                                                                :class="expActivity ? 'is-expanded' : ''">
                                                                {{ $campaign->client->activity_description }}
                                                            </div>
                                                            @if(strlen($campaign->client->activity_description) > 150)
                                                                <button type="button" @click="expActivity = !expActivity"
                                                                    class="btn-ghost-primary btn-xs u-mt-xs">Espandi/Comprimi</button>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <div x-show="$wire.include_client_header">
                                                            <div class="u-text-meta u-text-orange u-mb-sm">Nessuna descrizione
                                                                presente. Scrivine una.</div>
                                                            <textarea wire:model="runtime_activity_description"
                                                                class="form-ta cmp-ta-sm"
                                                                placeholder="Descrivi l'attività del cliente..."></textarea>
                                                            @error('runtime_activity_description') <div
                                                            class="form-err form-err-sm">{{ $message }}</div> @enderror
                                                            <label class="cmp-save-label u-mt-sm">
                                                                <input type="checkbox" wire:model="save_runtime_activity_to_client"
                                                                    class="u-cursor-pointer">
                                                                Salva e imposta come descrizione ufficiale
                                                            </label>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Versioni e Feedback --}}
                            @if($post->currentVersion)
                                <div class="panel cmp-version-box u-mt-md">
                                    <div class="cmp-version-hd">
                                        <h4 class="mkt-fw600-fs15-m0-flex-gap8">
                                            <i data-lucide="sparkles" class="mkt-icon-16-blue"></i>
                                            Azioni Sody & Feedback (v{{ $post->currentVersion->version_number }})
                                        </h4>

                                    </div>

                                    @if($post->comments->count() > 0)
                                        <div class="mkt-mb-24">
                                            <div class="mkt-feedback-title">Feedback Ricevuti</div>
                                            <div class="cmp-feedback-list custom-scrollbar">
                                                @foreach($post->comments as $comment)
                                                    <div class="cmp-feedback-item">
                                                        <div class="cmp-feedback-hd">
                                                            <span>
                                                                @if($comment->source === \App\Enums\Social\CommentSource::Client)
                                                                    <strong class="mkt-text-orange">[Cliente]</strong>
                                                                    <span class="cmp-client-badge">Risposta cliente</span>
                                                                @else
                                                                    <strong class="mkt-text-purple">[Team]
                                                                        {{ $comment->user->name ?? 'Sistema' }}</strong>
                                                                @endif
                                                            </span>
                                                            <span class="mkt-text-text3">{{ $comment->created_at->format('d/m H:i') }}</span>
                                                        </div>
                                                        <div class="cmp-feedback-body">{{ $comment->body }}</div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <div class="cmp-comment-form">
                                        <input type="text" wire:model="newInternalComment" class="form-in u-flex-1"
                                            placeholder="Aggiungi una nota o istruzioni per Sody...">
                                        <button type="button" wire:click="addInternalComment" class="btn btn-p btn-sm mkt-btn-s-pad">
                                            <i data-lucide="message-square" class="u-icon-sm"></i> Inserisci Nota
                                        </button>
                                    </div>

                                    <div class="mkt-flex-gap10-wrap-pt20-bt">

                                        @if($generatedReviewLink)
                                            <div class="cmp-review-link-box">
                                                <div class="mkt-green-fw600-fs14-mb8-flex-gap6">
                                                    <i data-lucide="check-circle" class="u-icon-md"></i> Inviato al Cliente
                                                </div>
                                                <div class="u-flex u-gap-sm" x-data="{ copied: false }">
                                                    <input type="text" value="{{ $generatedReviewLink }}" readonly
                                                        class="form-in mkt-review-input" id="review-link-{{ $post->id }}">
                                                    <button type="button"
                                                        @click="navigator.clipboard.writeText(document.getElementById('review-link-{{ $post->id }}').value); copied = true; setTimeout(() => copied = false, 2000)"
                                                        class="btn btn-s mkt-review-btn" :class="copied ? 'btn-green' : ''">
                                                        <span x-show="!copied">Copia Link</span>
                                                        <span x-show="copied" x-cloak><i data-lucide="check" class="u-icon-sm"></i>
                                                            Copiato!</span>
                                                    </button>
                                                </div>
                                            </div>
                                        @else
                                            @if(!in_array($post->status->value, ['pending_n8n', 'submitted_to_n8n', 'regenerating']))
                                                @if($post->canRegenerate())
                                                    <button type="button" x-on:click="window.dispatchEvent(new CustomEvent('show-sody-loader'))" wire:click="regeneratePost('full')" class="btn btn-p btn-sm u-flex-center u-gap-xs">
                                                        <i data-lucide="refresh-cw" class="u-icon-sm"></i> Rigenera Tutto
                                                    </button>
                                                    <button type="button" x-on:click="window.dispatchEvent(new CustomEvent('show-sody-loader'))" wire:click="regeneratePost('caption')"
                                                        class="btn btn-p btn-sm u-flex-center u-gap-xs">
                                                        <i data-lucide="type" class="u-icon-sm"></i> Rigenera Testo
                                                    </button>
                                                    <button type="button" x-on:click="window.dispatchEvent(new CustomEvent('show-sody-loader'))" wire:click="regeneratePost('image')" class="btn btn-p btn-sm u-flex-center u-gap-xs">
                                                        <i data-lucide="image" class="u-icon-sm"></i> Rigenera Immagine
                                                    </button>
                                                @endif

                                                <div class="u-flex-1"></div>

                                                @if(in_array($post->status->value, ['generated', 'ready_for_client', 'client_changes_requested']))
                                                    <button type="button" wire:click="sendToClient" class="btn btn-s btn-purple u-flex-center u-gap-xs">
                                                        <i data-lucide="send" class="u-icon-sm"></i> Invia al Cliente
                                                    </button>
                                                @endif

                                                @if(!in_array($post->status->value, ['approved', 'published', 'cancelled']))
                                                    <button type="button" wire:click="approvePost" class="btn btn-s btn-green u-flex-center u-gap-xs">
                                                        <i data-lucide="check" class="u-icon-sm"></i> Approva Definitivamente
                                                    </button>
                                                @endif
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div class="u-mt-lg u-flex u-gap-sm">
                                @if($form['ai_analysis_enabled'])
                                    <button type="button" wire:click="savePost" class="btn {{ $post->currentVersion ? 'btn-p' : 'btn-s' }}"
                                        wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="savePost">
                                            {{ $post->status->value !== 'draft' ? ($post->currentVersion ? 'Salva Versione Sody' : 'Salva Modifiche') : 'Salva Bozza' }}
                                        </span>
                                        <span wire:loading wire:target="savePost">Salvataggio...</span>
                                    </button>
                                    @if(!$post->currentVersion)
                                        <button type="button" x-on:click="window.dispatchEvent(new CustomEvent('show-sody-loader'))" wire:click="saveAndSubmitToN8n"
                                            class="btn btn-p u-flex-center u-gap-xs" wire:loading.attr="disabled">
                                            <i data-lucide="sparkles" class="u-icon-md"></i>
                                            <span wire:loading.remove wire:target="saveAndSubmitToN8n">
                                                {{ $post->status->value !== 'draft' ? 'Rigenera con Sody' : 'Salva e Invia a Sody' }}
                                            </span>
                                            <span wire:loading wire:target="saveAndSubmitToN8n">Invio in corso...</span>
                                        </button>
                                    @endif
                                @else
                                    <button type="button" wire:click="savePost" class="btn btn-p u-flex-center u-gap-xs"
                                        wire:loading.attr="disabled">
                                        <i data-lucide="save" class="u-icon-md"></i>
                                        <span wire:loading.remove wire:target="savePost">
                                            {{ $post->status->value !== 'draft' ? 'Salva Modifiche' : 'Salva Post' }}
                                        </span>
                                        <span wire:loading wire:target="savePost">Salvataggio...</span>
                                    </button>
                                @endif
                            </div>

                    </form>
                </div>
            </div>



        </div>

        {{-- Colonna Destra (1fr): Info Aggiuntive e Preview Sticky --}}
        <div class="cmp-post-right-col">

            <div class="panel u-mb-lg u-overflow-hidden">
                <div class="lw-modal-hd">
                    <div class="cmp-panel-title">Dettagli Operativi</div>
                </div>
                <div class="u-p-lg u-flex-col u-gap-md">
                    
                    <div class="u-flex-center u-gap-md">
                        <i data-lucide="folder" class="u-icon-lg u-text-muted"></i>
                        <div class="u-flex-col">
                            <div class="u-text-label">Progetto</div>
                            <a href="{{ route('marketing-campaigns.show', $campaign->id) }}" wire:navigate
                                class="u-text-accent-link u-text-strong">
                                {{ $campaign->name }}
                            </a>
                        </div>
                    </div>

                    <div class="u-flex-center u-gap-md">
                        <i data-lucide="briefcase" class="u-icon-lg u-text-muted"></i>
                        <div class="u-flex-col">
                            <div class="u-text-label">Cliente</div>
                            <div class="u-text-strong">{{ $campaign->client->name }}</div>
                        </div>
                    </div>

                    <div class="u-flex-center u-gap-md">
                        <i data-lucide="calendar" class="u-icon-lg u-text-muted"></i>
                        <div class="u-flex-col">
                            <div class="u-text-label">Creazione</div>
                            <div>{{ $post->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>

                    <div class="u-flex-center u-gap-md">
                        <i data-lucide="clock" class="u-icon-lg u-text-muted"></i>
                        <div class="u-flex-col">
                            <div class="u-text-label">Ultima Modifica</div>
                            <div>{{ $post->updated_at->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>

                    @if($post->n8n_request_id || $post->submitted_to_n8n_at || $post->n8n_completed_at || $post->n8n_error)
                        <div class="u-section-sep u-flex-col u-gap-md">
                            @if($post->n8n_request_id)
                                <div class="u-flex-center u-gap-md">
                                    <i data-lucide="cpu" class="u-icon-lg u-text-muted"></i>
                                    <div class="u-flex-col">
                                        <div class="u-text-label">ID Richiesta Sody</div>
                                        <div class="u-text-mono">{{ $post->n8n_request_id }}</div>
                                    </div>
                                </div>
                            @endif

                            @if($post->submitted_to_n8n_at)
                                <div class="u-flex-center u-gap-md">
                                    <i data-lucide="send" class="u-icon-lg u-text-muted"></i>
                                    <div class="u-flex-col">
                                        <div class="u-text-label">Inviato il</div>
                                        <div class="u-text-muted">{{ $post->submitted_to_n8n_at->format('d/m/Y H:i:s') }}</div>
                                    </div>
                                </div>
                            @endif

                            @if($post->n8n_completed_at)
                                <div class="u-flex-center u-gap-md">
                                    <i data-lucide="check-circle" class="u-icon-lg u-text-muted"></i>
                                    <div class="u-flex-col">
                                        <div class="u-text-label">Completato il</div>
                                        <div class="u-text-muted">{{ $post->n8n_completed_at->format('d/m/Y H:i:s') }}</div>
                                    </div>
                                </div>
                            @endif

                            @if($post->n8n_error)
                                <div class="u-flex-center u-gap-md">
                                    <i data-lucide="alert-triangle" class="u-icon-lg u-text-red"></i>
                                    <div class="u-flex-col">
                                        <div class="u-text-label u-text-red">Errore Sody</div>
                                        <div class="u-text-red">{{ $post->n8n_error }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- Sticky Instagram Preview --}}
            <div class="cmp-sticky-preview">
                <div class="panel u-overflow-hidden">
                    <div class="lw-modal-hd">
                        <div class="cmp-panel-title">Anteprima Post</div>
                    </div>
                    <div class="cmp-ig-preview">
                        <div class="cmp-ig-preview-hd">
                            <div class="cmp-ig-preview-avatar">
                                @if($runtime_logo && method_exists($runtime_logo, 'temporaryUrl'))
                                    <img src="{{ $runtime_logo->temporaryUrl() }}" alt="Logo Caricato"
                                        class="cmp-ig-preview-avatar-img">
                                @elseif($campaign->client->logo_url)
                                    <img src="{{ $campaign->client->logo_url }}" alt="{{ $campaign->client->name }}"
                                        class="cmp-ig-preview-avatar-img">
                                @endif
                            </div>
                            <div class="cmp-ig-preview-author">{{ $campaign->client->name }}</div>
                        </div>

                        <div class="cmp-ig-preview-media" @if((!$post->currentVersion && count($existing_media) > 1) || count($versionImages) > 1)
                        x-data="{ currentSlide: 0, slides: {{ $post->currentVersion ? count($versionImages) : min(count($existing_media), 10) }} }" @endif>
                            @if(count($versionImages) > 0)
                                @if(count($versionImages) == 1)
                                    <img src="{{ $versionImages[0] }}" alt="Preview Media">
                                @else
                                    <div class="cmp-carousel-inner" :data-slide="currentSlide">
                                        @foreach($versionImages as $index => $vImg)
                                            <div class="cmp-carousel-item">
                                                <img src="{{ $vImg }}" alt="Preview Media {{ $index + 1 }}">
                                            </div>
                                        @endforeach
                                    </div>
                                    <button type="button" class="cmp-carousel-prev" x-show="currentSlide > 0"
                                        @click="currentSlide--">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left u-icon-sm"><polyline points="15 18 9 12 15 6"></polyline></svg>
                                    </button>
                                    <button type="button" class="cmp-carousel-next" x-show="currentSlide < slides - 1"
                                        @click="currentSlide++">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right u-icon-sm"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                    </button>
                                    <div class="cmp-carousel-dots">
                                        <template x-for="i in slides">
                                            <span class="cmp-carousel-dot" :class="currentSlide === i - 1 ? 'active' : ''"
                                                @click="currentSlide = i - 1"></span>
                                        </template>
                                    </div>
                                @endif
                            @elseif(count($existing_media) > 0)
                                @if(count($existing_media) == 1)
                                    @php $firstMedia = $existing_media[0]; @endphp
                                    @if(\Illuminate\Support\Str::startsWith($firstMedia['mime_type'], 'video/'))
                                        <video src="{{ $firstMedia['preview_url'] }}" controls></video>
                                    @else
                                        <img src="{{ $firstMedia['preview_url'] }}" alt="Preview Media">
                                    @endif
                                @else
                                    <div class="cmp-carousel-inner" :data-slide="currentSlide">
                                        @foreach($existing_media as $index => $mediaItem)
                                            <div class="cmp-carousel-item">
                                                @if(\Illuminate\Support\Str::startsWith($mediaItem['mime_type'], 'video/'))
                                                    <video src="{{ $mediaItem['preview_url'] }}" controls></video>
                                                @else
                                                    <img src="{{ $mediaItem['preview_url'] }}" alt="Preview Media {{ $index + 1 }}">
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    <button type="button" class="cmp-carousel-prev" x-show="currentSlide > 0"
                                        @click="currentSlide--">
                                        <i data-lucide="chevron-left" class="u-icon-sm"></i>
                                    </button>
                                    <button type="button" class="cmp-carousel-next" x-show="currentSlide < slides - 1"
                                        @click="currentSlide++">
                                        <i data-lucide="chevron-right" class="u-icon-sm"></i>
                                    </button>
                                    <div class="cmp-carousel-dots">
                                        <template x-for="i in slides">
                                            <span class="cmp-carousel-dot" :class="currentSlide === i - 1 ? 'active' : ''"
                                                @click="currentSlide = i - 1"></span>
                                        </template>
                                    </div>
                                @endif
                            @else
                                <div class="cmp-ig-preview-placeholder">
                                    <i data-lucide="image" class="u-icon-lg u-text-muted"></i>
                                    <div class="u-mt-xs">Nessun media</div>
                                </div>
                            @endif
                        </div>

                        <div class="cmp-ig-preview-actions">
                            <i data-lucide="heart" class="u-icon-sm"></i>
                            <i data-lucide="message-circle" class="u-icon-sm"></i>
                            <i data-lucide="send" class="u-icon-sm"></i>
                        </div>

                        <div class="cmp-ig-preview-body">
                            <strong>{{ $campaign->client->name }}</strong>
                            <span
                                class="cmp-ig-preview-caption">{{ $form['description'] ?: 'Nessuna caption fornita...' }}</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        @if($showNextcloudPicker)
            <div class="nc-picker-overlay" role="dialog" aria-modal="true" aria-labelledby="picker-title"
                @keydown.escape.window="$wire.closeNextcloudPicker()">
                <div class="nc-picker">
                    <div class="nc-picker-header">
                        <div>
                            <h2 class="nc-picker-title" id="picker-title">
                                Seleziona {{ $nextcloud_media_kind === 'video' ? 'video' : 'foto' }} da Nextcloud
                            </h2>
                            <div class="nc-picker-path">
                                {{ $nextcloud_browse_path }}
                            </div>
                        </div>

                        <div class="u-flex u-gap-md u-align-center">
                            @if($nextcloud_browse_path !== '/' && $nextcloud_browse_path !== '')
                                <button type="button"
                                    wire:click="browseNextcloud(@js(Str::finish(dirname($nextcloud_browse_path), '/')))"
                                    class="btn btn-sec">
                                    <i data-lucide="arrow-left" class="u-icon-sm u-mr-xs"></i>
                                    Livello superiore
                                </button>
                            @endif

                            <button type="button" wire:click="closeNextcloudPicker" class="btn btn-sec">
                                Chiudi
                            </button>
                        </div>
                    </div>

                    @if($nextcloud_error)
                        <div class="form-err u-mb-md u-mx-md u-mt-md">
                            {{ $nextcloud_error }}
                        </div>
                    @endif

                    <div class="nc-picker-body">


                        @forelse($nextcloud_files as $ncFile)
                            @if($ncFile['is_dir'])
                                <button type="button" wire:click="browseNextcloud(@js($ncFile['path']))"
                                    class="nc-picker-card nc-picker-dir">
                                    <i data-lucide="folder" class="nc-picker-icon"></i>
                                    <span>{{ $ncFile['name'] }}</span>
                                </button>
                            @else
                                <div class="u-flex-col u-gap-xs">
                                    <button type="button" wire:click="toggleNextcloudFile(
                                                  @js($ncFile['path']),
                                                  @js($ncFile['name']),
                                                  @js($ncFile['size']),
                                                  @js($ncFile['content_type']),
                                                  @js($ncFile['file_id'])
                                              )"
                                        class="nc-picker-card {{ collect($pending_nextcloud_files)->contains('path', $ncFile['path']) ? 'is-selected' : '' }}">
                                        <div class="nc-picker-thumb nc-picker-thumb-large">
                                            @if($ncFile['is_image'] ?? false)
                                                <img src="{{ route('nextcloud.preview', ['path' => $ncFile['path'], 'w' => 600, 'h' => 600]) }}"
                                                    alt="{{ $ncFile['name'] }}" class="nc-picker-thumb-img" loading="lazy">
                                            @else
                                                <i data-lucide="{{ $nextcloud_media_kind === 'video' ? 'video' : 'image' }}"
                                                    class="nc-picker-icon"></i>
                                            @endif
                                            @if(collect($pending_nextcloud_files)->contains('path', $ncFile['path']))
                                                <div class="nc-picker-check"><i data-lucide="check" class="u-icon-sm"></i></div>
                                            @endif
                                        </div>

                                        <div class="nc-picker-file-name">
                                            {{ $ncFile['name'] }}
                                        </div>

                                        <div class="nc-picker-file-meta">
                                            {{ ($ncFile['size'] ?? 0) > 1048576 ? round(($ncFile['size'] ?? 0) / 1048576, 2) . ' MB' : round(($ncFile['size'] ?? 0) / 1024) . ' KB' }}
                                        </div>
                                    </button>

                                    <button type="button" wire:click.stop="openNextcloudPreview(@js($ncFile['path']))"
                                        class="btn btn-xs btn-sec u-w-full">
                                        Anteprima
                                    </button>
                                </div>
                            @endif
                        @empty
                            <div class="nc-picker-empty u-p-md">
                                Nessun file disponibile in questa cartella.
                            </div>
                        @endforelse
                    </div>

                    <div class="nc-picker-footer">
                        @if(!empty($pending_nextcloud_files))
                            <div class="nc-picker-selected">
                                Selezionati: <strong>{{ count($pending_nextcloud_files) }}</strong> file
                            </div>
                        @else
                            <div class="nc-picker-selected u-text-muted">
                                Nessun file selezionato.
                            </div>
                        @endif

                        <div class="u-flex u-gap-sm">
                            <button type="button" wire:click="closeNextcloudPicker" class="btn btn-sec">
                                Annulla
                            </button>

                            <button type="button" wire:click="confirmNextcloudSelection" class="btn btn-p">
                                Usa file selezionato
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @if($preview_nextcloud_file)
                <div class="nc-preview-overlay" role="dialog" aria-modal="true" aria-labelledby="preview-title"
                    @keydown.escape.window="$wire.closeNextcloudPreview()">
                    <div class="nc-preview-panel">
                        <button type="button" wire:click="closeNextcloudPreview" class="nc-preview-close">
                            Chiudi
                        </button>

                        <button type="button" wire:click="previewNextcloudPrevious" class="nc-preview-nav nc-preview-prev"
                            aria-label="Precedente">
                            ‹
                        </button>

                        <div class="nc-preview-image-wrap">
                            @if($preview_nextcloud_file['is_video'] ?? false)
                                <video src="{{ route('nextcloud.download', ['path' => $preview_nextcloud_file['path']]) }}" controls
                                    class="nc-preview-image"></video>
                            @else
                                <img src="{{ route('nextcloud.preview', ['path' => $preview_nextcloud_file['path'], 'w' => 800, 'h' => 800]) }}"
                                    alt="{{ $preview_nextcloud_file['name'] }}" class="nc-preview-image">
                            @endif
                        </div>

                        <button type="button" wire:click="previewNextcloudNext" class="nc-preview-nav nc-preview-next"
                            aria-label="Successiva">
                            ›
                        </button>

                        <div class="nc-preview-footer">
                            <div>
                                <div class="nc-preview-title" id="preview-title">
                                    {{ $preview_nextcloud_file['name'] }}
                                </div>
                                <div class="nc-preview-meta">
                                    {{ ($preview_nextcloud_file['size'] ?? 0) > 1048576 ? round(($preview_nextcloud_file['size'] ?? 0) / 1048576, 2) . ' MB' : round(($preview_nextcloud_file['size'] ?? 0) / 1024) . ' KB' }}
                                </div>
                            </div>

                            <div class="u-flex u-gap-sm">
                                <button type="button" wire:click="closeNextcloudPreview" class="btn-sec">
                                    Torna alla griglia
                                </button>

                                <button type="button" wire:click="toggleNextcloudFile(
                                          @js($preview_nextcloud_file['path']),
                                          @js($preview_nextcloud_file['name']),
                                          @js($preview_nextcloud_file['size']),
                                          @js($preview_nextcloud_file['content_type']),
                                          @js($preview_nextcloud_file['file_id'])
                                      )" class="btn btn-p">
                                    {{ collect($pending_nextcloud_files)->contains('path', $preview_nextcloud_file['path']) ? 'Deseleziona' : 'Seleziona' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif


    </div>