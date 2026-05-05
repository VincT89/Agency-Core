<div>
  <div style="margin-bottom: 20px;">
      <a href="{{ route('marketing-campaigns.index') }}" wire:navigate class="btn btn-g" style="font-size:12px; padding:6px 12px; display:inline-flex; align-items:center; gap:6px;">
          <i data-lucide="arrow-left" style="width:14px; height:14px;"></i> Torna ai progetti
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
      <x-badge :status="$campaign->status->value" :label="$campaign->status->label()" />
    </x-slot>
  </x-page-header>

  <div style="display:flex; gap:24px; align-items:flex-start;">
    
    {{-- Aside Box: Posts da Programmare --}}
    <div style="width:340px; flex-shrink:0;">
      <div class="panel" style="overflow:hidden; display:flex; flex-direction:column; max-height:calc(100vh - 180px);">
        <div style="padding:16px 20px; display:flex; justify-content:space-between; align-items:center; background:#111; color:#fff;">
          <div style="font-size:15px; font-weight:bold;">Post da Programmare</div>
          <button type="button" class="btn btn-p" style="padding:4px 8px; font-size:11px; display:inline-flex; align-items:center; justify-content:center; gap:4px; line-height:1;" wire:click="openPostModal()">
            <i data-lucide="plus" style="width:14px; height:14px;"></i> Nuovo
          </button>
        </div>
        
        <div style="flex:1; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:16px; background:var(--bg2);">
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
                    <i data-lucide="trash-2" style="width:14px; height:14px;"></i>
                  </button>
                </div>
              </div>

            </div>
          @empty
            <div style="text-align:center; padding:40px 0; color:var(--text3); display:flex; flex-direction:column; align-items:center;">
              <i data-lucide="inbox" style="width:40px; height:40px; margin-bottom:8px; opacity:0.5;"></i>
              <p style="font-size:13px;">Nessun post da programmare.</p>
              <p style="font-size:11px; margin-top:4px;">Clicca su "Nuovo" per iniziare.</p>
            </div>
          @endforelse
        </div>
      </div>
    </div>

    {{-- Main Box: Campaign Info & Calendar --}}
    <div style="flex:1; display:flex; flex-direction:column; gap:24px; min-width:0;">
      
      @if($campaign->description)
        <div class="panel" style="padding:20px;">
          <div class="font-bold mb-2">Descrizione Progetto</div>
          <p class="t-body text-base">{{ $campaign->description }}</p>
        </div>
      @endif

      <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:24px;">
        <div class="panel" style="padding:20px;">
          <div class="t-label mb-1">Periodo</div>
          <div class="font-bold">
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
        <div class="panel" style="padding:20px;">
          <div class="t-label mb-1">Budget Mensile</div>
          <div class="font-bold">
            {{ $campaign->monthly_fee ? '€ ' . number_format($campaign->monthly_fee, 2, ',', '.') : 'Non definito' }}
          </div>
        </div>
        <div class="panel" style="padding:20px;">
          <div class="t-label mb-1">Post Totali</div>
          <div class="font-bold">{{ $totalPostsCount }}</div>
        </div>
      </div>

      {{-- Calendario Editoriale --}}
      <div class="panel" style="overflow:hidden;">
        <div style="padding:16px 20px; display:flex; justify-content:space-between; align-items:center; background:#111; color:#fff;">
          <div style="font-size:16px; font-weight:bold;">Calendario Programmazione</div>
          <div style="display:flex; align-items:center; gap:16px;">
            <button type="button" class="btn btn-s" wire:click="previousMonth" style="padding:4px 8px;">&larr;</button>
            <div style="font-weight:bold; width:150px; text-align:center;">{{ Str::ucfirst($monthName) }}</div>
            <button type="button" class="btn btn-s" wire:click="nextMonth" style="padding:4px 8px;">&rarr;</button>
          </div>
        </div>
        
        <div style="display:grid; grid-template-columns:repeat(7, 1fr); text-align:center; border-bottom:1px solid var(--line); background:var(--bg2);">
            <div style="padding:8px; font-size:11px; font-weight:bold; color:var(--text3);">LUN</div>
            <div style="padding:8px; font-size:11px; font-weight:bold; color:var(--text3);">MAR</div>
            <div style="padding:8px; font-size:11px; font-weight:bold; color:var(--text3);">MER</div>
            <div style="padding:8px; font-size:11px; font-weight:bold; color:var(--text3);">GIO</div>
            <div style="padding:8px; font-size:11px; font-weight:bold; color:var(--text3);">VEN</div>
            <div style="padding:8px; font-size:11px; font-weight:bold; color:var(--text3);">SAB</div>
            <div style="padding:8px; font-size:11px; font-weight:bold; color:var(--text3);">DOM</div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(7, 1fr); grid-auto-rows:minmax(100px, auto); background:var(--line); gap:1px; border:1px solid var(--line); border-top:none; border-bottom:none; border-left:none; border-right:none;">
          @foreach($calendarGrid as $row)
            @foreach($row as $col)
              <div style="padding:8px; background:{{ $col && $col['isToday'] ? 'var(--bg2)' : 'var(--bg)' }};">
                @if($col)
                  <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <span style="font-size:12px; font-weight:{{ $col['isToday'] ? 'bold' : 'normal' }}; color:{{ $col['isToday'] ? 'var(--blue)' : 'inherit' }}">{{ $col['day'] }}</span>
                    <button type="button" style="color:var(--text3); cursor:pointer; background:none; border:none; padding:0;" wire:click="openPostModal(null, '{{ $col['date'] }}')">
                        <i data-lucide="plus" style="width:12px; height:12px;"></i>
                    </button>
                  </div>
                  <div style="display:flex; flex-direction:column; gap:4px;">
                    @foreach($col['posts'] as $p)
                      <div wire:click="openPostModal({{ $p->id }})" style="padding:4px 6px; font-size:10px; border-radius:4px; background:var(--bg1); border:1px solid var(--line2); cursor:pointer; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $p->title }}">
                        <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:{{ $p->status->value === 'draft' ? '#9ca3af' : ($p->status->value === 'published' ? '#10b981' : '#3b82f6') }}; margin-right:4px;"></span>
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
        <div class="panel" style="padding:20px; background:rgba(253, 224, 71, 0.2); border-color:rgba(253, 224, 71, 0.5);">
          <div class="t-label mb-2" style="color:#854d0e;">Note Interne</div>
          <div class="t-body">{{ $campaign->notes }}</div>
        </div>
      @endif

    </div>
  </div>

  {{-- Post Modal --}}
  @if($showPostModal)
    <style>
      .ubuntu-font, .ubuntu-font input, .ubuntu-font button, .ubuntu-font textarea, .ubuntu-font select {
        font-family: 'Ubuntu', sans-serif;
      }
    </style>
    <div class="ubuntu-font" style="position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); font-family: 'Ubuntu', sans-serif;">
      <div style="background:var(--bg); width:100%; max-width:650px; border-radius:12px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden; display:flex; flex-direction:column; max-height:90vh;">
        
        <div style="padding:16px 24px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; background:var(--bg1);">
          <h3 style="font-weight:bold; font-size:18px; margin:0;">{{ $editingPost ? 'Modifica Post' : 'Nuovo Post' }}</h3>
          <button type="button" wire:click="closePostModal" style="color:var(--text3); background:none; border:none; cursor:pointer; padding:0;">
            <i data-lucide="x" style="width:20px; height:20px;"></i>
          </button>
        </div>

        <div style="padding:24px; overflow-y:auto; flex:1;" class="custom-scrollbar">
          <form style="display:flex; flex-direction:column; gap:20px;">
            
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

            <div style="display:flex; gap:16px;">
              <div class="form-g mb-0" style="flex:1;">
                <label class="form-lbl">Tipo Contenuto <span style="color:var(--red)">*</span></label>
                <select class="form-sel" wire:model="form.content_type" required>
                  <option value="post">Post</option>
                  <option value="story">Story</option>
                  <option value="reel">Reel</option>
                </select>
                @error('form.content_type') <span class="form-err">{{ $message }}</span> @enderror
              </div>

              <div class="form-g mb-0" style="flex:1;">
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

            <div style="display:flex; gap:16px;">
              <div class="form-g mb-0" style="flex:1;">
                <label class="form-lbl">Data Pubblicazione</label>
                <input type="date" class="form-in" wire:model="form.scheduled_date">
                @error('form.scheduled_date') <span class="form-err">{{ $message }}</span> @enderror
              </div>
              <div class="form-g mb-0" style="flex:1;">
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
                  <div x-data="{ expanded: false }" style="margin-top:16px; padding-top:16px; border-top:1px solid var(--line);">
                      <button type="button" @click="expanded = !expanded" style="display:flex; justify-content:space-between; align-items:center; width:100%; text-align:left; font-weight:bold; font-size:13px; color:var(--text2); background:none; border:none; padding:0; cursor:pointer;">
                          <span><i data-lucide="settings" style="width:14px; height:14px; margin-right:6px; display:inline-block; vertical-align:middle;"></i> Impostazioni Identità Cliente per AI</span>
                          <i data-lucide="chevron-down" x-show="!expanded" style="width:16px; height:16px;"></i>
                          <i data-lucide="chevron-up" x-show="expanded" style="width:16px; height:16px; display:none;"></i>
                      </button>

                      <div x-show="expanded" style="display:none; margin-top:16px; flex-direction:column; gap:16px;">
                          {{-- Logo --}}
                          <div style="background:var(--bg2); padding:12px; border-radius:6px; border:1px solid var(--line);">
                              <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer; color:var(--text); font-weight:bold;">
                                  <input type="checkbox" wire:model.live="include_client_logo" style="cursor:pointer">
                                  Includi logo cliente nel briefing
                              </label>

                              @if($campaign->client->logo_path)
                                  <div x-show="$wire.include_client_logo" style="margin-top:12px; padding-top:12px; border-top:1px solid var(--line); font-size:12px; color:var(--text3);">
                                      Logo attuale:<br>
                                      <img src="{{ $campaign->client->logo_url }}" alt="Logo Cliente" style="height:30px; border-radius:4px; margin-top:4px; border:1px solid var(--line);">
                                  </div>
                              @else
                                  <div x-show="$wire.include_client_logo" style="margin-top:12px; padding-top:12px; border-top:1px solid var(--line);">
                                      <div style="font-size:11px; color:var(--orange); margin-bottom:8px;">Nessun logo presente nella scheda cliente. Caricane uno per il task.</div>
                                      <input type="file" wire:model="runtime_logo" class="form-in" style="font-size:12px; padding:4px;" accept="image/jpeg,image/png,image/webp">
                                      @error('runtime_logo') <div style="color:var(--red);font-size:11px;margin-top:2px;">{{ $message }}</div> @enderror
                                      
                                      <label style="display:flex; align-items:center; gap:8px; margin-top:8px; font-size:11px; color:var(--text3); cursor:pointer;">
                                          <input type="checkbox" wire:model="save_runtime_logo_to_client" style="cursor:pointer">
                                          Salva e imposta come logo ufficiale
                                      </label>
                                  </div>
                              @endif
                          </div>

                          {{-- Header / Activity --}}
                          <div style="background:var(--bg2); padding:12px; border-radius:6px; border:1px solid var(--line);">
                              <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer; color:var(--text); font-weight:bold;">
                                  <input type="checkbox" wire:model.live="include_client_header" style="cursor:pointer">
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
                                      <div style="font-size:11px; color:var(--orange); margin-bottom:8px;">Nessuna descrizione presente. Scrivine una.</div>
                                      <textarea wire:model="runtime_activity_description" class="form-ta" style="font-size:12px; padding:8px; min-height:60px;" placeholder="Descrivi l'attività del cliente..."></textarea>
                                      @error('runtime_activity_description') <div style="color:var(--red);font-size:11px;margin-top:2px;">{{ $message }}</div> @enderror
                                      
                                      <label style="display:flex; align-items:center; gap:8px; margin-top:8px; font-size:11px; color:var(--text3); cursor:pointer;">
                                          <input type="checkbox" wire:model="save_runtime_activity_to_client" style="cursor:pointer">
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
                    <div style="display:flex; gap:16px;">
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
                                <i data-lucide="check-circle" style="width:14px; height:14px; display:inline-block; vertical-align:middle; margin-right:4px;"></i>
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
                                                <i data-lucide="folder" style="width:14px; height:14px; display:inline-block; vertical-align:middle; margin-right:4px;"></i> {{ $ncFile['name'] }}
                                            </div>
                                        @else
                                            <div style="flex:1;">
                                                <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                                                    <input type="radio" name="nc_file_sel"
                                                        wire:click="selectNextcloudFile('{{ $ncFile['path'] }}', '{{ $ncFile['name'] }}', {{ $ncFile['size'] }}, '{{ $ncFile['content_type'] }}')"
                                                        {{ ($selected_nextcloud_file['path'] ?? null) === $ncFile['path'] ? 'checked' : '' }}>
                                                    <i data-lucide="image" style="width:14px; height:14px; display:inline-block; vertical-align:middle; color:var(--text3);"></i>
                                                    {{ $ncFile['name'] }} <span style="color:var(--text3); font-size:11px;">({{ round($ncFile['size'] / 1024) }} KB)</span>
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
                <div class="panel" style="padding:16px; margin-bottom:0; margin-top:20px; border-color:var(--blue);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h4 style="font-weight:bold; font-size:14px; margin:0;">Versione Generata (v{{ $editingPost->currentVersion->version_number }})</h4>
                        <span style="font-size:11px; padding:2px 8px; border-radius:12px; background:var(--blue); color:white;">{{ $editingPost->status->label() }}</span>
                    </div>

                    <div style="font-size:13px; color:var(--text2); margin-bottom:12px;">
                        <strong>Titolo:</strong> {{ $editingPost->currentVersion->title }}<br>
                        <strong>Caption:</strong> <span style="white-space:pre-wrap;">{{ $editingPost->currentVersion->caption }}</span>
                    </div>

                    @if($editingPost->currentVersion->image_url)
                        <div style="margin-bottom:12px;">
                            <img src="{{ $editingPost->currentVersion->image_url }}" style="max-height:150px; border-radius:8px; border:1px solid var(--line);">
                        </div>
                    @endif

                    @if($editingPost->comments->count() > 0)
                        <div style="margin-bottom:16px; padding:12px; background:var(--bg2); border-radius:8px; border:1px solid var(--line);">
                            <strong style="font-size:12px; color:var(--text2);">Feedback Ricevuti:</strong>
                            <div style="margin-top:8px; display:flex; flex-direction:column; gap:8px;">
                                @foreach($editingPost->comments as $comment)
                                    <div style="font-size:12px; padding:8px; background:var(--bg); border:1px solid var(--line); border-radius:6px;">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:4px; color:var(--text3); font-size:11px;">
                                            <span>
                                                @if($comment->visibility === 'client')
                                                    <strong style="color:var(--orange);">[Cliente] {{ $comment->client_name }}</strong>
                                                @else
                                                    <strong>[Team] {{ $comment->user->name ?? 'Sistema' }}</strong>
                                                @endif
                                            </span>
                                            <span>{{ $comment->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div style="color:var(--text1);">{{ $comment->body }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        @if($editingPost->canRegenerate())
                            <button type="button" wire:click="regeneratePost({{ $editingPost->id }}, 'full')" class="btn btn-s" style="border-color:var(--blue); color:var(--blue);">
                                <i data-lucide="refresh-cw" style="width:14px; height:14px; margin-right:4px;"></i> Rigenera Tutto
                            </button>
                            <button type="button" wire:click="regeneratePost({{ $editingPost->id }}, 'caption')" class="btn btn-s">Rigenera Testo</button>
                            <button type="button" wire:click="regeneratePost({{ $editingPost->id }}, 'image')" class="btn btn-s">Rigenera Immagine</button>
                        @endif

                        @if(in_array($editingPost->status->value, ['generated', 'ready_for_client', 'client_changes_requested']))
                            <button type="button" wire:click="sendToClient({{ $editingPost->id }})" class="btn btn-s" style="background:var(--purple); color:white; border:none;">
                                <i data-lucide="send" style="width:14px; height:14px; margin-right:4px;"></i> Invia al Cliente
                            </button>
                        @endif

                        @if(!in_array($editingPost->status->value, ['approved', 'published', 'cancelled']))
                            <button type="button" wire:click="approvePost({{ $editingPost->id }})" class="btn btn-s" style="background:var(--green); color:white; border:none;">
                                <i data-lucide="check" style="width:14px; height:14px; margin-right:4px;"></i> Approva Definitivamente
                            </button>
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
                <i data-lucide="trash-2" style="width:16px; height:16px;"></i> Elimina
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
              <i data-lucide="sparkles" style="width:16px; height:16px;"></i>
              <span wire:loading.remove wire:target="saveAndSubmitToN8n">Salva e Invia a N8n</span>
              <span wire:loading wire:target="saveAndSubmitToN8n">Invio in corso...</span>
            </button>
          </div>
        </div>

      </div>
    </div>
  @endif

</div>

<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.hook('morph.updated', ({ el, component }) => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    });
</script>
