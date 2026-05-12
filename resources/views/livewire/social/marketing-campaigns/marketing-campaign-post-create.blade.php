<div>
  <div class="u-mb-lg">
      <a href="{{ route('marketing-campaigns.show', $campaign->id) }}" wire:navigate class="btn btn-g u-inline-flex-center u-gap-xs">
          <i data-lucide="arrow-left" class="u-icon-sm"></i> Torna al progetto
      </a>
  </div>

  <x-page-header eyebrow="Nuovo Post">
    <x-slot:title>
      <strong>{{ $campaign->name }}</strong>
    </x-slot:title>
  </x-page-header>

  <div class="cmp-post-detail-layout relative">
    {{-- Colonna Sinistra (2fr): Modulo di modifica --}}
    <div class="u-flex-col u-gap-lg">
      <div class="panel u-overflow-hidden">
        <div class="lw-modal-hd">
          <div class="cmp-panel-title">Dati Principali</div>
        </div>
        <div class="u-p-lg relative">
          <form class="form-stack">
        
        {{-- Blocco 1: Piattaforme --}}
        <div class="panel cmp-panel-pad">
            <div class="cmp-section-label mb-2">Piattaforme di pubblicazione</div>
            <div class="cmp-platform-options">
                <label class="cmp-platform-option" x-bind:class="($wire.form.publishing_platforms || []).includes('instagram') ? 'active' : ''">
                    <input type="checkbox" wire:model="form.publishing_platforms" value="instagram" class="hidden">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="cmp-platform-icon">
                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                    </svg>
                    <span class="cmp-platform-label">Instagram</span>
                </label>
                <label class="cmp-platform-option" x-bind:class="($wire.form.publishing_platforms || []).includes('facebook') ? 'active' : ''">
                    <input type="checkbox" wire:model="form.publishing_platforms" value="facebook" class="hidden">
                    <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" class="cmp-platform-icon">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    <span class="cmp-platform-label">Facebook</span>
                </label>
                <label class="cmp-platform-option" x-bind:class="($wire.form.publishing_platforms || []).includes('tiktok') ? 'active' : ''">
                    <input type="checkbox" wire:model="form.publishing_platforms" value="tiktok" class="hidden">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="cmp-platform-icon">
                        <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>
                    </svg>
                    <span class="cmp-platform-label">TikTok</span>
                </label>
            </div>
            @error('form.publishing_platforms') <span class="form-err">{{ $message }}</span> @enderror
        </div>

        {{-- Box 1: Dati Principali --}}
        <div class="panel cmp-panel-pad u-mb-md">
            {{-- Blocco 2: Titolo --}}
            <div class="form-g">
              <label class="form-lbl">Titolo Post</label>
              <input type="text" class="form-in" wire:model="form.title" placeholder="Es: Lancio prodotto X">
              @error('form.title') <span class="form-err">{{ $message }}</span> @enderror
            </div>

            {{-- Blocco 3: Tipo Contenuto + Stato + Data/Ora --}}
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
                <select class="form-sel" wire:model="form.status" required>
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

        {{-- Box 2: Media e Copy --}}
        <div class="panel cmp-panel-pad u-mb-md">
            {{-- Blocco 4: Preview Media --}}
            @if ($media && count($media) > 0)
            <div class="cmp-media-preview-box u-flex u-gap-sm u-flex-wrap u-mb-md"
                 x-data="{
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
                @foreach($media as $index => $m)
                <div class="cmp-media-preview-item"
                     wire:key="media-{{ $index }}"
                     draggable="true"
                     @dragstart="dragStart({{ $index }})"
                     @dragover="dragOver($event, {{ $index }})"
                     @drop="drop({{ $index }})"
                     @dragend="draggingIndex = null; dropIndex = null"
                     :class="{ 'cmp-dragging': draggingIndex === {{ $index }}, 'cmp-drag-over': dropIndex === {{ $index }} && draggingIndex !== {{ $index }} }">
                    <div class="cmp-drag-handle"><i data-lucide="grip-vertical" class="u-icon-sm"></i></div>
                    <img src="{{ $m->temporaryUrl() }}" class="cmp-media-preview-img cmp-local-preview-img">
                    <div class="cmp-media-preview-label">Upload {{ $index + 1 }}</div>
                </div>
                @endforeach
            </div>
            @endif

            <div class="cmp-media-source-hd">
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
                <input type="file" wire:model="media" multiple class="form-in p-2 text-sm" accept="image/jpeg,image/png,image/webp">
                <div wire:loading wire:target="media" class="text-xs text-blue-500 mt-1">Caricamento anteprima...</div>
                <div class="text-xs text-gray-500 mt-1 u-mb-md">Puoi selezionare più file (max 10 in totale).</div>
                @error('media') <span class="form-err">{{ $message }}</span> @enderror
            @else
                {{-- Nextcloud Section --}}
                @if(!empty($selected_nextcloud_files))
                    <div class="cmp-media-preview-box u-flex u-gap-sm u-flex-wrap u-mb-sm"
                         x-data="{
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
                            <div class="cmp-nc-preview-item"
                                 wire:key="nc-{{ $index }}"
                                 draggable="true"
                                 @dragstart="dragStart({{ $index }})"
                                 @dragover="dragOver($event, {{ $index }})"
                                 @drop="drop({{ $index }})"
                                 @dragend="draggingIndex = null; dropIndex = null"
                                 :class="{ 'cmp-dragging': draggingIndex === {{ $index }}, 'cmp-drag-over': dropIndex === {{ $index }} && draggingIndex !== {{ $index }} }">
                                <div class="cmp-drag-handle"><i data-lucide="grip-vertical" class="u-icon-sm"></i></div>
                                <img src="{{ route('nextcloud.preview', ['path' => $ncFile['path'], 'w' => 150, 'h' => 150]) }}" class="cmp-nc-preview-img">
                                <div class="u-text-truncate u-w-full u-text-meta u-mt-xs" title="{{ $ncFile['name'] }}">{{ $index + 1 }}. {{ $ncFile['name'] }}</div>
                                <button type="button" wire:click="removeNextcloudFile('{{ addslashes($ncFile['path']) }}')" class="btn btn-xs btn-sec u-w-full u-mt-xs">Rimuovi</button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="form-g u-mb-md">
                    <label class="form-lbl cmp-lbl-sm">Sfoglia Cartelle Nextcloud</label>
                    <div class="cmp-nc-browse-group">
                        <input type="text" wire:model="nextcloud_browse_path" class="form-in" placeholder="/" disabled>
                        <div class="u-flex u-gap-xs">
                            <button type="button" wire:click="openNextcloudPicker('photo')" class="btn btn-sec">Esplora Foto</button>
                        </div>
                    </div>
                    @error('form.nextcloud_path') <div class="form-err">{{ $message }}</div> @enderror

                    @if($nextcloud_error)
                        <div class="form-err">{{ $nextcloud_error }}</div>
                    @endif
                </div>
            @endif

            <div class="form-g mb-0 u-border-t u-border-line u-pt-md">
              <label class="form-lbl">Copy / Descrizione</label>
              <textarea class="form-ta" wire:model.live="form.description" rows="5" placeholder="Inserisci il testo del post..."></textarea>
              @error('form.description') <span class="form-err">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Blocco 5: Identità Cliente per Sody --}}
        <div class="panel cmp-panel-pad cmp-identity-panel">
          <label class="cmp-ai-check-wrap">
            <input type="checkbox" wire:model.live="form.ai_analysis_enabled" class="cmp-ai-check-input">
            <div class="cmp-ai-check-content">
              <div class="cmp-ai-check-title">Richiedi Analisi Sody</div>
              <div class="cmp-ai-check-desc">Se abilitato, Sody analizzerà il media e genererà un copy se assente.</div>
            </div>
          </label>

          @if($form['ai_analysis_enabled'])
            <div class="cmp-identity-body">
                {{-- Riga logo --}}
                <div class="cmp-identity-row">
                    <div class="cmp-identity-col-check">
                        <label class="cmp-check-label u-mb-sm">
                            <input type="checkbox" wire:model.live="include_client_logo" class="u-cursor-pointer">
                            Includi logo cliente nel briefing
                        </label>
                        @if($campaign->client->logo_path)
                            <div x-show="$wire.include_client_logo" class="cmp-identity-logo-preview">
                                <span class="u-text-meta muted">Logo attuale:</span>
                                @if($runtime_logo && method_exists($runtime_logo, 'temporaryUrl'))
                                    <img src="{{ $runtime_logo->temporaryUrl() }}" alt="Logo Caricato" class="cmp-identity-logo-img">
                                @else
                                    <img src="{{ $campaign->client->logo_url }}" alt="Logo Cliente" class="cmp-identity-logo-img">
                                @endif
                            </div>
                        @else
                            <div x-show="$wire.include_client_logo">
                                <div class="u-text-meta u-text-orange u-mb-sm">Nessun logo presente. Caricane uno.</div>
                                <input type="file" wire:model="runtime_logo" class="form-in cmp-file-sm" accept="image/jpeg,image/png,image/webp">
                                @error('runtime_logo') <div class="form-err form-err-sm">{{ $message }}</div> @enderror
                                <label class="cmp-save-label u-mt-sm">
                                    <input type="checkbox" wire:model="save_runtime_logo_to_client" class="u-cursor-pointer">
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
                            <input type="checkbox" wire:model.live="include_client_header" class="u-cursor-pointer">
                            Includi descrizione attività nel briefing
                        </label>
                        @if($campaign->client->activity_description)
                            <div x-show="$wire.include_client_header" x-data="{ expActivity: false }">
                                <div class="u-text-meta muted u-mb-xs">Testo attuale:</div>
                                <div class="cmp-identity-activity-full custom-scrollbar" :class="expActivity ? 'is-expanded' : ''">
                                    {{ $campaign->client->activity_description }}
                                </div>
                                @if(strlen($campaign->client->activity_description) > 150)
                                    <button type="button" @click="expActivity = !expActivity" class="btn-ghost-primary btn-xs u-mt-xs">Espandi/Comprimi</button>
                                @endif
                            </div>
                        @else
                            <div x-show="$wire.include_client_header">
                                <div class="u-text-meta u-text-orange u-mb-sm">Nessuna descrizione presente. Scrivine una.</div>
                                <textarea wire:model="runtime_activity_description" class="form-ta cmp-ta-sm" placeholder="Descrivi l'attività del cliente..."></textarea>
                                @error('runtime_activity_description') <div class="form-err form-err-sm">{{ $message }}</div> @enderror
                                <label class="cmp-save-label u-mt-sm">
                                    <input type="checkbox" wire:model="save_runtime_activity_to_client" class="u-cursor-pointer">
                                    Salva e imposta come descrizione ufficiale
                                </label>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
          @endif
        </div>

          </form>
        </div>

        <div class="u-p-lg u-bg-gray-50 u-border-t u-border-line u-flex u-justify-between">
          <div></div>
          <div class="u-flex u-gap-sm">
            <a href="{{ route('marketing-campaigns.show', $campaign->id) }}" wire:navigate class="btn btn-s">Annulla</a>
            
            @if($form['ai_analysis_enabled'])
                <button type="button" wire:click="save" class="btn btn-s">
                  <span wire:loading.remove wire:target="save">Salva Bozza</span>
                  <span wire:loading wire:target="save">Salvataggio...</span>
                </button>
                <button type="button" wire:click="saveAndSubmitToN8n" class="btn btn-p u-flex-center u-gap-xs">
                  <i data-lucide="sparkles" class="u-icon-md"></i>
                  <span wire:loading.remove wire:target="saveAndSubmitToN8n">Salva e Invia a Sody</span>
                  <span wire:loading wire:target="saveAndSubmitToN8n">Invio in corso...</span>
                </button>
            @else
                <button type="button" wire:click="save" class="btn btn-p u-flex-center u-gap-xs">
                  <i data-lucide="save" class="u-icon-md"></i>
                  <span wire:loading.remove wire:target="save">Salva Post</span>
                  <span wire:loading wire:target="save">Salvataggio...</span>
                </button>
            @endif
          </div>
        </div>
      </div>
    </div>

    {{-- Colonna Destra (1fr): Preview Sticky --}}
    <div class="cmp-post-right-col">
        <div class="cmp-sticky-preview">
            <div class="panel u-overflow-hidden">
                <div class="lw-modal-hd">
                    <div class="cmp-panel-title">Anteprima Post</div>
                </div>
                <div class="cmp-ig-preview">
                    <div class="cmp-ig-preview-hd">
                        <div class="cmp-ig-preview-avatar">
                            @if($runtime_logo && method_exists($runtime_logo, 'temporaryUrl'))
                                <img src="{{ $runtime_logo->temporaryUrl() }}" alt="Logo Caricato" class="cmp-ig-preview-avatar-img">
                            @elseif($campaign->client->logo_url)
                                <img src="{{ $campaign->client->logo_url }}" alt="{{ $campaign->client->name }}" class="cmp-ig-preview-avatar-img">
                            @endif
                        </div>
                        <div class="cmp-ig-preview-author">{{ $campaign->client->name }}</div>
                    </div>

                    @php
                        $previewMedia = [];
                        if ($form['media_source'] === 'local' && is_array($media)) {
                            foreach($media as $m) {
                                if (!$m) continue;
                                $previewMedia[] = [
                                    'type' => \Illuminate\Support\Str::startsWith($m->getMimeType(), 'video/') ? 'video' : 'image',
                                    'url' => method_exists($m, 'temporaryUrl') ? $m->temporaryUrl() : ''
                                ];
                            }
                        } elseif ($form['media_source'] === 'nextcloud' && !empty($selected_nextcloud_files)) {
                            foreach($selected_nextcloud_files as $ncFile) {
                                $previewMedia[] = [
                                    'type' => ($ncFile['is_image'] ?? true) ? 'image' : 'video',
                                    'url' => route('nextcloud.preview', ['path' => $ncFile['path'], 'w' => 600, 'h' => 600])
                                ];
                            }
                        }
                    @endphp

                    <div class="cmp-ig-preview-media" @if(count($previewMedia) > 1) x-data="{ currentSlide: 0, slides: {{ min(count($previewMedia), 10) }} }" @endif>
                        @if(count($previewMedia) > 0)
                            @if(count($previewMedia) == 1)
                                @if($previewMedia[0]['type'] === 'video')
                                    <video src="{{ $previewMedia[0]['url'] }}" controls></video>
                                @else
                                    <img src="{{ $previewMedia[0]['url'] }}" alt="Preview Media">
                                @endif
                            @else
                                <div class="cmp-carousel-inner" :data-slide="currentSlide">
                                    @foreach($previewMedia as $index => $item)
                                        <div class="cmp-carousel-item">
                                            @if($item['type'] === 'video')
                                                <video src="{{ $item['url'] }}" controls></video>
                                            @else
                                                <img src="{{ $item['url'] }}" alt="Preview Media {{ $index + 1 }}">
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="cmp-carousel-prev" x-show="currentSlide > 0" @click="currentSlide--">
                                    <i data-lucide="chevron-left" class="u-icon-sm"></i>
                                </button>
                                <button type="button" class="cmp-carousel-next" x-show="currentSlide < slides - 1" @click="currentSlide++">
                                    <i data-lucide="chevron-right" class="u-icon-sm"></i>
                                </button>
                                <div class="cmp-carousel-dots">
                                    <template x-for="i in slides">
                                        <span class="cmp-carousel-dot" :class="currentSlide === i - 1 ? 'active' : ''" @click="currentSlide = i - 1"></span>
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
                        <span class="cmp-ig-preview-caption">{{ $form['description'] ?: 'Nessuna caption fornita...' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </div>

  @if($showNextcloudPicker)
      <div class="nc-picker-overlay">
          <div class="nc-picker">
              <div class="nc-picker-header">
                  <div>
                      <h2 class="nc-picker-title">
                          Seleziona {{ $nextcloud_media_kind === 'video' ? 'video' : 'foto' }} da Nextcloud
                      </h2>
                      <div class="nc-picker-path">
                          {{ $nextcloud_browse_path }}
                      </div>
                  </div>

                  <div class="u-flex u-gap-md u-align-center">
                      @if($nextcloud_browse_path !== '/' && $nextcloud_browse_path !== '')
                          <button
                              type="button"
                              wire:click="browseNextcloud(@js(Str::finish(dirname($nextcloud_browse_path), '/')))"
                              class="btn btn-sec"
                          >
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
                      @if(!$ncFile['is_dir'] && !($ncFile['is_image'] ?? false))
                          @continue
                      @endif

                      @if($ncFile['is_dir'])
                          <button
                              type="button"
                              wire:click="browseNextcloud(@js($ncFile['path']))"
                              class="nc-picker-card nc-picker-dir"
                          >
                              <i data-lucide="folder" class="nc-picker-icon"></i>
                              <span>{{ $ncFile['name'] }}</span>
                          </button>
                      @else
                          <div class="u-flex-col u-gap-xs">
                              <button
                                  type="button"
                                  wire:click="toggleNextcloudFile(
                                      @js($ncFile['path']),
                                      @js($ncFile['name']),
                                      @js($ncFile['size']),
                                      @js($ncFile['content_type']),
                                      @js($ncFile['file_id'])
                                  )"
                                  class="nc-picker-card {{ collect($pending_nextcloud_files)->contains('path', $ncFile['path']) ? 'is-selected' : '' }}"
                              >
                                  <div class="nc-picker-thumb nc-picker-thumb-large">
                                      @if($ncFile['is_image'] ?? false)
                                          <img
                                              src="{{ route('nextcloud.preview', ['path' => $ncFile['path'], 'w' => 600, 'h' => 600]) }}"
                                              alt="{{ $ncFile['name'] }}"
                                              class="nc-picker-thumb-img"
                                              loading="lazy"
                                          >
                                      @else
                                          <i data-lucide="{{ $nextcloud_media_kind === 'video' ? 'video' : 'image' }}" class="nc-picker-icon"></i>
                                      @endif
                                  </div>

                                  <div class="nc-picker-file-name">
                                      {{ $ncFile['name'] }}
                                  </div>

                                  <div class="nc-picker-file-meta">
                                      {{ round(($ncFile['size'] ?? 0) / 1024) }} KB
                                  </div>
                              </button>
                              
                              <button
                                  type="button"
                                  wire:click.stop="openNextcloudPreview(@js($ncFile['path']))"
                                  class="btn btn-xs btn-sec u-w-full"
                              >
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
                          Selezionati: <strong>{{ count($pending_nextcloud_files) }} file</strong>
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
                          Usa foto selezionate
                      </button>
                  </div>
              </div>
          </div>
      </div>

      @if($preview_nextcloud_file)
          <div class="nc-preview-overlay">
              <div class="nc-preview-panel">
                  <button
                      type="button"
                      wire:click="closeNextcloudPreview"
                      class="nc-preview-close"
                  >
                      Chiudi
                  </button>

                  <button
                      type="button"
                      wire:click="previewNextcloudPrevious"
                      class="nc-preview-nav nc-preview-prev"
                  >
                      ‹
                  </button>

                  <div class="nc-preview-image-wrap">
                      <img
                          src="{{ route('nextcloud.preview', ['path' => $preview_nextcloud_file['path'], 'w' => 800, 'h' => 800]) }}"
                          alt="{{ $preview_nextcloud_file['name'] }}"
                          class="nc-preview-image"
                      >
                  </div>

                  <button
                      type="button"
                      wire:click="previewNextcloudNext"
                      class="nc-preview-nav nc-preview-next"
                  >
                      ›
                  </button>

                  <div class="nc-preview-footer">
                      <div>
                          <div class="nc-preview-title">
                              {{ $preview_nextcloud_file['name'] }}
                          </div>
                          <div class="nc-preview-meta">
                              {{ round(($preview_nextcloud_file['size'] ?? 0) / 1024) }} KB
                          </div>
                      </div>

                      <div class="u-flex u-gap-sm">
                          <button
                              type="button"
                              wire:click="closeNextcloudPreview"
                              class="btn-sec"
                          >
                              Torna alla griglia
                          </button>
                      </div>
                  </div>
              </div>
          </div>
      @endif
  @endif
</div>
