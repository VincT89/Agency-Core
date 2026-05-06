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
          <div class="cmp-panel-title">Post da Programmare</div>
          <button type="button" class="btn btn-p" class="cmp-aside-new-btn" wire:click="openPostModal()">
            <i data-lucide="plus" class="u-icon-sm"></i> Nuovo
          </button>
        </div>
        
        <div class="cmp-aside-body">
          @forelse($posts->whereNull('scheduled_date') as $post)
            <div class="panel" style="padding:16px; cursor:pointer;" wire:click="openPostModal({{ $post->id }})" wire:key="post-{{ $post->id }}">
              
              <div class="flex justify-between items-start mb-2">
                <x-badge :status="$post->status->value" :label="$post->status->label()" />
                <div class="t-mono text-xs text-gray-500">
                  Data non decisa
                </div>
              </div>
              
              <div class="font-bold text-[var(--text)] mb-1">
                {{ $post->title ?: 'Senza Titolo' }}
              </div>
              
              @if($post->description)
                <div class="text-sm text-[var(--text2)] line-clamp-2 mb-3">
                  {{ $post->description }}
                </div>
              @endif

              <div class="flex items-center justify-between mt-2 pt-2 border-t border-[var(--line)]">
                <div class="flex gap-2 text-xs font-mono text-gray-500">
                  <span class="uppercase">{{ $post->content_type->label() }}</span>
                  @if($post->media_path)
                    <span style="display:flex; align-items:center; color:var(--blue);"><i data-lucide="image" style="width:14px; height:14px; margin-right:4px;"></i> Media</span>
                  @endif
                </div>
                
                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button type="button" class="btn btn-s btn-sm text-red-500 hover:text-red-600" wire:click.stop="deletePost({{ $post->id }})" wire:confirm="Sei sicuro di voler eliminare questo post?">
                    <i data-lucide="trash-2" class="u-icon-sm"></i>
                  </button>
                </div>
              </div>

            </div>
          @empty
            <div class="cmp-aside-empty">
              <i data-lucide="inbox" class="cmp-aside-empty-icon"></i>
              <p style="font-size:13px;">Nessun post da programmare.</p>
              <p style="font-size:11px; margin-top:4px;">Clicca su "Nuovo" per iniziare.</p>
            </div>
          @endforelse
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
              <table class="w-full text-left" style="font-size:13px;">
                <thead>
                  <tr class="cmp-tr">
                    <th style="padding-bottom:8px; font-weight:normal; width:40%;">Dal - Al</th>
                    <th style="padding-bottom:8px; font-weight:normal; text-align:right; padding-right:16px;">Importo</th>
                    <th style="padding-bottom:8px; font-weight:normal; text-align:right;">Stato</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($campaign->periods->sortByDesc('from_date') as $period)
                    <tr class="cmp-tr">
                      <td style="padding:12px 0;">
                        {{ $period->from_date->format('d/m/Y') }}<br>
                        <span style="color:var(--text2); font-size:11px;">{{ $period->to_date ? $period->to_date->format('d/m/Y') : 'In corso' }}</span>
                      </td>
                      <td class="cmp-td-amount">€ {{ number_format($period->amount, 2, ',', '.') }}</td>
                      <td style="padding:12px 0; text-align:right;">
                        <x-badge :status="$period->status->value" :label="$period->status->label()" />
                        @if($period->invoice_id)
                          <div style="font-size:10px; margin-top:4px; color:var(--text3);">Fatturato</div>
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

        <div style="display:flex; flex-direction:column; gap:24px;">
            {{-- Extra --}}
            <div class="panel u-overflow-hidden">
              <div class="lw-modal-hd">
                <div class="cmp-panel-title">Extra Campagna</div>
                <button wire:click="openExtraModal" class="btn btn-s" class="btn-hd-sm">+ Aggiungi</button>
              </div>
              <div class="u-p-lg">
                @if($campaign->extras->count())
                  <table class="w-full text-left" style="font-size:13px;">
                    <tbody>
                      @foreach($campaign->extras->sortByDesc('created_at') as $extra)
                        <tr class="cmp-tr">
                          <td style="padding:12px 0; width:50%;">
                            <div>{{ $extra->description }}</div>
                            <div class="u-text-meta">{{ $extra->occurred_on ? $extra->occurred_on->format('d/m/Y') : '-' }}</div>
                          </td>
                          <td class="cmp-td-amount">€ {{ number_format($extra->amount, 2, ',', '.') }}</td>
                          <td class="u-flex-end u-gap-sm">
                            <x-badge :status="$extra->status->value" :label="$extra->status->label()" />
                            @if(!$extra->invoice_id)
                              <button wire:click="deleteExtra({{ $extra->id }})" class="btn btn-d" style="font-size:10px; padding:2px 4px; border:none; background:transparent; color:var(--text3); cursor:pointer;" onclick="confirm('Sei sicuro di voler annullare questo extra?') || event.stopImmediatePropagation()">
                                <i data-lucide="trash-2" class="u-icon-sm"></i>
                              </button>
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
          <button wire:click="openInvoiceModal" class="btn btn-s" class="btn-hd-sm">Genera Fattura</button>
        </div>
        <div class="u-p-lg">
          @if($campaign->invoices && $campaign->invoices->count())
            <table class="w-full text-left" style="font-size:13px;">
              <tbody>
                @foreach($campaign->invoices->sortByDesc('issue_date') as $invoice)
                  <tr class="cmp-tr">
                    <td style="padding:12px 0;">
                      <div style="font-weight:bold;">Fattura #{{ $invoice->number }}</div>
                      <div class="u-text-meta">Emissione: {{ $invoice->issue_date->format('d/m/Y') }}</div>
                    </td>
                    <td class="cmp-td-amount">
                      € {{ number_format($invoice->tax_amount, 2, ',', '.') }}<br>
                      <span style="font-size:10px; color:var(--text3);">Scadenza: {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}</span>
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
            <button type="button" class="btn btn-s" wire:click="previousMonth" style="padding:4px 8px;">&larr;</button>
            <div style="font-weight:bold; width:150px; text-align:center;">{{ Str::ucfirst($monthName) }}</div>
            <button type="button" class="btn btn-s" wire:click="nextMonth" style="padding:4px 8px;">&rarr;</button>
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
                  <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <span style="font-size:12px; font-weight:{{ $col['isToday'] ? 'bold' : 'normal' }}; color:{{ $col['isToday'] ? 'var(--blue)' : 'inherit' }}">{{ $col['day'] }}</span>
                    <button type="button" class="btn-calendar-add" wire:click="openPostModal(null, '{{ $col['date'] }}')">
                        <i data-lucide="plus" style="width:12px; height:12px;"></i>
                    </button>
                  </div>
                  <div style="display:flex; flex-direction:column; gap:4px;">
                    @foreach($col['posts'] as $p)
                      <div wire:click="openPostModal({{ $p->id }})" class="cal-post-pill" title="{{ $p->title }}">
                        <span class="cal-post-dot" style="background:{{ $p->status->value === 'draft' ? '#9ca3af' : ($p->status->value === 'published' ? '#10b981' : '#3b82f6') }};"></span>
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
          <div class="t-label mb-2" style="color:#854d0e;">Note Interne</div>
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
          <h3 style="font-weight:bold; font-size:18px; margin:0; color:#fff;">{{ $editingPost ? 'Modifica Post' : 'Nuovo Post' }}</h3>
          <button type="button" wire:click="closePostModal" class="btn-ghost-white">
            <i data-lucide="x" class="u-icon-lg"></i>
          </button>
        </div>

        <div class="lw-modal-body" class="custom-scrollbar">
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
                <label class="form-lbl">Tipo Contenuto <span style="color:var(--red)">*</span></label>
                <select class="form-sel" wire:model="form.content_type" required>
                  <option value="post">Post</option>
                  <option value="story">Story</option>
                  <option value="reel">Reel</option>
                </select>
                @error('form.content_type') <span class="form-err">{{ $message }}</span> @enderror
              </div>

              <div class="form-g mb-0 u-flex-1">
                <label class="form-lbl">Stato <span style="color:var(--red)">*</span></label>
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

            <div class="panel" style="padding:16px; margin-bottom:0;">
              <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" wire:model.live="form.ai_analysis_enabled" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <div>
                  <div class="font-bold text-sm">Richiedi Analisi AI N8n</div>
                  <div class="text-xs text-gray-500">Se abilitato, N8n analizzerà il media e genererà un copy se assente.</div>
                </div>
              </label>

              @if($form['ai_analysis_enabled'])
                  <div x-data="{ expanded: false }" class="cmp-ai-section">
                      <button type="button" @click="expanded = !expanded" style="display:flex; justify-content:space-between; align-items:center; width:100%; text-align:left; font-weight:bold; font-size:13px; color:var(--text2); background:none; border:none; padding:0; cursor:pointer;">
                          <span><i data-lucide="settings" style="width:14px; height:14px; margin-right:6px; display:inline-block; vertical-align:middle;"></i> Impostazioni Identità Cliente per AI</span>
                          <i data-lucide="chevron-down" x-show="!expanded" class="u-icon-md"></i>
                          <i data-lucide="chevron-up" x-show="expanded" style="width:16px; height:16px; display:none;"></i>
                      </button>

                      <div x-show="expanded" style="display:none; margin-top:16px; flex-direction:column; gap:16px;">
                          {{-- Logo --}}
                          <div class="cmp-client-box">
                              <label class="cmp-check-label">
                                  <input type="checkbox" wire:model.live="include_client_logo" class="u-cursor-pointer">
                                  Includi logo cliente nel briefing
                              </label>

                              @if($campaign->client->logo_path)
                                  <div x-show="$wire.include_client_logo" style="margin-top:12px; padding-top:12px; border-top:1px solid var(--line); font-size:12px; color:var(--text3);">
                                      Logo attuale:<br>
                                      <img src="{{ $campaign->client->logo_url }}" alt="Logo Cliente" style="height:30px; border-radius:4px; margin-top:4px; border:1px solid var(--line);">
                                  </div>
                              @else
                                  <div x-show="$wire.include_client_logo" style="margin-top:12px; padding-top:12px; border-top:1px solid var(--line);">
                                      <div class="u-text-meta u-text-orange u-mb-sm">Nessun logo presente nella scheda cliente. Caricane uno per il task.</div>
                                      <input type="file" wire:model="runtime_logo" class="form-in" style="font-size:12px; padding:4px;" accept="image/jpeg,image/png,image/webp">
                                      @error('runtime_logo') <div style="color:var(--red);font-size:11px;margin-top:2px;">{{ $message }}</div> @enderror
                                      
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
                                  <div x-show="$wire.include_client_header" style="margin-top:12px; padding-top:12px; border-top:1px solid var(--line); font-size:12px; color:var(--text3);">
                                      Testo attuale:<br>
                                      <div style="background:var(--bg); padding:8px; border-radius:4px; margin-top:4px; border:1px solid var(--line); max-height:60px; overflow-y:auto;">
                                          {{ Str::limit($campaign->client->activity_description, 100, '') }}
                                          @if(strlen($campaign->client->activity_description) > 100)
                                              <span>...</span>
                                          @endif
                                      </div>
                                  </div>
                              @else
                                  <div x-show="$wire.include_client_header" style="margin-top:12px; padding-top:12px; border-top:1px solid var(--line);">
                                      <div class="u-text-meta u-text-orange u-mb-sm">Nessuna descrizione presente. Scrivine una.</div>
                                      <textarea wire:model="runtime_activity_description" class="form-ta" style="font-size:12px; padding:8px; min-height:60px;" placeholder="Descrivi l'attività del cliente..."></textarea>
                                      @error('runtime_activity_description') <div style="color:var(--red);font-size:11px;margin-top:2px;">{{ $message }}</div> @enderror
                                      
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

            <div class="panel" style="padding:16px; margin-bottom:0;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <label class="form-lbl mb-0">Sorgente Media</label>
                    <div class="u-flex u-gap-lg">
                        <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                            <input type="radio" wire:model.live="form.media_source" value="local">
                            Upload Locale
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
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
                        <div style="margin-bottom:12px; font-size:12px; color:var(--text2); background:var(--bg2); padding:8px; border-radius:4px; border:1px solid var(--line);">
                            <span style="font-weight:bold;">Media Attuale:</span> Collegato a Nextcloud<br>
                            Path: <code style="font-size:11px;">{{ $editingPost->nextcloud_path }}</code>
                        </div>
                    @endif

                    @if($selected_nextcloud_file)
                        <div style="display:flex; align-items:center; gap:10px; padding:10px; border:1px solid var(--green); border-radius:6px; background:var(--bg); margin-bottom:10px;">
                            <div style="flex:1; font-size:13px; color:var(--green); font-weight:bold;">
                                <i data-lucide="check-circle" class="u-icon-sm"></i>
                                File Selezionato: {{ $selected_nextcloud_file['name'] }}
                            </div>
                            <button type="button" wire:click="removeNextcloudFile" class="btn btn-s" style="padding:4px 8px;">Rimuovi</button>
                        </div>
                    @endif

                    <div class="form-g mb-0" style="margin-top:10px;">
                        <label class="form-lbl" style="font-size:12px;">Sfoglia Cartelle Nextcloud</label>
                        <div style="display:flex; gap:10px; margin-bottom:10px;">
                            <input type="text" wire:model="nextcloud_browse_path" class="form-in" placeholder="/" disabled>
                            <button type="button" wire:click="browseNextcloud(nextcloud_browse_path)" class="btn-sec" style="padding:8px 15px;">Esplora</button>
                        </div>
                        @error('form.nextcloud_path') <span class="form-err" style="display:block; margin-bottom:10px;">{{ $message }}</span> @enderror

                        @if(!empty($nextcloud_files))
                            <div style="max-height:200px; overflow-y:auto; border:1px solid var(--line); border-radius:var(--r); background:var(--bg); padding:10px;">
                                @if($nextcloud_browse_path !== '/')
                                    <div wire:click="browseNextcloud('{{ dirname($nextcloud_browse_path) }}')" style="cursor:pointer; padding:5px; border-bottom:1px solid var(--line); color:var(--text2); font-size:13px;">
                                        .. (Livello Superiore)
                                    </div>
                                @endif
                                @foreach($nextcloud_files as $ncFile)
                                    <div style="display:flex; align-items:center; gap:10px; padding:5px; border-bottom:1px solid var(--line); font-size:13px;">
                                        @if($ncFile['is_dir'])
                                            <div wire:click="browseNextcloud('{{ $ncFile['path'] }}')" style="cursor:pointer; color:var(--blue); flex:1; font-weight:bold;">
                                                <i data-lucide="folder" class="u-icon-sm"></i> {{ $ncFile['name'] }}
                                            </div>
                                        @else
                                            <div class="u-flex-1">
                                                <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                                                    <input type="radio" name="nc_file_sel"
                                                        wire:click="selectNextcloudFile('{{ $ncFile['path'] }}', '{{ $ncFile['name'] }}', {{ $ncFile['size'] }}, '{{ $ncFile['content_type'] }}')"
                                                        {{ ($selected_nextcloud_file['path'] ?? null) === $ncFile['path'] ? 'checked' : '' }}>
                                                    <i data-lucide="image" style="width:14px; height:14px; display:inline-block; vertical-align:middle; color:var(--text3);"></i>
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

            @if($editingPost && $editingPost->currentVersion)
                <div class="panel" class="cmp-version-box">
                    <div class="cmp-version-hd">
                        <h4 style="font-weight:600; font-size:15px; margin:0; display:flex; align-items:center; gap:8px;">
                            <i data-lucide="sparkles" style="width:16px; height:16px; color:var(--blue);"></i>
                            Versione Generata (v{{ $editingPost->currentVersion->version_number }})
                        </h4>
                        <span style="font-size:11px; font-weight:600; padding:4px 10px; border-radius:20px; background:var(--blue); color:white;">
                            {{ $editingPost->status->label() }}
                        </span>
                    </div>

                    <div class="cmp-version-content">
                        @if($editingPost->currentVersion->image_url)
                            <div style="flex-shrink:0;">
                                <img src="{{ $editingPost->currentVersion->image_url }}" class="cmp-version-img">
                            </div>
                        @endif
                        <div style="flex:1; font-size:13px; color:var(--text2);">
                            <strong style="color:var(--text1); font-size:14px;">{{ $editingPost->currentVersion->title }}</strong>
                            <div style="margin-top:12px; white-space:pre-wrap; line-height:1.6;">{{ $editingPost->currentVersion->caption }}</div>
                        </div>
                    </div>

                    @if($editingPost->comments->count() > 0)
                        <div style="margin-bottom:24px;">
                            <div style="font-size:11px; font-weight:600; color:var(--text3); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:12px;">Feedback Ricevuti</div>
                            <div style="display:flex; flex-direction:column; gap:10px; max-height:250px; overflow-y:auto; padding-right:8px;" class="custom-scrollbar">
                                @foreach($editingPost->comments as $comment)
                                    <div style="font-size:13px; padding:12px; background:var(--bg); border:1px solid var(--line); border-radius:8px;">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:11px;">
                                            <span>
                                                @if($comment->visibility->value === 'client')
                                                    <strong style="color:var(--orange);">[Cliente] {{ $comment->client_name }}</strong>
                                                @else
                                                    <strong style="color:var(--purple);">[Team] {{ $comment->user->name ?? 'Sistema' }}</strong>
                                                @endif
                                            </span>
                                            <span style="color:var(--text3);">{{ $comment->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div style="color:var(--text1); line-height:1.5;">{{ $comment->body }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div style="margin-bottom:24px; display:flex; gap:12px;">
                        <input type="text" wire:model="newInternalComment" class="form-in" placeholder="Aggiungi una nota o istruzioni per l'AI..." class="u-flex-1">
                        <button type="button" wire:click="addInternalComment({{ $editingPost->id }})" class="btn btn-s" style="padding:0 16px; display:flex; align-items:center; gap:6px;">
                            <i data-lucide="message-square" class="u-icon-sm"></i> Inserisci Nota
                        </button>
                    </div>

                    <div style="display:flex; gap:10px; flex-wrap:wrap; padding-top:20px; border-top:1px solid var(--line);">
                        
                        @if($generatedReviewLink)
                            <div class="cmp-review-link-box">
                                <div style="color:var(--green); font-weight:600; font-size:14px; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                                    <i data-lucide="check-circle" class="u-icon-md"></i> Inviato al Cliente
                                </div>
                                <div class="u-flex u-gap-sm">
                                    <input type="text" value="{{ $generatedReviewLink }}" readonly class="form-in" style="flex:1; background:var(--bg); border:1px solid var(--line); font-size:12px; padding:6px 10px;" id="review-link-{{ $editingPost->id }}">
                                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('review-link-{{ $editingPost->id }}').value); alert('Link copiato!');" class="btn btn-s" style="padding:6px 12px; font-size:12px;">Copia Link</button>
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
                                <button type="button" wire:click="sendToClient({{ $editingPost->id }})" class="btn btn-s" class="btn btn-s btn-purple u-flex-center u-gap-xs">
                                    <i data-lucide="send" class="u-icon-sm"></i> Invia al Cliente
                                </button>
                            @endif

                            @if(!in_array($editingPost->status->value, ['approved', 'published', 'cancelled']))
                                <button type="button" wire:click="approvePost({{ $editingPost->id }})" class="btn btn-s" class="btn btn-s btn-green u-flex-center u-gap-xs">
                                    <i data-lucide="check" class="u-icon-sm"></i> Approva Definitivamente
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            @endif

          </form>
        </div>

        <div style="padding:16px 24px; border-top:1px solid var(--line); background:var(--bg1); display:flex; justify-content:space-between; align-items:center;">
          <div>
            @if($editingPost)
              <button type="button" wire:click="deletePost({{ $editingPost->id }})" wire:confirm="Sei sicuro di voler eliminare questo post?" class="btn btn-s" style="color:var(--red); border-color:transparent; padding:4px 8px; display:inline-flex; align-items:center; gap:4px;">
                <i data-lucide="trash-2" class="u-icon-md"></i> Elimina
              </button>
            @endif
          </div>
          
          <div class="flex gap-2">
            <button type="button" wire:click="closePostModal" class="btn btn-s">Annulla</button>
            
            <button type="button" wire:click="savePost" class="btn btn-s">
              <span wire:loading.remove wire:target="savePost">Salva Bozza</span>
              <span wire:loading wire:target="savePost">Salvataggio...</span>
            </button>
            <button type="button" wire:click="saveAndSubmitToN8n" class="btn btn-p" @if(!$form['ai_analysis_enabled']) title="Richiede Analisi AI attiva" @endif style="display:inline-flex; align-items:center; gap:4px;">
              <i data-lucide="sparkles" class="u-icon-md"></i>
              <span wire:loading.remove wire:target="saveAndSubmitToN8n">Salva e Invia a N8n</span>
              <span wire:loading wire:target="saveAndSubmitToN8n">Invio in corso...</span>
            </button>
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
          <div style="margin-bottom:16px; border:1px solid var(--line); border-radius:8px; padding:12px; background:var(--bg2);">
            <div style="font-size:12px; font-weight:bold; color:var(--text); margin-bottom:8px; text-transform:uppercase;">Periodi da fatturare</div>
            @foreach($pendingPeriodsForInvoice as $period)
            <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text2); margin-bottom:6px; cursor:pointer;">
              <input type="checkbox" wire:model="invoiceForm.period_ids" value="{{ $period['id'] }}" class="u-accent-blue">
              <span>{{ $period['description'] }} - <strong style="color:var(--text);">€ {{ number_format($period['amount'], 2, ',', '.') }}</strong></span>
            </label>
            @endforeach
          </div>
          @endif

          @if(count($pendingExtrasForInvoice) > 0)
          <div style="margin-bottom:16px; border:1px solid var(--line); border-radius:8px; padding:12px; background:var(--bg2);">
            <div style="font-size:12px; font-weight:bold; color:var(--text); margin-bottom:8px; text-transform:uppercase;">Extra da fatturare</div>
            @foreach($pendingExtrasForInvoice as $extra)
            <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text2); margin-bottom:6px; cursor:pointer;">
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
          <button type="button" wire:click="generateInvoice" class="btn btn-p" class="btn btn-green">Genera Fattura</button>
        </div>
      </div>
    </div>
  @endif

</div>


