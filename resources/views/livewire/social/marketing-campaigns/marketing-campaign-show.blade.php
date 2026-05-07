<div>
  <div class="u-mb-lg u-flex-end">
      <a href="{{ route('marketing-campaigns.index') }}" wire:navigate class="btn btn-g u-flex-center u-gap-xs">
          <i data-lucide="arrow-left" class="u-icon-sm"></i> Torna ai progetti
      </a>
  </div>

  <x-page-header eyebrow="Progetto Marketing">
    <x-slot:title>
      <div class="flex items-center gap-3">
        @if($campaign->client->logo_url)
          <img src="{{ $campaign->client->logo_url }}" class="w-8 h-8 rounded-full object-cover border border-[var(--line)]">
        @endif
        <div>
          <strong>{{ $campaign->name }}</strong>
          <div class="t-mono text-gray-500 text-xs">{{ $campaign->client->name }}</div>
        </div>
      </div>
    </x-slot:title>
    <x-slot name="actions">
      <div class="u-flex-center u-gap-lg">
          <x-badge :status="$campaign->status->value" :label="$campaign->status->label()" />
          <div class="u-flex u-gap-sm">
            <button wire:click="openCampaignModal" class="btn btn-g btn-sm">Modifica</button>
            <button wire:click="openExtendModal" class="btn btn-g btn-sm">Prolunga</button>
            @if(in_array($campaign->status->value, ['closed', 'paused']))
              <button wire:click="openRenewModal" class="btn btn-g btn-sm">Rinnova</button>
            @endif
            <button wire:click="openInvoiceModal" class="btn btn-g btn-sm">Fattura</button>
          </div>
      </div>
    </x-slot>
  </x-page-header>

  <div class="cmp-layout">
    
    {{-- Aside Box: Posts da Programmare --}}
    <div class="cmp-aside">
      <div class="panel cmp-aside-panel">
        <div class="lw-modal-hd">
          <div class="cmp-panel-title">Tutti i Post</div>
          <button type="button" class="btn btn-p cmp-aside-new-btn" wire:click="openPostModal()">
            <i data-lucide="plus" class="u-icon-sm"></i> Nuovo
          </button>
        </div>
        
        <div class="cmp-aside-body">
          <div class="cmp-post-list">
          @forelse($posts as $post)
            <div class="cmp-post-list-item group" wire:click="openPostModal({{ $post->id }})" wire:key="post-{{ $post->id }}">
                <div class="cmp-post-list-header">
                    <x-badge :status="$post->status->value" :label="$post->status->label()" />
                    <div class="cmp-post-list-date">
                        {{ $post->scheduled_date ? $post->scheduled_date->format('d/m/Y') : 'Da def.' }}
                    </div>
                </div>
                
                <div class="cmp-post-list-title">
                    {{ $post->title ?: 'Senza Titolo' }}
                </div>
                
                <div class="cmp-post-list-meta">
                    <div class="flex gap-2 items-center">
                        <span class="uppercase">{{ $post->content_type->label() }}</span>
                        @if($post->media_path || $post->nextcloud_path)
                            <span class="cmp-media-label"><i data-lucide="image" class="cmp-media-icon"></i></span>
                        @endif
                    </div>
                    
                    <div class="cmp-post-list-actions">
                        <x-delete-modal wireClick="deletePost({{ $post->id }})" title="Elimina Post" message="Vuoi davvero eliminare questo post?">
                            <button type="button" class="text-red-500 hover:text-red-700">
                                <i data-lucide="trash-2" class="u-icon-sm"></i>
                            </button>
                        </x-delete-modal>
                    </div>
                </div>
            </div>
          @empty
            <div class="cmp-aside-empty">
              <i data-lucide="inbox" class="cmp-aside-empty-icon"></i>
              <p class="u-text-muted">Nessun post presente.</p>
              <p class="u-text-meta u-mt-xs">Clicca su "Nuovo" per iniziare.</p>
            </div>
          @endforelse
          </div>
        </div>
      </div>
    </div>

    {{-- Main Box: Campaign Info & Calendar --}}
    <div class="cmp-main">
      
      <div class="panel u-overflow-hidden">
        <div class="lw-modal-hd">
          <div class="cmp-panel-title">Dettagli Campagna</div>
        </div>
        <div class="u-p-lg">
          @if($campaign->description)
            <div class="u-mb-lg">
              <div class="cmp-section-label">Descrizione Progetto</div>
              <p class="t-body text-base m-0">{{ $campaign->description }}</p>
            </div>
          @endif
          
          <div class="cmp-info-row">
            <div>
              <div class="cmp-section-label">Periodo</div>
              <div class="font-bold text-base">
                @if($campaign->starts_at)
                  {{ $campaign->starts_at->format('d/m/Y') }} 
                  @if($campaign->ends_at)
                    - {{ $campaign->ends_at->format('d/m/Y') }}
                  @endif
                @else
                  Non definito
                @endif
              </div>
            </div>
            <div>
              <div class="cmp-section-label">Post Totali</div>
              <div class="font-bold text-base">{{ $totalPostsCount }}</div>
            </div>
          </div>
        </div>
      </div>

      {{-- Blocchi di Gestione --}}
      <div class="g-2col">
        
        {{-- Storico Periodi --}}
        <div class="panel u-overflow-hidden">
          <div class="lw-modal-hd">
            <div class="cmp-panel-title">Contratto / Periodi</div>
          </div>
          <div class="u-p-lg">
            @if($campaign->periods->count())
              <table class="cmp-inner-table">
                <thead>
                  <tr class="cmp-tr">
                    <th class="w-40">Dal - Al</th>
                    <th class="right">Importo</th>
                    <th class="right mkt-pr-0">Stato</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($campaign->periods->sortByDesc('from_date') as $period)
                    <tr class="cmp-tr">
                      <td >
                        {{ $period->from_date->format('d/m/Y') }}<br>
                        <span class="cmp-period-date">{{ $period->to_date ? $period->to_date->format('d/m/Y') : 'In corso' }}</span>
                      </td>
                      <td class="amount">€ {{ number_format($period->amount, 2, ',', '.') }}</td>
                      <td class="right">
                        <x-badge :status="$period->status->value" :label="$period->status->label()" />
                        @if($period->invoice_id)
                      <div class="cmp-inv-factured">Fatturato</div>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            @else
              <div class="u-text-muted u-text-italic">Nessun periodo registrato.</div>
            @endif
          </div>
        </div>

        <div class="u-flex-col u-gap-xl">
            {{-- Extra --}}
            <div class="panel u-overflow-hidden">
              <div class="lw-modal-hd">
                <div class="cmp-panel-title">Extra Campagna</div>
                <button wire:click="openExtraModal" class="btn btn-s btn-hd-sm">+ Aggiungi</button>
              </div>
              <div class="u-p-lg">
                @if($campaign->extras->count())
                  <table class="cmp-inner-table">
                    <tbody>
                      @foreach($campaign->extras->sortByDesc('created_at') as $extra)
                        <tr class="cmp-tr">
                          <td class="w-50">
                            <div>{{ $extra->description }}</div>
                            <div class="u-text-meta">{{ $extra->occurred_on ? $extra->occurred_on->format('d/m/Y') : '-' }}</div>
                          </td>
                          <td class="amount">€ {{ number_format($extra->amount, 2, ',', '.') }}</td>
                          <td class="u-flex-end u-gap-sm">
                            <x-badge :status="$extra->status->value" :label="$extra->status->label()" />
                            @if(!$extra->invoice_id)
                              <x-delete-modal wireClick="deleteExtra({{ $extra->id }})" title="Annulla Extra" message="Sei sicuro di voler annullare questo extra?">
                                <button type="button" class="btn-ghost-danger btn-xs u-text-muted">
                                  <i data-lucide="trash-2" class="u-icon-sm"></i>
                                </button>
                              </x-delete-modal>
                            @endif
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                @else
                  <div class="u-text-muted u-text-italic">Nessun extra registrato.</div>
                @endif
              </div>
            </div>

        </div>
      </div>
      
      {{-- Storico Fatture --}}
      <div class="panel u-overflow-hidden u-mt-lg">
        <div class="lw-modal-hd">
          <div class="cmp-panel-title">Storico Fatture</div>
          <button wire:click="openInvoiceModal" class="btn btn-s btn-hd-sm">Genera Fattura</button>
        </div>
        <div class="u-p-lg">
          @if($campaign->invoices && $campaign->invoices->count())
            <table class="cmp-inner-table">
              <tbody>
                @foreach($campaign->invoices->sortByDesc('issue_date') as $invoice)
                  <tr class="cmp-tr">
                    <td >
                      <div class="mkt-fw-bold">Fattura #{{ $invoice->number }}</div>
                      <div class="u-text-meta">Emissione: {{ $invoice->issue_date->format('d/m/Y') }}</div>
                    </td>
                    <td class="amount">
                      € {{ number_format($invoice->tax_amount, 2, ',', '.') }}<br>
                      <span class="mkt-text-xs-meta">Scadenza: {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}</span>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @else
            <div class="u-text-muted u-text-italic">Nessuna fattura registrata.</div>
          @endif
        </div>
      </div>

      {{-- Calendario Editoriale --}}
      <div class="panel u-overflow-hidden">
        <div class="lw-modal-hd">
          <div class="cmp-panel-title">Calendario Programmazione</div>
          <div class="u-flex-center u-gap-lg">
            <button type="button" class="btn btn-s" wire:click="previousMonth" class="btn-cal-nav">&larr;</button>
            <div class="cmp-month-nav">{{ Str::ucfirst($monthName) }}</div>
            <button type="button" class="btn btn-s" wire:click="nextMonth" class="btn-cal-nav">&rarr;</button>
          </div>
        </div>
        
        <div class="cal-day-header-grid">
            <div class="mkt-cam-label">LUN</div>
            <div class="mkt-cam-label">MAR</div>
            <div class="mkt-cam-label">MER</div>
            <div class="mkt-cam-label">GIO</div>
            <div class="mkt-cam-label">VEN</div>
            <div class="mkt-cam-label">SAB</div>
            <div class="mkt-cam-label">DOM</div>
        </div>

        <div class="cal-grid-7">
          @foreach($calendarGrid as $row)
            @foreach($row as $col)
              <div class="cal-cell {{ $col && $col['isToday'] ? 'today' : '' }}">
                @if($col)
                  <div class="mkt-flex-between-mb">
                    <span class="mkt-fs-12 {{ $col['isToday'] ? 'mkt-fw-bold mkt-text-blue' : 'mkt-fw-normal mkt-text-inherit' }}">{{ $col['day'] }}</span>
                    <button type="button" class="btn-calendar-add" wire:click="openPostModal(null, '{{ $col['date'] }}')">
                        <i data-lucide="plus" class="mkt-icon-12"></i>
                    </button>
                  </div>
                  <div class="mkt-flex-col-gap4">
                    @foreach($col['posts'] as $p)
                      <div wire:click="openPostModal({{ $p->id }})" class="cal-post-pill" title="{{ $p->title }}">
                        <span class="cal-post-dot {{ $p->status->value === 'draft' ? 'bg-gray-400' : ($p->status->value === 'published' ? 'bg-emerald-500' : 'bg-blue-500') }}"></span>
                        {{ $p->scheduled_time ? date('H:i', strtotime($p->scheduled_time)) . ' - ' : '' }} {{ $p->title ?: 'Senza Titolo' }}
                      </div>
                    @endforeach
                  </div>
                @endif
              </div>
            @endforeach
          @endforeach
        </div>
      </div>

      @if($campaign->notes)
        <div class="panel cmp-notes-box">
          <div class="t-label mb-2 cmp-notes-label">Note Interne</div>
          <div class="t-body">{{ $campaign->notes }}</div>
        </div>
      @endif

    </div>
  </div>

  {{-- Post Modal --}}
  @if($showPostModal)

    <div class="lw-overlay">
      <div class="lw-modal lw-modal-lg">
        
        <div class="lw-modal-hd">
          <h3 class="lw-modal-hd-title">{{ $editingPost ? 'Modifica Post' : 'Nuovo Post' }}</h3>
          <button type="button" wire:click="closePostModal" class="btn-ghost-white">
            <i data-lucide="x" class="u-icon-lg"></i>
          </button>
        </div>

        <div class="lw-modal-body custom-scrollbar">
          <form class="form-stack">
            
            <div class="form-g mb-0">
              <label class="form-lbl">Titolo Post</label>
              <input type="text" class="form-in" wire:model="form.title" placeholder="Es: Lancio prodotto X">
              @error('form.title') <span class="form-err">{{ $message }}</span> @enderror
            </div>

            <div class="form-g mb-0">
              <label class="form-lbl">Copy / Descrizione</label>
              <textarea class="form-ta" wire:model="form.description" rows="4" placeholder="Inserisci il testo del post..."></textarea>
              @error('form.description') <span class="form-err">{{ $message }}</span> @enderror
            </div>

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
                    @if($existing_media_url && !$media && $editingPost && $editingPost->media_source === 'local')
                    <div class="mb-3 relative inline-block group rounded border border-[var(--line)] overflow-hidden">
                        <img src="{{ $existing_media_url }}" class="h-32 object-contain bg-gray-100">
                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <span class="text-white text-xs font-bold">Media Attuale</span>
                        </div>
                    </div>
                    @endif

                    @if ($media)
                    <div class="mb-3 relative inline-block group rounded border border-blue-200 overflow-hidden">
                        <img src="{{ $media->temporaryUrl() }}" class="h-32 object-contain bg-blue-50">
                        <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                        <span class="text-white text-xs font-bold">Nuovo Upload in corso</span>
                        </div>
                    </div>
                    @endif

                    <input type="file" wire:model="media" class="form-in p-2 text-sm" accept="image/jpeg,image/png,image/webp">
                    <div wire:loading wire:target="media" class="text-xs text-blue-500 mt-1">Caricamento anteprima...</div>
                    @error('media') <span class="form-err">{{ $message }}</span> @enderror
                @else
                    {{-- Nextcloud Section --}}
                    @if($editingPost && $editingPost->media_source === 'nextcloud' && !$selected_nextcloud_file && !$form['nextcloud_path'])
                        <div class="cmp-nc-current-media">
                            <span class="u-text-strong">Media Attuale:</span> Collegato a Nextcloud<br>
                            Path: <code>{{ $editingPost->nextcloud_path }}</code>
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
                            <button type="button" wire:click="browseNextcloud(nextcloud_browse_path)" class="btn-sec">Esplora</button>
                        </div>
                        @error('form.nextcloud_path') <div class="form-err u-mb-md">{{ $message }}</div> @enderror

                        @if(!empty($nextcloud_files))
                            <div class="cmp-nc-browser">
                                @if($nextcloud_browse_path !== '/')
                                    <div wire:click="browseNextcloud('{{ dirname($nextcloud_browse_path) }}')" class="cmp-nc-item u-text-muted u-cursor-pointer">
                                        .. (Livello Superiore)
                                    </div>
                                @endif
                                @foreach($nextcloud_files as $ncFile)
                                    <div class="cmp-nc-item">
                                        @if($ncFile['is_dir'])
                                            <div wire:click="browseNextcloud('{{ $ncFile['path'] }}')" class="cmp-nc-dir">
                                                <i data-lucide="folder" class="u-icon-sm"></i> {{ $ncFile['name'] }}
                                            </div>
                                        @else
                                            <div class="u-flex-1">
                                                <label class="cmp-nc-file-label">
                                                    <input type="radio" name="nc_file_sel"
                                                        wire:click="selectNextcloudFile('{{ $ncFile['path'] }}', '{{ $ncFile['name'] }}', {{ $ncFile['size'] }}, '{{ $ncFile['content_type'] }}')"
                                                        {{ ($selected_nextcloud_file['path'] ?? null) === $ncFile['path'] ? 'checked' : '' }}>
                                                    <i data-lucide="image" class="cmp-nc-item-icon"></i>
                                                    {{ $ncFile['name'] }} <span class="u-text-meta">({{ round($ncFile['size'] / 1024) }} KB)</span>
                                                </label>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="panel cmp-panel-pad">
              <label class="cmp-ai-check-wrap">
                <input type="checkbox" wire:model.live="form.ai_analysis_enabled" class="cmp-ai-check-input">
                <div class="cmp-ai-check-content">
                  <div class="cmp-ai-check-title">Richiedi Analisi AI Sody</div>
                  <div class="cmp-ai-check-desc">Se abilitato, Sody analizzerà il media e genererà un copy se assente.</div>
                </div>
              </label>

              @if($form['ai_analysis_enabled'])
                  <div x-data="{ expanded: false }" class="cmp-ai-section">
                      <button type="button" @click="expanded = !expanded" class="cmp-ai-toggle">
                          <span><i data-lucide="settings" class="mkt-icon-14-mr6-middle"></i> Impostazioni Identità Cliente per AI</span>
                          <i data-lucide="chevron-down" x-show="!expanded" class="u-icon-md"></i>
                          <i data-lucide="chevron-up" x-show="expanded" class="mkt-icon-16-hidden"></i>
                      </button>

                      <div x-show="expanded" class="cmp-ai-expanded-content mkt-hidden">
                          {{-- Logo --}}
                          <div class="cmp-client-box">
                              <label class="cmp-check-label">
                                  <input type="checkbox" wire:model.live="include_client_logo" class="u-cursor-pointer">
                                  Includi logo cliente nel briefing
                              </label>

                              @if($campaign->client->logo_path)
                                  <div x-show="$wire.include_client_logo" class="cmp-ai-section muted">
                                      Logo attuale:<br>
                                      <img src="{{ $campaign->client->logo_url }}" alt="Logo Cliente" class="cmp-ai-client-logo">
                                  </div>
                              @else
                                  <div x-show="$wire.include_client_logo" class="cmp-ai-section">
                                      <div class="u-text-meta u-text-orange u-mb-sm">Nessun logo presente nella scheda cliente. Caricane uno per il task.</div>
                                      <input type="file" wire:model="runtime_logo" class="form-in cmp-file-sm" accept="image/jpeg,image/png,image/webp">
                                      @error('runtime_logo') <div class="form-err form-err-sm">{{ $message }}</div> @enderror
                                      
                                      <label class="cmp-save-label">
                                          <input type="checkbox" wire:model="save_runtime_logo_to_client" class="u-cursor-pointer">
                                          Salva e imposta come logo ufficiale
                                      </label>
                                  </div>
                              @endif
                          </div>

                          {{-- Header / Activity --}}
                          <div class="cmp-client-box">
                              <label class="cmp-check-label">
                                  <input type="checkbox" wire:model.live="include_client_header" class="u-cursor-pointer">
                                  Includi descrizione attività nel briefing
                              </label>

                              @if($campaign->client->activity_description)
                                  <div x-show="$wire.include_client_header" class="cmp-ai-section muted">
                                      Testo attuale:<br>
                                      <div class="cmp-ai-activity-preview custom-scrollbar">
                                          {{ Str::limit($campaign->client->activity_description, 100, '') }}
                                          @if(strlen($campaign->client->activity_description) > 100)
                                              <span>...</span>
                                          @endif
                                      </div>
                                  </div>
                              @else
                                  <div x-show="$wire.include_client_header" class="cmp-ai-section">
                                      <div class="u-text-meta u-text-orange u-mb-sm">Nessuna descrizione presente. Scrivine una.</div>
                                      <textarea wire:model="runtime_activity_description" class="form-ta cmp-ta-sm" placeholder="Descrivi l'attività del cliente..."></textarea>
                                      @error('runtime_activity_description') <div class="form-err form-err-sm">{{ $message }}</div> @enderror
                                      
                                      <label class="cmp-save-label">
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

            @if($editingPost && $editingPost->currentVersion)
                <div class="panel cmp-version-box">
                    <div class="cmp-version-hd">
                        <h4 class="mkt-fw600-fs15-m0-flex-gap8">
                            <i data-lucide="sparkles" class="mkt-icon-16-blue"></i>
                            Versione Generata (v{{ $editingPost->currentVersion->version_number }})
                        </h4>
                        <span class="cmp-version-status-badge">
                            {{ $editingPost->status->label() }}
                        </span>
                    </div>

                    <div class="cmp-version-content">
                        @if($editingPost->currentVersion->image_url)
                            <div class="mkt-shrink-0">
                                <img src="{{ $editingPost->currentVersion->image_url }}" class="cmp-version-img">
                            </div>
                        @endif
                        <div class="mkt-flex1-fs13-text2">
                            <strong class="mkt-text1-fs14">{{ $editingPost->currentVersion->title }}</strong>
                            <div class="cmp-version-caption">{{ $editingPost->currentVersion->caption }}</div>
                        </div>
                    </div>

                    @if($editingPost->comments->count() > 0)
                        <div class="mkt-mb-24">
                            <div class="mkt-feedback-title">Feedback Ricevuti</div>
                            <div class="cmp-feedback-list custom-scrollbar">
                                @foreach($editingPost->comments as $comment)
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
                        <button type="button" wire:click="addInternalComment({{ $editingPost->id }})" class="btn btn-s mkt-btn-s-pad">
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
                                    <input type="text" value="{{ $generatedReviewLink }}" readonly class="form-in mkt-review-input" id="review-link-{{ $editingPost->id }}">
                                    <button type="button" 
                                        @click="navigator.clipboard.writeText(document.getElementById('review-link-{{ $editingPost->id }}').value); copied = true; setTimeout(() => copied = false, 2000)" 
                                        class="btn btn-s mkt-review-btn" 
                                        :class="copied ? 'btn-green' : ''">
                                        <span x-show="!copied">Copia Link</span>
                                        <span x-show="copied" x-cloak style="display: none;"><i data-lucide="check" class="u-icon-sm"></i> Copiato!</span>
                                    </button>
                                </div>
                            </div>
                        @else
                            @if($editingPost->canRegenerate())
                                <button type="button" wire:click="regeneratePost({{ $editingPost->id }}, 'full')" class="btn btn-s u-flex-center u-gap-xs">
                                    <i data-lucide="refresh-cw" class="u-icon-sm"></i> Rigenera Tutto
                                </button>
                                <button type="button" wire:click="regeneratePost({{ $editingPost->id }}, 'caption')" class="btn btn-s u-flex-center u-gap-xs">
                                    <i data-lucide="type" class="u-icon-sm"></i> Rigenera Testo
                                </button>
                                <button type="button" wire:click="regeneratePost({{ $editingPost->id }}, 'image')" class="btn btn-s u-flex-center u-gap-xs">
                                    <i data-lucide="image" class="u-icon-sm"></i> Rigenera Immagine
                                </button>
                            @endif

                            <div class="u-flex-1"></div>

                            @if(in_array($editingPost->status->value, ['generated', 'ready_for_client', 'client_changes_requested']))
                                <button type="button" wire:click="sendToClient({{ $editingPost->id }})" class="btn btn-s btn-purple u-flex-center u-gap-xs">
                                    <i data-lucide="send" class="u-icon-sm"></i> Invia al Cliente
                                </button>
                            @endif

                            @if(!in_array($editingPost->status->value, ['approved', 'published', 'cancelled']))
                                <button type="button" wire:click="approvePost({{ $editingPost->id }})" class="btn btn-s btn-green u-flex-center u-gap-xs">
                                    <i data-lucide="check" class="u-icon-sm"></i> Approva Definitivamente
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            @endif

          </form>
        </div>

        <div class="lw-modal-ft mkt-modal-ft-between">
          <div>
            @if($editingPost)
              <x-delete-modal wireClick="deletePost({{ $editingPost->id }})" title="Elimina Post" message="Sei sicuro di voler eliminare questo post?">
                <button type="button" class="btn btn-d u-flex-center u-gap-xs">
                  <i data-lucide="trash-2" class="u-icon-md"></i> Elimina
                </button>
              </x-delete-modal>
            @endif
          </div>
          
          <div class="u-flex u-gap-sm">
            <button type="button" wire:click="closePostModal" class="btn btn-s">Annulla</button>
            
            @if($form['ai_analysis_enabled'])
                <button type="button" wire:click="savePost" class="btn btn-s">
                  <span wire:loading.remove wire:target="savePost">
                    {{ $editingPost && $editingPost->status->value !== 'draft' ? 'Aggiorna Dati' : 'Salva Bozza' }}
                  </span>
                  <span wire:loading wire:target="savePost">Salvataggio...</span>
                </button>
                <button type="button" wire:click="saveAndSubmitToN8n" class="btn btn-p u-flex-center u-gap-xs">
                  <i data-lucide="sparkles" class="u-icon-md"></i>
                  <span wire:loading.remove wire:target="saveAndSubmitToN8n">
                    {{ $editingPost && $editingPost->status->value !== 'draft' ? 'Rigenera con Sody' : 'Salva e Invia a Sody' }}
                  </span>
                  <span wire:loading wire:target="saveAndSubmitToN8n">Invio in corso...</span>
                </button>
            @else
                <button type="button" wire:click="savePost" class="btn btn-p u-flex-center u-gap-xs">
                  <i data-lucide="save" class="u-icon-md"></i>
                  <span wire:loading.remove wire:target="savePost">
                    {{ $editingPost && $editingPost->status->value !== 'draft' ? 'Aggiorna Post' : 'Salva Post' }}
                  </span>
                  <span wire:loading wire:target="savePost">Salvataggio...</span>
                </button>
            @endif
          </div>
        </div>

      </div>
    </div>
  @endif

  {{-- Modale: Modifica Campagna --}}
  @if($showCampaignModal)
    <div class="lw-overlay">
      <div class="lw-modal">
        <div class="lw-modal-hd">
          <h3 class="lw-modal-hd-title">Modifica Campagna</h3>
          <button type="button" wire:click="closeCampaignModal" class="btn-ghost-white">
            <i data-lucide="x" class="u-icon-lg"></i>
          </button>
        </div>
        <div class="lw-modal-body custom-scrollbar">
          <div class="form-g">
            <label class="form-lbl">Nome Campagna</label>
            <input type="text" class="form-in" wire:model="campaignForm.name">
            @error('campaignForm.name') <span class="form-err">{{ $message }}</span> @enderror
          </div>
          <div class="form-g">
            <label class="form-lbl">Descrizione</label>
            <textarea class="form-ta" wire:model="campaignForm.description"></textarea>
          </div>
          <div class="u-flex u-gap-lg">
            <div class="form-g u-flex-1">
                <label class="form-lbl">Data Inizio</label>
                <input type="date" class="form-in" wire:model="campaignForm.starts_at">
            </div>
            <div class="form-g u-flex-1">
                <label class="form-lbl">Data Fine</label>
                <input type="date" class="form-in" wire:model="campaignForm.ends_at">
            </div>
          </div>
          <div class="form-g">
            <label class="form-lbl">Stato</label>
            <select class="form-sel" wire:model="campaignForm.status">
              <option value="draft">Bozza</option>
              <option value="active">Attiva</option>
              <option value="paused">In Pausa</option>
              <option value="closed">Chiusa</option>
            </select>
          </div>
          <div class="form-g">
            <label class="form-lbl">Budget Mensile (€)</label>
            <input type="number" step="0.01" class="form-in" wire:model="campaignForm.monthly_fee">
          </div>
          <div class="form-g">
            <label class="form-lbl">Note Interne</label>
            <textarea class="form-ta" wire:model="campaignForm.notes"></textarea>
          </div>
        </div>
        <div class="lw-modal-ft">
          <button type="button" wire:click="closeCampaignModal" class="btn btn-s">Annulla</button>
          <button type="button" wire:click="saveCampaign" class="btn btn-p">Salva Modifiche</button>
        </div>
      </div>
    </div>
  @endif

  {{-- Modale: Prolunga Campagna --}}
  @if($showExtendModal)
    <div class="lw-overlay">
      <div class="lw-modal">
        <div class="lw-modal-hd">
          <h3 class="lw-modal-hd-title">Prolunga Campagna</h3>
          <button type="button" wire:click="closeExtendModal" class="btn-ghost-white">
            <i data-lucide="x" class="u-icon-lg"></i>
          </button>
        </div>
        <div class="lw-modal-body custom-scrollbar">
          <p class="u-text-muted u-mb-md">
            Aggiungi un nuovo periodo di fatturazione. Lo stato della campagna tornerà "Attivo" se era chiuso o in pausa.
          </p>
          <div class="u-flex u-gap-lg">
            <div class="form-g u-flex-1">
                <label class="form-lbl">Dal</label>
                <input type="date" class="form-in" wire:model="extendForm.from_date">
                @error('extendForm.from_date') <span class="form-err">{{ $message }}</span> @enderror
            </div>
            <div class="form-g u-flex-1">
                <label class="form-lbl">Al (opzionale)</label>
                <input type="date" class="form-in" wire:model="extendForm.to_date">
                @error('extendForm.to_date') <span class="form-err">{{ $message }}</span> @enderror
            </div>
          </div>
          <div class="form-g">
            <label class="form-lbl">Importo (€)</label>
            <input type="number" step="0.01" class="form-in" wire:model="extendForm.amount">
            @error('extendForm.amount') <span class="form-err">{{ $message }}</span> @enderror
          </div>
          <div class="form-g">
            <label class="form-lbl">Descrizione</label>
            <input type="text" class="form-in" wire:model="extendForm.description">
            @error('extendForm.description') <span class="form-err">{{ $message }}</span> @enderror
          </div>
        </div>
        <div class="lw-modal-ft">
          <button type="button" wire:click="closeExtendModal" class="btn btn-s">Annulla</button>
          <button type="button" wire:click="extendCampaign" class="btn btn-p">Prolunga</button>
        </div>
      </div>
    </div>
  @endif

  {{-- Modale: Rinnova Campagna --}}
  @if($showRenewModal)
    <div class="lw-overlay">
      <div class="lw-modal">
        <div class="lw-modal-hd">
          <h3 class="lw-modal-hd-title">Rinnova Campagna</h3>
          <button type="button" wire:click="closeRenewModal" class="btn-ghost-white">
            <i data-lucide="x" class="u-icon-lg"></i>
          </button>
        </div>
        <div class="lw-modal-body custom-scrollbar">
          <p class="u-text-muted u-mb-md">
            Riattiva questa campagna chiusa o in pausa creando un nuovo contratto/periodo.
          </p>
          <div class="form-g">
            <label class="form-lbl">Nuova Data Inizio Contratto (starts_at)</label>
            <input type="date" class="form-in" wire:model="renewForm.starts_at">
          </div>
          <div class="u-flex u-gap-lg">
            <div class="form-g u-flex-1">
                <label class="form-lbl">Primo Periodo Dal</label>
                <input type="date" class="form-in" wire:model="renewForm.from_date">
            </div>
            <div class="form-g u-flex-1">
                <label class="form-lbl">Al (opzionale)</label>
                <input type="date" class="form-in" wire:model="renewForm.to_date">
            </div>
          </div>
          <div class="form-g">
            <label class="form-lbl">Importo (€)</label>
            <input type="number" step="0.01" class="form-in" wire:model="renewForm.amount">
          </div>
          <div class="form-g">
            <label class="form-lbl">Descrizione</label>
            <input type="text" class="form-in" wire:model="renewForm.description">
          </div>
        </div>
        <div class="lw-modal-ft">
          <button type="button" wire:click="closeRenewModal" class="btn btn-s">Annulla</button>
          <button type="button" wire:click="renewCampaign" class="btn btn-p">Rinnova</button>
        </div>
      </div>
    </div>
  @endif

  {{-- Modale: Aggiungi Extra --}}
  @if($showExtraModal)
    <div class="lw-overlay">
      <div class="lw-modal lw-modal-sm">
        <div class="lw-modal-hd">
          <h3 class="lw-modal-hd-title">Aggiungi Extra</h3>
          <button type="button" wire:click="closeExtraModal" class="btn-ghost-white">
            <i data-lucide="x" class="u-icon-lg"></i>
          </button>
        </div>
        <div class="lw-modal-body">
          <div class="form-g">
            <label class="form-lbl">Descrizione Servizio</label>
            <input type="text" class="form-in" wire:model="extraForm.description" placeholder="Es: Shooting fotografico">
            @error('extraForm.description') <span class="form-err">{{ $message }}</span> @enderror
          </div>
          <div class="form-g">
            <label class="form-lbl">Importo (€)</label>
            <input type="number" step="0.01" class="form-in" wire:model="extraForm.amount">
            @error('extraForm.amount') <span class="form-err">{{ $message }}</span> @enderror
          </div>
          <div class="form-g">
            <label class="form-lbl">Data di Svolgimento</label>
            <input type="date" class="form-in" wire:model="extraForm.occurred_on">
            @error('extraForm.occurred_on') <span class="form-err">{{ $message }}</span> @enderror
          </div>
        </div>
        <div class="lw-modal-ft">
          <button type="button" wire:click="closeExtraModal" class="btn btn-s">Annulla</button>
          <button type="button" wire:click="addExtra" class="btn btn-p">Salva Extra</button>
        </div>
      </div>
    </div>
  @endif

  {{-- Modale: Genera Fattura --}}
  @if($showInvoiceModal)
    <div class="lw-overlay">
      <div class="lw-modal">
        <div class="lw-modal-hd">
          <h3 class="lw-modal-hd-title">Genera Fattura</h3>
          <button type="button" wire:click="closeInvoiceModal" class="btn-ghost-white">
            <i data-lucide="x" class="u-icon-lg"></i>
          </button>
        </div>
        <div class="lw-modal-body custom-scrollbar">
          @if(count($pendingPeriodsForInvoice) > 0)
          <div class="cmp-client-box">
            <div class="cmp-section-label light">Periodi da fatturare</div>
            @foreach($pendingPeriodsForInvoice as $period)
            <label class="cmp-check-label muted">
              <input type="checkbox" wire:model="invoiceForm.period_ids" value="{{ $period['id'] }}" class="u-accent-blue">
              <span>{{ $period['description'] }} - <strong style="color:var(--text);">€ {{ number_format($period['amount'], 2, ',', '.') }}</strong></span>
            </label>
            @endforeach
          </div>
          @endif

          @if(count($pendingExtrasForInvoice) > 0)
          <div class="cmp-client-box">
            <div class="cmp-section-label light">Extra da fatturare</div>
            @foreach($pendingExtrasForInvoice as $extra)
            <label class="cmp-check-label muted">
              <input type="checkbox" wire:model="invoiceForm.extra_ids" value="{{ $extra['id'] }}" class="u-accent-blue">
              <span>{{ $extra['description'] }} - <strong style="color:var(--text);">€ {{ number_format($extra['amount'], 2, ',', '.') }}</strong></span>
            </label>
            @endforeach
          </div>
          @endif
          
          @error('invoiceForm') <div class="form-err" style="margin-bottom:16px; font-weight:bold;">{{ $message }}</div> @enderror
          
          <div class="u-flex u-gap-lg">
              <div class="form-g u-flex-1">
                <label class="form-lbl">Numero Fattura</label>
                <input type="text" class="form-in" wire:model="invoiceForm.number" placeholder="Es: FAT-001/26">
                @error('invoiceForm.number') <span class="form-err">{{ $message }}</span> @enderror
              </div>
          </div>
          
          <div class="u-flex u-gap-lg">
            <div class="form-g u-flex-1">
                <label class="form-lbl">Data Emissione</label>
                <input type="date" class="form-in" wire:model="invoiceForm.issue_date">
                @error('invoiceForm.issue_date') <span class="form-err">{{ $message }}</span> @enderror
            </div>
            <div class="form-g u-flex-1">
                <label class="form-lbl">Data Scadenza</label>
                <input type="date" class="form-in" wire:model="invoiceForm.due_date">
                @error('invoiceForm.due_date') <span class="form-err">{{ $message }}</span> @enderror
            </div>
          </div>
          
          <div class="form-g">
            <label class="form-lbl">Ammontare Tasse/IVA (€)</label>
            <input type="number" step="0.01" class="form-in" wire:model="invoiceForm.tax_amount">
            @error('invoiceForm.tax_amount') <span class="form-err">{{ $message }}</span> @enderror
          </div>
          
        </div>
        <div class="lw-modal-ft">
          <button type="button" wire:click="closeInvoiceModal" class="btn btn-s">Annulla</button>
          <button type="button" wire:click="generateInvoice" class="btn btn-p btn-green">Genera Fattura</button>
        </div>
      </div>
    </div>
  @endif

</div>


