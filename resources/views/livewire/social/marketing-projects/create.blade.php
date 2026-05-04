<div>
    <div class="mb-4">
        <x-page-header eyebrow="Social" meta="Wizard creazione campagna e piano editoriale">
            <x-slot:title><strong>Nuova Campagna Marketing</strong></x-slot:title>
            <x-slot:actions>
                <a href="{{ route('marketing-projects.index') }}" wire:navigate class="btn btn-g">← Indietro</a>
            </x-slot:actions>
        </x-page-header>
    </div>

    {{-- Progress Indicator --}}
    <div class="mkt-wizard-progress">
        @for($i = 1; $i <= 5; $i++)
            <div class="mkt-wizard-step {{ $step >= $i ? 'active' : 'inactive' }}"
                 style="{{ ($i == 4 && $campaign_structure !== 'plan') ? 'display: none;' : '' }}"></div>
        @endfor
    </div>

    <x-panel padded="true" title="Step {{ $step }}: {{ 
        $step == 1 ? 'Seleziona Cliente' : (
        $step == 2 ? 'Tipo di Campagna' : (
        $step == 3 ? 'Brief e Dettagli' : (
        $step == 4 ? 'Piano Editoriale' : 'Riepilogo'
        ))) 
    }}">
    
        <form wire:submit.prevent="save">
            
            {{-- STEP 1 --}}
            @if($step == 1)
                <div wire:key="step-1">
                    <div class="form-g mb-3" @client-updated="$wire.set('client_id', $event.detail)">
                        <label class="form-lbl">Cliente *</label>
                        <div wire:ignore>
                            <x-client-autocomplete 
                                name="client_id" 
                                :value="$client_id" 
                                :required="true" 
                            />
                        </div>
                        @error('client_id') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                    </div>

                    @if($client_id)
                    <div class="form-g mb-3">
                        <label class="form-lbl">Commessa Associata *</label>
                        <div style="display:flex; gap:20px; margin-bottom:15px;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="radio" wire:model.live="project_mode" value="existing">
                                <span>Usa commessa esistente</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="radio" wire:model.live="project_mode" value="new">
                                <span>Crea nuova commessa</span>
                            </label>
                        </div>
                        @error('project_mode') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror

                        @if($project_mode === 'existing')
                            @if(count($projects) > 0)
                                <select wire:model="project_id" class="form-in" required>
                                    <option value="">Seleziona...</option>
                                    @foreach($projects as $proj)
                                        <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                                    @endforeach
                                </select>
                                @error('project_id') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                            @else
                                <div style="padding:15px; background:var(--bg); border:1px solid var(--line2); border-radius:var(--r); color:var(--text2); font-size:14px;">
                                    Questo cliente non ha commesse attive. Seleziona "Crea nuova commessa".
                                </div>
                            @endif
                        @else
                            <div style="padding:15px; background:var(--bg2); border:1px solid var(--line2); border-radius:var(--r);">
                                <div class="form-g mb-3">
                                    <label class="form-lbl">Nome Commessa *</label>
                                    <input type="text" wire:model="new_project_name" class="form-in" placeholder="Es. Commessa Primavera 2026">
                                    @error('new_project_name') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-g mb-3">
                                    <label class="form-lbl">Descrizione (Opzionale)</label>
                                    <textarea wire:model="new_project_description" class="form-in" rows="2"></textarea>
                                </div>
                                <div class="g-2col">
                                    <div class="form-g">
                                        <label class="form-lbl">Budget (Opzionale)</label>
                                        <input type="number" step="0.01" wire:model="new_project_budget" class="form-in" placeholder="0.00">
                                    </div>
                                    <div class="form-g">
                                        <label class="form-lbl">Scadenza (Opzionale)</label>
                                        <input type="date" wire:model="new_project_deadline" class="form-in">
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    @endif
                </div>
            @endif

            {{-- STEP 2 --}}
            @if($step == 2)
                <div wire:key="step-2">
                    <h4 style="margin-bottom:15px; font-size:16px; font-family:var(--sans); color:var(--text);">1. Cosa vendiamo? (Servizio)</h4>
                    <div class="form-g mb-4">
                        <select wire:model.live="service_type" class="form-in" required>
                            <option value="other">Altro (Generico)</option>
                            <option value="social_management">Social Media Management</option>
                            <option value="ads">Advertising (Ads)</option>
                            <option value="seo">SEO / Posizionamento</option>
                            <option value="branding">Branding / Grafica</option>
                            <option value="editorial_plan">Solo Piano Editoriale (Legacy)</option>
                        </select>
                        @error('service_type') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                    </div>

                    <h4 style="margin-bottom:15px; font-size:16px; font-family:var(--sans); color:var(--text);">2. Come è strutturata nel tempo?</h4>
                    <div class="mkt-type-grid">
                        <div wire:click="$set('campaign_structure', 'one_shot')" class="mkt-type-card {{ $campaign_structure == 'one_shot' ? 'selected' : '' }}">
                            <h3>Una Tantum</h3>
                            <p class="mkt-type-desc">Post singolo, lancio isolato o lavoro su consegna secca.</p>
                        </div>
                        <div wire:click="$set('campaign_structure', 'recurring')" class="mkt-type-card {{ $campaign_structure == 'recurring' ? 'selected' : '' }}">
                            <h3>Ricorrente / Mantenimento</h3>
                            <p class="mkt-type-desc">Attività mensile continua senza necessitare del tool piano editoriale interno.</p>
                        </div>
                        <div wire:click="$set('campaign_structure', 'plan')" class="mkt-type-card {{ $campaign_structure == 'plan' ? 'selected' : '' }}">
                            <h3>Piano Editoriale Strutturato</h3>
                            <p class="mkt-type-desc">Richiede l'impostazione di un calendario di slot di pubblicazione precisi.</p>
                        </div>
                    </div>
                    @error('campaign_structure') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                </div>
            @endif

            {{-- STEP 3 --}}
            @if($step == 3)
                <div wire:key="step-3">
                    <div class="form-g mb-3">
                        <label class="form-lbl">Titolo Progetto *</label>
                        <input type="text" wire:model="title" class="form-in" placeholder="Es. Lancio prodotto XYZ" required>
                        @error('title') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-g mb-3">
                        <label class="form-lbl">Briefing per l'AI / Creativi *</label>
                        <textarea wire:model="brief" class="form-in" rows="5" placeholder="Descrivi l'obiettivo, il tono di voce, il target..." required></textarea>
                        @error('brief') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                    </div>

                    {{-- DINAMICO IN BASE A SERVICE TYPE --}}
                    @if($service_type === 'social_management')
                        <div style="padding:15px; background:var(--bg2); border:1px solid var(--line2); border-radius:var(--r); margin-bottom:15px;">
                            <h4 style="font-size:14px; margin-bottom:10px;">Opzioni Social Management</h4>
                            
                            <div class="form-g mb-3">
                                <label class="form-lbl">Frequenza Post *</label>
                                <input type="text" wire:model="service_options.frequency" class="form-in" placeholder="Es. 3 post a settimana" required>
                                @error('service_options.frequency') <span style="color:var(--red); font-size:12px; display:block;">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-g mb-3">
                                <label class="form-lbl">Piattaforme *</label>
                                <div class="mkt-checkbox-group">
                                    @foreach($availablePlatforms as $plat)
                                        <label class="mkt-checkbox-label">
                                            <input type="checkbox" wire:model.live="service_options.platforms" value="{{ $plat }}"> {{ ucfirst($plat) }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('service_options.platforms') <span style="color:var(--red); font-size:12px; display:block;">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    @elseif($service_type === 'ads')
                        <div style="padding:15px; background:var(--bg2); border:1px solid var(--line2); border-radius:var(--r); margin-bottom:15px;">
                            <h4 style="font-size:14px; margin-bottom:10px;">Opzioni Advertising</h4>
                            
                            <div class="form-g mb-3">
                                <label class="form-lbl">Budget Ads (€) *</label>
                                <input type="number" wire:model="service_options.budget" class="form-in" placeholder="Es. 500" required>
                                @error('service_options.budget') <span style="color:var(--red); font-size:12px; display:block;">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-g mb-3">
                                <label class="form-lbl">Piattaforme Ads *</label>
                                <div class="mkt-checkbox-group">
                                    @foreach($availablePlatforms as $plat)
                                        <label class="mkt-checkbox-label">
                                            <input type="checkbox" wire:model.live="service_options.platforms" value="{{ $plat }}"> {{ ucfirst($plat) }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('service_options.platforms') <span style="color:var(--red); font-size:12px; display:block;">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    @endif

                    {{-- FINE DINAMICO --}}

                    {{-- MATERIALE DI RIFERIMENTO --}}
                    <h4 style="margin-bottom:15px; font-size:16px; font-family:var(--sans); color:var(--text);">Materiale di riferimento</h4>
                    <div style="padding:15px; background:var(--bg2); border:1px solid var(--line2); border-radius:var(--r); margin-bottom:15px;">
                        
                        <div class="form-g mb-4">
                            <label class="form-lbl">Carica dal computer</label>
                            <input type="file" wire:model="uploaded_media" multiple class="form-in" accept="image/*">
                            <div style="font-size:11px; color:var(--text3); margin-top:4px;">Max 10MB per file. Solo immagini (jpg, png, webp).</div>
                            @error('uploaded_media.*') <span style="color:var(--red); font-size:12px; display:block;">{{ $message }}</span> @enderror
                            
                            @if($uploaded_media)
                                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
                                    @foreach($uploaded_media as $idx => $file)
                                        <div style="position:relative; width:80px; height:80px; border-radius:var(--r); overflow:hidden; border:1px solid var(--line);">
                                            @php
                                                $tempUrl = null;
                                                try {
                                                    $tempUrl = $file->temporaryUrl();
                                                } catch(\Exception $e) {}
                                            @endphp
                                            @if($tempUrl)
                                                <img src="{{ $tempUrl }}" style="width:100%; height:100%; object-fit:cover;">
                                            @else
                                                <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:var(--bg3); font-size:10px; color:var(--text3);">{{ strtoupper($file->getClientOriginalExtension()) }}</div>
                                            @endif
                                            <button type="button" wire:click="removeUploadedMedia({{ $idx }})" style="position:absolute; top:2px; right:2px; background:var(--red); color:white; border:none; border-radius:50%; width:20px; height:20px; font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="form-g mb-3">
                            <label class="form-lbl">Importa da Nextcloud</label>
                            <div style="display:flex; gap:10px; margin-bottom:10px;">
                                <input type="text" wire:model="nextcloud_path" class="form-in" placeholder="/Cartella" disabled>
                                <button type="button" wire:click="browseNextcloud(nextcloud_path)" class="btn-sec" style="padding:8px 15px;">Esplora</button>
                            </div>
                            @error('nextcloud_files') <span style="color:var(--red); font-size:12px; display:block; margin-bottom:10px;">{{ $message }}</span> @enderror

                            @if(!empty($nextcloud_files))
                                <div style="max-height:200px; overflow-y:auto; border:1px solid var(--line); border-radius:var(--r); background:var(--bg); padding:10px;">
                                    @if($nextcloud_path !== '/')
                                        <div wire:click="browseNextcloud('{{ dirname($nextcloud_path) }}')" style="cursor:pointer; padding:5px; border-bottom:1px solid var(--line); color:var(--text2); font-size:13px;">
                                            .. (Su)
                                        </div>
                                    @endif
                                    @foreach($nextcloud_files as $ncFile)
                                        <div style="display:flex; align-items:center; gap:10px; padding:5px; border-bottom:1px solid var(--line); font-size:13px;">
                                            @if($ncFile['is_dir'])
                                                <div wire:click="browseNextcloud('{{ $ncFile['path'] }}')" style="cursor:pointer; color:var(--blue); flex:1;">
                                                    [Dir] {{ $ncFile['name'] }}
                                                </div>
                                            @else
                                                <div style="flex:1;">
                                                    <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                                                        <input type="checkbox" 
                                                            wire:click="toggleNextcloudFile('{{ $ncFile['path'] }}', '{{ $ncFile['name'] }}', {{ $ncFile['size'] }}, '{{ $ncFile['content_type'] }}')"
                                                            {{ collect($selected_nextcloud_files)->contains('path', $ncFile['path']) ? 'checked' : '' }}>
                                                        {{ $ncFile['name'] }} <span style="color:var(--text3); font-size:11px;">({{ round($ncFile['size'] / 1024) }} KB)</span>
                                                    </label>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($selected_nextcloud_files))
                                <div style="margin-top:10px;">
                                    <strong style="font-size:12px; color:var(--text2);">Selezionati da Nextcloud:</strong>
                                    <ul style="font-size:12px; color:var(--text); margin-top:5px; padding-left:20px;">
                                        @foreach($selected_nextcloud_files as $idx => $sFile)
                                            <li style="margin-bottom:4px; display:flex; align-items:center; justify-content:space-between; max-width:300px;">
                                                <span>{{ $sFile['name'] }} ({{ round($sFile['size'] / 1024) }} KB)</span>
                                                <button type="button" wire:click="removeNextcloudFile({{ $idx }})" style="background:transparent; color:var(--red); border:none; cursor:pointer; font-size:14px; padding:0 5px;">&times;</button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>

                    <hr style="border:none; border-top:1px solid var(--line); margin:20px 0;">
                    <h4 style="margin-bottom:15px; font-size:16px; font-family:var(--sans); color:var(--text);">Produzione foto/video</h4>
                    <div class="form-g mb-3">
                        <label class="form-lbl">Questa campagna richiede foto o video?</label>
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="radio" wire:model.live="shooting_mode" value="none">
                                <span>No</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="radio" wire:model.live="shooting_mode" value="existing">
                                <span>Sì, collega shooting esistente</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="radio" wire:model.live="shooting_mode" value="new">
                                <span>Sì, crea nuova richiesta shooting</span>
                            </label>
                        </div>
                        @error('shooting_mode') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                    </div>

                    @if($shooting_mode === 'existing')
                        <div style="padding:15px; background:var(--bg); border:1px solid var(--line2); border-radius:var(--r); margin-bottom:15px;">
                            <label class="form-lbl">Shooting Esistente *</label>
                            @if(count($availableShoots) > 0)
                                <select wire:model="existing_shoot_id" class="form-in" required>
                                    <option value="">Seleziona...</option>
                                    @foreach($availableShoots as $shoot)
                                        <option value="{{ $shoot->id }}">
                                            {{ $shoot->title }} - 
                                            {{ $shoot->photographer?->name ?? 'Da assegnare' }} - 
                                            {{ $shoot->status->label() }} 
                                            ({{ $shoot->created_at->format('d/m/Y') }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('existing_shoot_id') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                            @else
                                <div style="color:var(--text2); font-size:13px;">Nessuno shooting disponibile per questa commessa.</div>
                            @endif
                        </div>
                    @elseif($shooting_mode === 'new')
                        <div style="padding:15px; background:var(--bg2); border:1px solid var(--line2); border-radius:var(--r); margin-bottom:15px;">
                            <div class="g-2col mb-3">
                                <div class="form-g">
                                    <label class="form-lbl">Fotografo Assegnato *</label>
                                    <select wire:model="photographer_id" class="form-in" required>
                                        <option value="">Seleziona fotografo...</option>
                                        @foreach($photographers as $photographer)
                                            <option value="{{ $photographer->id }}">{{ $photographer->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('photographer_id') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-g">
                                    <label class="form-lbl">Location *</label>
                                    <input type="text" wire:model="shooting_location" class="form-in" placeholder="Es. Sede cliente o Indirizzo" required>
                                    @error('shooting_location') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="form-g mb-3">
                                <label class="form-lbl">Brief Shooting *</label>
                                <textarea wire:model="shooting_brief" class="form-in" rows="3" placeholder="Descrivi cosa deve fotografare/riprendere..." required></textarea>
                                @error('shooting_brief') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                            </div>

                            <div class="mb-2">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                    <label class="form-lbl" style="margin:0;">Date/Slot Proposti *</label>
                                    <button type="button" wire:click="addShootingSlot" class="btn btn-sm btn-secondary">+ Slot</button>
                                </div>
                                @error('shooting_proposed_slots') <span style="color:var(--red); font-size:12px; display:block; margin-bottom:10px;">{{ $message }}</span> @enderror
                                
                                @foreach($shooting_proposed_slots as $index => $slot)
                                    <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
                                        <input type="date" wire:model="shooting_proposed_slots.{{ $index }}.date" class="form-in" required>
                                        <select wire:model="shooting_proposed_slots.{{ $index }}.period" class="form-in" required>
                                            <option value="morning">Mattina (09:00 - 13:00)</option>
                                            <option value="afternoon">Pomeriggio (14:00 - 18:00)</option>
                                            <option value="full_day">Giornata Intera</option>
                                        </select>
                                        <button type="button" wire:click="removeShootingSlot({{ $index }})" class="btn btn-sm" style="color:var(--red); border:1px solid var(--red)40;">&times;</button>
                                    </div>
                                    @error('shooting_proposed_slots.'.$index.'.date') <span style="color:var(--red); font-size:12px; display:block;">{{ $message }}</span> @enderror
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @php
                        $requiresMeta = collect($service_options['platforms'] ?? [])->intersect(['facebook', 'instagram'])->isNotEmpty();
                        $status = $this->client_social_status;
                        $isMetaReady = $status['is_meta_ready'] ?? false;
                    @endphp
                    @if($requiresMeta && !$isMetaReady)
                        <div style="margin-top: 20px;">
                            <x-alert type="warning" icon="lock" title="Stato Accessi Social">
                                Meta Business è richiesto per le piattaforme scelte, ma il cliente non ha completato il collegamento. Puoi salvare la campagna, ma non potrai inviarla a n8n finché l'accesso non sarà risolto.
                            </x-alert>
                        </div>
                    @endif
                </div>
            @endif

            {{-- STEP 4 --}}
            @if($step == 4)
                <div wire:key="step-4">
                    <div class="g-2col mb-3">
                        <div class="form-g">
                            <label class="form-lbl">Data Inizio</label>
                            <input type="date" wire:model="start_date" class="form-in">
                        </div>
                        <div class="form-g">
                            <label class="form-lbl">Data Fine</label>
                            <input type="date" wire:model="end_date" class="form-in">
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="mkt-header">
                            <label class="form-lbl" style="margin:0;">Slot di Pubblicazione</label>
                            <button type="button" wire:click="addSlot" class="btn btn-sm btn-secondary">+ Aggiungi Slot</button>
                        </div>

                        @foreach($planSlots as $index => $slot)
                            <div class="mkt-slot-card" wire:key="slot-{{ $index }}">
                                <button type="button" wire:click="removeSlot({{ $index }})" class="mkt-slot-remove">&times; Rimuovi</button>
                                
                                <div class="g-2col mb-2">
                                    <div class="form-g">
                                        <label class="form-lbl">Data *</label>
                                        <input type="date" wire:model="planSlots.{{ $index }}.date" class="form-in" required>
                                    </div>
                                    <div class="form-g">
                                        <label class="form-lbl">Orario *</label>
                                        <input type="time" wire:model="planSlots.{{ $index }}.time" class="form-in" required>
                                    </div>
                                </div>
                                <div class="form-g mb-2">
                                    <label class="form-lbl">Topic (Opzionale)</label>
                                    <input type="text" wire:model="planSlots.{{ $index }}.topic" class="form-in" placeholder="Es. Focus sui benefici del prodotto">
                                </div>
                                <div class="form-g">
                                    <label class="form-lbl" style="font-size:12px;">Piattaforme</label>
                                    <div class="mkt-slot-platforms">
                                        @foreach($availablePlatforms as $plat)
                                            <label class="mkt-checkbox-label">
                                                <input type="checkbox" wire:model="planSlots.{{ $index }}.platforms" value="{{ $plat }}"> {{ ucfirst($plat) }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        @if(count($planSlots) === 0)
                            <p class="mkt-slot-empty">Nessuno slot configurato. Aggiungine almeno uno per l'approvazione.</p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- STEP 5 --}}
            @if($step == 5)
                <div wire:key="step-5">
                    <div class="mkt-summary-card">
                        <h3 class="mkt-summary-title">Riepilogo Campagna</h3>
                        
                        <table class="mkt-summary-table">
                            <tr><td class="label">Titolo</td><td><strong>{{ $title }}</strong></td></tr>
                            <tr><td class="label">Servizio</td><td>{{ ucfirst(str_replace('_', ' ', $service_type)) }}</td></tr>
                            <tr><td class="label">Struttura</td><td>{{ ucfirst(str_replace('_', ' ', $campaign_structure)) }}</td></tr>
                            @if(isset($service_options['platforms']))
                            <tr><td class="label">Piattaforme base</td><td>{{ implode(', ', array_map('ucfirst', $service_options['platforms'])) }}</td></tr>
                            @endif
                            @if($campaign_structure === 'plan')
                                <tr><td class="label">Slot configurati</td><td>{{ count($planSlots) }}</td></tr>
                            @endif
                        </table>
                        
                        <div style="margin-top:20px;">
                            <h4 style="font-size:14px; margin-bottom:5px;">Brief:</h4>
                            <p style="font-size:13px; color:var(--text2); white-space:pre-wrap;">{{ $brief }}</p>
                        </div>
                    </div>

                    <div class="mkt-alert-info">
                        <i class="fa fa-info-circle"></i> La campagna verrà salvata in stato <strong>Bozza</strong>. Potrai inviarla a n8n in un secondo momento dalla pagina di dettaglio.
                    </div>
                </div>
            @endif

            {{-- Buttons --}}
            <div class="mkt-wizard-footer">
                @if($step > 1)
                    <button type="button" wire:click="prevStep" wire:target="prevStep" class="btn btn-secondary" wire:loading.attr="disabled">Indietro</button>
                @else
                    <div></div>
                @endif

                @if($step < 5)
                    <button type="button" wire:click="nextStep" wire:target="nextStep" class="btn btn-g" wire:loading.attr="disabled">Avanti</button>
                @else
                    <button type="submit" wire:target="save" class="btn btn-success" wire:loading.attr="disabled">Salva Campagna</button>
                @endif
            </div>

        </form>
    </x-panel>
</div>
