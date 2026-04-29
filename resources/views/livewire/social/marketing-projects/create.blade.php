<div>
    <div class="mb-4">
        <x-page-header eyebrow="Social" meta="Wizard creazione progetto e piano editoriale">
            <x-slot:title><strong>Nuovo Progetto Marketing</strong></x-slot:title>
            <x-slot:actions>
                <a href="{{ route('marketing-projects.index') }}" wire:navigate class="btn btn-g">← Indietro</a>
            </x-slot:actions>
        </x-page-header>
    </div>

    {{-- Progress Indicator --}}
    <div class="mkt-wizard-progress">
        @for($i = 1; $i <= 5; $i++)
            @if($i == 4 && $type == 'one_shot') @continue @endif
            <div class="mkt-wizard-step {{ $step >= $i ? 'active' : 'inactive' }}"></div>
        @endfor
    </div>

    <x-panel padded="true" title="Step {{ $step }}: {{ 
        $step == 1 ? 'Seleziona Cliente' : (
        $step == 2 ? 'Tipo di Progetto' : (
        $step == 3 ? 'Brief e Dettagli' : (
        $step == 4 ? 'Piano Editoriale' : 'Riepilogo'
        ))) 
    }}">
    
        <form wire:submit.prevent="save">
            
            {{-- STEP 1 --}}
            @if($step == 1)
                <div wire:key="step-1">
                    <div class="form-g mb-3">
                        <label class="form-lbl">Cliente *</label>
                        <select wire:model.live="client_id" class="form-in" required>
                            <option value="">Seleziona...</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        </select>
                        @error('client_id') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                    </div>

                    @if($client_id)
                    <div class="form-g mb-3">
                        <label class="form-lbl">Progetto Gestionale Associato *</label>
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
                                Questo cliente non ha progetti attivi. <a href="{{ route('projects.create') }}" style="color:var(--brand); text-decoration:underline;">Crea prima un progetto gestionale</a>.
                            </div>
                        @endif
                    </div>
                    @endif
                </div>
            @endif

            {{-- STEP 2 --}}
            @if($step == 2)
                <div wire:key="step-2">
                    <div class="mkt-type-grid">
                        <div wire:click="$set('type', 'one_shot')" class="mkt-type-card {{ $type == 'one_shot' ? 'selected' : '' }}">
                            <h3>Una Tantum</h3>
                            <p class="mkt-type-desc">Post singolo o campagna isolata. Nessun piano temporale complesso.</p>
                        </div>
                        <div wire:click="$set('type', 'editorial_plan')" class="mkt-type-card {{ $type == 'editorial_plan' ? 'selected' : '' }}">
                            <h3>Piano Editoriale</h3>
                            <p class="mkt-type-desc">Pianificazione a medio termine (es. 30/45 giorni) con molteplici slot di pubblicazione.</p>
                        </div>
                    </div>
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

                    <div class="form-g mb-3">
                        <label class="form-lbl">Piattaforme *</label>
                        <div class="mkt-checkbox-group">
                            @foreach($availablePlatforms as $plat)
                                <label class="mkt-checkbox-label">
                                    <input type="checkbox" wire:model.live="platforms" value="{{ $plat }}"> {{ ucfirst($plat) }}
                                </label>
                            @endforeach
                        </div>
                        @error('platforms') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                        
                        @if(count($platforms) > 0 && $client_id && $this->clientSocialStatus)
                            <div style="margin-top:10px; padding:12px; background:var(--bg); border:1px solid var(--line2); border-radius:var(--r);">
                                <div style="font-family:var(--mono); font-size:10px; color:var(--text3); margin-bottom:8px; text-transform:uppercase;">Stato Accessi Social</div>
                                
                                @if(in_array('facebook', $platforms) || in_array('instagram', $platforms))
                                    <div style="display:flex; justify-content:space-between; align-items:center; padding:4px 0; border-bottom:1px solid var(--line);">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <i data-lucide="facebook" style="width:14px; height:14px; color:var(--text2);"></i>
                                            <span style="font-size:12px; font-family:var(--sans);">Meta (Facebook / Instagram)</span>
                                        </div>
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            @if($this->clientSocialStatus['is_meta_ready'])
                                                <span style="font-size:10px; font-family:var(--mono); color:var(--green); padding:2px 6px; border:1px solid var(--green)40; border-radius:4px; background:var(--green)15;">PRONTO</span>
                                                <i data-lucide="check-circle" style="width:14px; height:14px; color:var(--green);" title="Pronto per la pubblicazione"></i>
                                            @else
                                                <span style="font-size:10px; font-family:var(--mono); color:var(--orange); padding:2px 6px; border:1px solid var(--orange)40; border-radius:4px; background:var(--orange)15;">INCOMPLETO</span>
                                                <i data-lucide="alert-triangle" style="width:14px; height:14px; color:var(--orange);" title="Accesso non operativo"></i>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                @if(in_array('tiktok', $platforms))
                                    <div style="display:flex; justify-content:space-between; align-items:center; padding:4px 0;">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text2);">
                                                <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>
                                            </svg>
                                            <span style="font-size:12px; font-family:var(--sans);">TikTok</span>
                                        </div>
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            @if($this->clientSocialStatus['is_tiktok_ready'])
                                                <span style="font-size:10px; font-family:var(--mono); color:var(--green); padding:2px 6px; border:1px solid var(--green)40; border-radius:4px; background:var(--green)15;">PRONTO</span>
                                                <i data-lucide="check-circle" style="width:14px; height:14px; color:var(--green);" title="Pronto per la pubblicazione"></i>
                                            @else
                                                <span style="font-size:10px; font-family:var(--mono); color:var(--text3); padding:2px 6px; border:1px solid var(--text3)40; border-radius:4px; background:var(--text3)15;">NON CONFIGURATO</span>
                                                <i data-lucide="alert-circle" style="width:14px; height:14px; color:var(--text3);" title="Accesso non configurato (Opzionale)"></i>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                @if((in_array('facebook', $platforms) || in_array('instagram', $platforms)) && !$this->clientSocialStatus['is_meta_ready'])
                                    <div style="margin-top:8px; font-size:11px; color:var(--red); display:flex; gap:6px;">
                                        <i data-lucide="alert-octagon" style="width:14px; height:14px; flex-shrink:0;"></i>
                                        <span><strong>Attenzione:</strong> Meta Business è richiesto per l'invio al workflow automatico. Il progetto sarà salvato in Bozza e l'invio verrà bloccato finché non configuri gli accessi.</span>
                                    </div>
                                @elseif(in_array('tiktok', $platforms) && !$this->clientSocialStatus['is_tiktok_ready'])
                                    <div style="margin-top:8px; font-size:11px; color:var(--orange); display:flex; gap:6px;">
                                        <i data-lucide="info" style="width:14px; height:14px; flex-shrink:0;"></i>
                                        <span>TikTok non è configurato. Potrai comunque inviare il piano (poiché opzionale), ma la pubblicazione andrà gestita manualmente.</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="form-g mb-3">
                        <label class="form-lbl">Modalità Pubblicazione</label>
                        <select wire:model="publication_mode" class="form-in">
                            <option value="manual">Manuale (L'operatore pubblica sui social)</option>
                            <option value="automatic">Automatica (Tramite n8n/API se supportato)</option>
                        </select>
                    </div>
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
                        <h3 class="mkt-summary-title">Riepilogo Progetto</h3>
                        
                        <table class="mkt-summary-table">
                            <tr><td class="label">Titolo</td><td><strong>{{ $title }}</strong></td></tr>
                            <tr><td class="label">Tipo</td><td>{{ $type === 'one_shot' ? 'Una Tantum' : 'Piano Editoriale' }}</td></tr>
                            <tr><td class="label">Piattaforme base</td><td>{{ implode(', ', array_map('ucfirst', $platforms)) }}</td></tr>
                            @if($type === 'editorial_plan')
                                <tr><td class="label">Slot configurati</td><td>{{ count($planSlots) }}</td></tr>
                            @endif
                        </table>
                        
                        <div style="margin-top:20px;">
                            <h4 style="font-size:14px; margin-bottom:5px;">Brief:</h4>
                            <p style="font-size:13px; color:var(--text2); white-space:pre-wrap;">{{ $brief }}</p>
                        </div>
                    </div>

                    <div class="mkt-alert-info">
                        <i class="fa fa-info-circle"></i> Il progetto verrà salvato in stato <strong>Bozza</strong>. Potrai inviarlo a n8n in un secondo momento dalla pagina di dettaglio.
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
                    <button type="submit" wire:target="save" class="btn btn-success" wire:loading.attr="disabled">Salva Progetto</button>
                @endif
            </div>

        </form>
    </x-panel>
</div>
