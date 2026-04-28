<div>
    <div style="margin-bottom:15px">
        <a href="{{ route('social.shooting.index') }}" wire:navigate style="color:var(--text3);font-size:12px;text-decoration:none">← Torna alle richieste</a>
    </div>

    <div class="mb-4">
        <x-page-header eyebrow="Social" meta="Compila i dettagli e proponi le date per avviare il workflow con il reparto fotografia.">
            <x-slot:title><strong>Nuova Richiesta Shooting</strong></x-slot:title>
        </x-page-header>
    </div>

    <div class="g-2col-main" style="align-items:start;">
        <!-- DETTAGLI -->
        <x-panel title="Dettagli Shooting" dot="var(--purple)" padded>
            <div style="display:flex; flex-direction:column; gap:24px;">
                
                <div class="form-row full" style="margin-bottom:0; gap:16px;">
                    <div>
                        <label class="form-lbl">Titolo Shooting <span style="color:var(--red);">*</span></label>
                        <input type="text" wire:model="title" class="form-in" placeholder="es. Shooting Esterno Campagna Estiva" style="width:100%; box-sizing:border-box;">
                        @error('title') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-row" style="margin-bottom:0; gap:16px;">
                    <div>
                        <label class="form-lbl">Progetto <span style="color:var(--red);">*</span></label>
                        <select wire:model="project_id" class="form-in" style="width:100%; box-sizing:border-box;">
                            <option value="">Seleziona...</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        @error('project_id') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="form-lbl">Fotografo Assegnato <span style="color:var(--red);">*</span></label>
                        <select wire:model="photographer_id" class="form-in" style="width:100%; box-sizing:border-box;">
                            <option value="">Da definire</option>
                            @foreach($photographers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @error('photographer_id') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-row full" style="margin-bottom:0; gap:16px;">
                    <div>
                        <label class="form-lbl">Location</label>
                        <input type="text" wire:model="location" class="form-in" placeholder="Indirizzo o link Maps" style="width:100%; box-sizing:border-box;">
                        @error('location') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="form-lbl">Note per il Cliente</label>
                    <textarea wire:model="client_notes" class="form-in" rows="2" placeholder="Visibili al cliente in fase di approvazione" style="width:100%; box-sizing:border-box;"></textarea>
                    @error('client_notes') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label class="form-lbl">Note Interne</label>
                    <textarea wire:model="internal_notes" class="form-in" rows="2" placeholder="Solo uso interno (es. lenti consigliate)" style="width:100%; box-sizing:border-box;"></textarea>
                    @error('internal_notes') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                </div>

            </div>
        </x-panel>

        <!-- SLOT PROPOSTI -->
        <div style="display:flex; flex-direction:column; gap:24px;">
            <x-panel title="Slot Proposti" dot="var(--blue)" padded>
                <p style="font-size:13px; color:var(--text2); margin-bottom:16px;">
                    Indica le date disponibili. Il fotografo potrà accettarne solo una.
                </p>

                <div style="display:flex; flex-direction:column; gap:12px;">
                    @foreach($proposedSlots as $index => $slot)
                        <div style="background:var(--bg3); border-radius:8px; padding:16px; position:relative; display:flex; flex-direction:column; gap:12px;">
                            
                            @if(count($proposedSlots) > 1)
                            <button wire:click="removeSlot({{ $index }})" style="position:absolute; top:12px; right:12px; background:none; border:none; color:var(--text3); cursor:pointer; transition:color 0.2s;" onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--text3)'">
                                <i data-lucide="x" style="width:16px; height:16px;"></i>
                            </button>
                            @endif
                            
                            <div>
                                <label class="form-lbl" style="font-size:11px; margin-bottom:4px;">Data Proposta</label>
                                <input type="date" wire:model="proposedSlots.{{ $index }}.date" class="form-in" style="width:100%; box-sizing:border-box; background:var(--bg);">
                                @error('proposedSlots.'.$index.'.date') <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="form-lbl" style="font-size:11px; margin-bottom:4px;">Fascia Oraria</label>
                                <div style="display:flex; gap:12px; align-items:center; background:var(--bg); padding:8px 12px; border-radius:6px; border:1px solid var(--line);">
                                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; color:var(--text1); cursor:pointer; flex:1;">
                                        <input type="checkbox" wire:model="proposedSlots.{{ $index }}.morning"> 
                                        Mattina (9:00-13:00)
                                    </label>
                                    <div style="width:1px; height:16px; background:var(--line);"></div>
                                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; color:var(--text1); cursor:pointer; flex:1;">
                                        <input type="checkbox" wire:model="proposedSlots.{{ $index }}.afternoon"> 
                                        Pomeriggio (14:00-18:00)
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                
                @error('slots')
                    <div style="color:var(--red); font-size:13px; margin-top:16px;">
                        <i data-lucide="alert-circle" style="width:14px; height:14px; margin-right:4px; vertical-align:middle;"></i> {{ $message }}
                    </div>
                @enderror

                <button wire:click="addSlot" class="btn btn-g" style="width:100%; margin-top:16px; display:flex; justify-content:center; align-items:center; gap:8px;">
                    <i data-lucide="plus" style="width:16px; height:16px;"></i> Aggiungi Slot
                </button>
            </x-panel>
            
            <button wire:click="save" class="btn btn-p" style="width:100%; display:flex; justify-content:center; align-items:center; gap:8px;">
                <i data-lucide="send" style="width:16px; height:16px;"></i> Invia Richiesta
            </button>
        </div>
    </div>
</div>