<div>
    <div class="mb-4">
        <x-page-header eyebrow="Social" meta="Wizard creazione progetto e piano editoriale">
            <x-slot:title><strong>Nuovo Progetto Marketing</strong></x-slot:title>
        </x-page-header>
    </div>

    <!-- Progress Indicator -->
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
                <div class="form-g mb-3">
                    <label class="form-lbl">Cliente *</label>
                    <select wire:model.live="client_id" class="form-in" required>
                        <option value="">Seleziona...</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                    @error('client_id') <span class="mkt-text-red">{{ $message }}</span> @enderror
                </div>

                @if($client_id)
                <div class="form-g mb-3">
                    <label class="form-lbl">Progetto Gestionale Associato (Opzionale)</label>
                    <select wire:model="project_id" class="form-in">
                        <option value="">Nessuno (Generico Cliente)</option>
                        @foreach($projects as $proj)
                            <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            @endif

            {{-- STEP 2 --}}
            @if($step == 2)
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
            @endif

            {{-- STEP 3 --}}
            @if($step == 3)
                <div class="form-g mb-3">
                    <label class="form-lbl">Titolo Progetto *</label>
                    <input type="text" wire:model="title" class="form-in" placeholder="Es. Lancio prodotto XYZ" required>
                    @error('title') <span class="mkt-text-red">{{ $message }}</span> @enderror
                </div>

                <div class="form-g mb-3">
                    <label class="form-lbl">Briefing per l'AI / Creativi *</label>
                    <textarea wire:model="brief" class="form-in" rows="5" placeholder="Descrivi l'obiettivo, il tono di voce, il target..." required></textarea>
                    @error('brief') <span class="mkt-text-red">{{ $message }}</span> @enderror
                </div>

                <div class="form-g mb-3">
                    <label class="form-lbl">Piattaforme *</label>
                    <div class="mkt-checkbox-group">
                        @foreach($availablePlatforms as $plat)
                            <label class="mkt-checkbox-label">
                                <input type="checkbox" wire:model="platforms" value="{{ $plat }}"> {{ ucfirst($plat) }}
                            </label>
                        @endforeach
                    </div>
                    @error('platforms') <span class="mkt-text-red">{{ $message }}</span> @enderror
                </div>

                <div class="form-g mb-3">
                    <label class="form-lbl">Modalità Pubblicazione</label>
                    <select wire:model="publication_mode" class="form-in">
                        <option value="manual">Manuale (L'operatore pubblica sui social)</option>
                        <option value="automatic">Automatica (Tramite n8n/API se supportato)</option>
                    </select>
                </div>
            @endif

            {{-- STEP 4 --}}
            @if($step == 4)
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
                        <div class="mkt-slot-card">
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
            @endif

            {{-- STEP 5 --}}
            @if($step == 5)
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
            @endif

            {{-- Buttons --}}
            <div class="mkt-wizard-footer">
                @if($step > 1)
                    <button type="button" wire:click="prevStep" class="btn btn-secondary">Indietro</button>
                @else
                    <div></div>
                @endif

                @if($step < 5)
                    <button type="button" wire:click="nextStep" class="btn btn-g" wire:loading.attr="disabled">Avanti</button>
                @else
                    <button type="submit" class="btn btn-success" wire:loading.attr="disabled">Salva Progetto</button>
                @endif
            </div>

        </form>
    </x-panel>
</div>
