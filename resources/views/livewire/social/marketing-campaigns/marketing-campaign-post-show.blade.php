<div>
  <div class="u-mb-lg">
      <a href="{{ route('marketing-campaigns.show', $campaign->id) }}" wire:navigate class="btn btn-g u-inline-flex-center u-gap-xs">
          <i data-lucide="arrow-left" class="u-icon-sm"></i> Torna al progetto
      </a>
  </div>

  <x-page-header eyebrow="Gestione Post">
    <x-slot:title>
      <strong>{{ $post->title ?: 'Senza Titolo' }}</strong>
    </x-slot:title>
    <x-slot:actions>
        <x-badge :status="$post->status->value" :label="$post->status->label()" />
        <x-delete-modal wireClick="deletePost" title="Elimina Post" message="Sei sicuro di voler eliminare questo post?">
            <button type="button" class="btn btn-d btn-sm u-inline-flex-center u-gap-xs">
              <i data-lucide="trash-2" class="u-icon-sm"></i> Elimina
            </button>
        </x-delete-modal>
    </x-slot:actions>
  </x-page-header>

  <div class="cmp-post-detail-layout">
    
    {{-- Colonna Sinistra (2fr): Modulo di modifica e Versioni --}}
    <div class="u-flex-col u-gap-lg">
      
      {{-- Modulo Modifica Post --}}
      <div class="panel u-overflow-hidden">
        <div class="lw-modal-hd">
          <div class="cmp-panel-title">Dati Principali</div>
        </div>
        <div class="u-p-lg">
          <form class="form-stack">
            
            {{-- Blocco 1: Titolo --}}
            <div class="form-g mb-0">
              <label class="form-lbl">Titolo Post</label>
              <input type="text" class="form-in" wire:model="form.title" placeholder="Es: Lancio prodotto X">
              @error('form.title') <span class="form-err">{{ $message }}</span> @enderror
            </div>

            {{-- Blocco 2: Identità Cliente per AI --}}
            <div class="panel cmp-panel-pad cmp-identity-panel">
              <label class="cmp-ai-check-wrap">
                <input type="checkbox" wire:model.live="form.ai_analysis_enabled" class="cmp-ai-check-input">
                <div class="cmp-ai-check-content">
                  <div class="cmp-ai-check-title">Richiedi Analisi AI Sody</div>
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
                                    <img src="{{ $campaign->client->logo_url }}" alt="Logo Cliente" class="cmp-identity-logo-img">
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
                                    <div class="cmp-identity-activity-full custom-scrollbar" :style="expActivity ? 'max-height:none' : 'max-height:80px'">
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

            {{-- Blocco 3: Piattaforme --}}
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

            {{-- Blocco 4: Tipo + Stato --}}
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
                  <option value="pending_n8n">In Coda AI</option>
                  <option value="submitted_to_n8n">In Elaborazione AI</option>
                  <option value="generated">Generato</option>
                  <option value="approved">Approvato</option>
                  <option value="published">Pubblicato</option>
                  <option value="cancelled">Annullato</option>
                </select>
                @error('form.status') <span class="form-err">{{ $message }}</span> @enderror
              </div>
            </div>

            {{-- Blocco 5: Data + Ora --}}
            <div class="u-flex u-gap-lg">
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

            {{-- Blocco 6: Preview Media --}}
            @if($existing_media_url || $media)
            <div class="cmp-media-preview-box">
                @if($existing_media_url && !$media)
                    <div class="cmp-media-preview-item">
                        @if($post->media_mime && \Illuminate\Support\Str::startsWith($post->media_mime, 'video/'))
                            <video src="{{ $existing_media_url }}" class="cmp-media-preview-video" controls></video>
                        @else
                            <img src="{{ $existing_media_url }}" class="cmp-media-preview-img">
                        @endif
                        <div class="cmp-media-preview-label">Media Attuale</div>
                    </div>
                @endif

                @if($media)
                    <div class="cmp-media-preview-item">
                        @if(\Illuminate\Support\Str::startsWith($media->getMimeType(), 'video/'))
                            <video src="{{ $media->temporaryUrl() }}" class="cmp-media-preview-video" controls></video>
                        @else
                            <img src="{{ $media->temporaryUrl() }}" class="cmp-media-preview-img">
                        @endif
                        <div class="cmp-media-preview-label">Nuovo Upload in corso</div>
                    </div>
                @endif
            </div>
            @endif

            {{-- Blocco 7: Sorgente Media --}}
            <div class="panel cmp-panel-pad">
                <div class="cmp-media-source-hd">
                    <label class="form-lbl mb-0">Sorgente Media</label>
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
                    <input type="file" wire:model="media" class="form-in p-2 text-sm" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm">
                    <div wire:loading wire:target="media" class="text-xs text-blue-500 mt-1">Caricamento anteprima...</div>
                    @error('media') <span class="form-err">{{ $message }}</span> @enderror
                @else
                    {{-- Nextcloud Section --}}
                    @if($post->media_source === 'nextcloud' && !$selected_nextcloud_file && !$form['nextcloud_path'])
                        <div class="cmp-nc-current-media">
                            <span class="u-text-strong">Media Attuale:</span> Collegato a Nextcloud<br>
                            Path: <code>{{ $post->nextcloud_path }}</code>
                        </div>
                    @endif

                    @if($selected_nextcloud_file)
                        <div class="cmp-nc-file-selected">
                            <div class="cmp-nc-file-selected-text">
                                <i data-lucide="check-circle" class="u-icon-sm"></i>
                                File Selezionato: {{ $selected_nextcloud_file['name'] }}
                            </div>
                            <button type="button" wire:click="removeNextcloudFile" class="btn btn-s btn-xs">Rimuovi</button>
                        </div>
                    @endif

                    <div class="form-g mb-0 u-mt-md">
                        <label class="form-lbl cmp-lbl-sm">Sfoglia Cartelle Nextcloud</label>
                        <div class="cmp-nc-browse-group">
                            <input type="text" wire:model="nextcloud_browse_path" class="form-in" placeholder="/" disabled>
                            <div class="u-flex u-gap-xs">
                                <button type="button" wire:click="openNextcloudPicker('photo')" class="btn btn-sec">Esplora Foto</button>
                                <button type="button" wire:click="openNextcloudPicker('video')" class="btn btn-sec">Esplora Video</button>
                            </div>
                        </div>
                        @error('form.nextcloud_path') <div class="form-err u-mb-md">{{ $message }}</div> @enderror

                        @if($nextcloud_error)
                            <div class="form-err u-mb-md">{{ $nextcloud_error }}</div>
                        @endif


                    </div>
                @endif
            </div>

            {{-- Blocco 8: Copy / Descrizione --}}
            <div class="form-g mb-0">
              <label class="form-lbl">Copy / Descrizione</label>
              <textarea class="form-ta" wire:model="form.description" rows="5" placeholder="Inserisci il testo del post..."></textarea>
              @error('form.description') <span class="form-err">{{ $message }}</span> @enderror
            </div>

            <div class="u-mt-lg u-flex u-gap-sm">
                @if($form['ai_analysis_enabled'])
                    <button type="button" wire:click="save" class="btn btn-s" wire:loading.attr="disabled">
                      <span wire:loading.remove wire:target="save">
                        {{ $post->status->value !== 'draft' ? 'Aggiorna Dati' : 'Salva Bozza' }}
                      </span>
                      <span wire:loading wire:target="save">Salvataggio...</span>
                    </button>
                    <button type="button" wire:click="saveAndSubmitToN8n" class="btn btn-p u-flex-center u-gap-xs" wire:loading.attr="disabled">
                      <i data-lucide="sparkles" class="u-icon-md"></i>
                      <span wire:loading.remove wire:target="saveAndSubmitToN8n">
                        {{ $post->status->value !== 'draft' ? 'Rigenera con Sody' : 'Salva e Invia a Sody' }}
                      </span>
                      <span wire:loading wire:target="saveAndSubmitToN8n">Invio in corso...</span>
                    </button>
                @else
                    <button type="button" wire:click="save" class="btn btn-p u-flex-center u-gap-xs" wire:loading.attr="disabled">
                      <i data-lucide="save" class="u-icon-md"></i>
                      <span wire:loading.remove wire:target="save">
                        {{ $post->status->value !== 'draft' ? 'Aggiorna Post' : 'Salva Post' }}
                      </span>
                      <span wire:loading wire:target="save">Salvataggio...</span>
                    </button>
                @endif
            </div>

          </form>
        </div>
      </div>

      {{-- Versioni e Feedback --}}
      @if($post->currentVersion)
          <div class="panel cmp-version-box">
              <div class="cmp-version-hd">
                  <h4 class="mkt-fw600-fs15-m0-flex-gap8">
                      <i data-lucide="sparkles" class="mkt-icon-16-blue"></i>
                      Versione Generata (v{{ $post->currentVersion->version_number }})
                  </h4>
                  <span class="cmp-version-status-badge">
                      {{ $post->status->label() }}
                  </span>
              </div>

              <div class="cmp-version-content">
                  @if($post->currentVersion->image_url)
                      <div class="mkt-shrink-0">
                          <img src="{{ $post->currentVersion->image_url }}" class="cmp-version-img">
                      </div>
                  @endif
                  <div class="mkt-flex1-fs13-text2">
                      <strong class="mkt-text1-fs14">{{ $post->currentVersion->title }}</strong>
                      <div class="cmp-version-caption">{{ $post->currentVersion->caption }}</div>
                  </div>
              </div>

              @if($post->comments->count() > 0)
                  <div class="mkt-mb-24">
                      <div class="mkt-feedback-title">Feedback Ricevuti</div>
                      <div class="cmp-feedback-list custom-scrollbar">
                          @foreach($post->comments as $comment)
                              <div class="cmp-feedback-item">
                                  <div class="cmp-feedback-hd">
                                      <span>
                                          @if($comment->visibility->value === 'client')
                                              <strong class="mkt-text-orange">[Cliente] {{ $comment->client_name }}</strong>
                                          @else
                                              <strong class="mkt-text-purple">[Team] {{ $comment->user->name ?? 'Sistema' }}</strong>
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
                  <input type="text" wire:model="newInternalComment" class="form-in u-flex-1" placeholder="Aggiungi una nota o istruzioni per l'AI...">
                  <button type="button" wire:click="addInternalComment" class="btn btn-s mkt-btn-s-pad">
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
                              <input type="text" value="{{ $generatedReviewLink }}" readonly class="form-in mkt-review-input" id="review-link-{{ $post->id }}">
                              <button type="button" 
                                  @click="navigator.clipboard.writeText(document.getElementById('review-link-{{ $post->id }}').value); copied = true; setTimeout(() => copied = false, 2000)" 
                                  class="btn btn-s mkt-review-btn" 
                                  :class="copied ? 'btn-green' : ''">
                                  <span x-show="!copied">Copia Link</span>
                                  <span x-show="copied" x-cloak style="display: none;"><i data-lucide="check" class="u-icon-sm"></i> Copiato!</span>
                              </button>
                          </div>
                      </div>
                  @else
                      @if($post->canRegenerate())
                          <button type="button" wire:click="regeneratePost('full')" class="btn btn-s u-flex-center u-gap-xs">
                              <i data-lucide="refresh-cw" class="u-icon-sm"></i> Rigenera Tutto
                          </button>
                          <button type="button" wire:click="regeneratePost('caption')" class="btn btn-s u-flex-center u-gap-xs">
                              <i data-lucide="type" class="u-icon-sm"></i> Rigenera Testo
                          </button>
                          <button type="button" wire:click="regeneratePost('image')" class="btn btn-s u-flex-center u-gap-xs">
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
              </div>
          </div>
      @endif

    </div>

    {{-- Colonna Destra (1fr): Info Aggiuntive --}}
    <div class="cmp-post-info-panel">
      
      <div class="cmp-post-info-row">
        <div class="cmp-post-info-label">Progetto</div>
        <div class="cmp-post-info-value font-semibold">
          <a href="{{ route('marketing-campaigns.show', $campaign->id) }}" wire:navigate class="no-underline text-blue-600 hover:text-blue-800">
            {{ $campaign->name }}
          </a>
        </div>
      </div>
      
      <div class="cmp-post-info-row">
        <div class="cmp-post-info-label">Cliente</div>
        <div class="cmp-post-info-value">{{ $campaign->client->name }}</div>
      </div>

      <div class="cmp-post-info-row">
        <div class="cmp-post-info-label">Creazione</div>
        <div class="cmp-post-info-value">{{ $post->created_at->format('d/m/Y H:i') }}</div>
      </div>
      
      <div class="cmp-post-info-row">
        <div class="cmp-post-info-label">Ultima Modifica</div>
        <div class="cmp-post-info-value">{{ $post->updated_at->format('d/m/Y H:i') }}</div>
      </div>

      @if($post->n8n_request_id)
        <div class="cmp-post-info-row mt-4 pt-4 border-t border-[var(--line)]">
          <div class="cmp-post-info-label">ID richiesta Sody</div>
          <div class="cmp-post-info-value text-xs font-mono text-gray-500">{{ $post->n8n_request_id }}</div>
        </div>
      @endif
      
      @if($post->submitted_to_n8n_at)
        <div class="cmp-post-info-row">
          <div class="cmp-post-info-label">Inviato il</div>
          <div class="cmp-post-info-value text-xs text-gray-500">{{ $post->submitted_to_n8n_at->format('d/m/Y H:i:s') }}</div>
        </div>
      @endif

      @if($post->n8n_completed_at)
        <div class="cmp-post-info-row">
          <div class="cmp-post-info-label">Completato il</div>
          <div class="cmp-post-info-value text-xs text-gray-500">{{ $post->n8n_completed_at->format('d/m/Y H:i:s') }}</div>
        </div>
      @endif

      @if($post->n8n_error)
        <div class="cmp-post-info-row">
          <div class="cmp-post-info-label text-red-500">Errore elaborazione</div>
          <div class="cmp-post-info-value text-xs text-red-500">{{ $post->n8n_error }}</div>
        </div>
      @endif

  </div>

  @if($showNextcloudPicker)
      <div class="nc-picker-overlay" role="dialog" aria-modal="true" aria-labelledby="picker-title" @keydown.escape.window="$wire.closeNextcloudPicker()">
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
                          <button
                              type="button"
                              wire:click="openNextcloudPreview(@js($ncFile['path']))"
                              class="nc-picker-card {{ ($pending_nextcloud_file['path'] ?? null) === $ncFile['path'] ? 'is-selected' : '' }}"
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
                                  {{ ($ncFile['size'] ?? 0) > 1048576 ? round(($ncFile['size'] ?? 0) / 1048576, 2) . ' MB' : round(($ncFile['size'] ?? 0) / 1024) . ' KB' }}
                              </div>
                          </button>
                      @endif
                  @empty
                      <div class="nc-picker-empty u-p-md">
                          Nessun file disponibile in questa cartella.
                      </div>
                  @endforelse
              </div>

              <div class="nc-picker-footer">
                  @if($pending_nextcloud_file)
                      <div class="nc-picker-selected">
                          Selezionato: <strong>{{ $pending_nextcloud_file['name'] }}</strong>
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
          <div class="nc-preview-overlay" role="dialog" aria-modal="true" aria-labelledby="preview-title" @keydown.escape.window="$wire.closeNextcloudPreview()">
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
                      aria-label="Precedente"
                  >
                      ‹
                  </button>

                  <div class="nc-preview-image-wrap">
                      @if($preview_nextcloud_file['is_video'] ?? false)
                          <video src="{{ route('nextcloud.download', ['path' => $preview_nextcloud_file['path']]) }}" controls class="nc-preview-image"></video>
                      @else
                          <img
                              src="{{ route('nextcloud.preview', ['path' => $preview_nextcloud_file['path'], 'w' => 1400, 'h' => 1400]) }}"
                              alt="{{ $preview_nextcloud_file['name'] }}"
                              class="nc-preview-image"
                          >
                      @endif
                  </div>

                  <button
                      type="button"
                      wire:click="previewNextcloudNext"
                      class="nc-preview-nav nc-preview-next"
                      aria-label="Successiva"
                  >
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
                          <button
                              type="button"
                              wire:click="closeNextcloudPreview"
                              class="btn-sec"
                          >
                              Torna alla griglia
                          </button>

                          <button
                              type="button"
                              wire:click="confirmNextcloudSelection"
                              class="btn btn-p"
                          >
                              Usa questa foto
                          </button>
                      </div>
                  </div>
              </div>
          </div>
      @endif
  @endif
</div>
