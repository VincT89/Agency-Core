<div>
    <div class="shooting-back-link">
        <a href="{{ route('social.shooting.index') }}" wire:navigate>← Torna alle richieste</a>
    </div>

    <div class="mb-4">
        <x-page-header eyebrow="Social" meta="Compila i dettagli e proponi le date per avviare il workflow con il reparto fotografia.">
            <x-slot:title><strong>Nuova Richiesta Shooting</strong></x-slot:title>
        </x-page-header>
    </div>

    <div class="g-2col-main shooting-2col-main-start">
        {{-- DETTAGLI --}}
        <x-panel title="Dettagli Shooting" dot="var(--purple)" padded>
            <div class="shooting-main-col">
                
                <div class="form-row full shooting-form-row">
                    <div>
                        <label class="form-lbl">Titolo Shooting</label>
                        <input type="text" wire:model="title" class="form-in shooting-input-full" placeholder="es. Shooting Esterno Campagna Estiva (lascia vuoto per auto-generato)">
                        @error('title') <span class="shooting-err-msg">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-row shooting-form-row">
                    <div>
                        <label class="form-lbl">Progetto di Fatturazione <span class="u-text-meta u-text-muted">(Richiesto se nessuna campagna)</span></label>
                        <select wire:model="project_id" class="form-in shooting-input-full">
                            <option value="">Seleziona progetto (costi/budget)...</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        @error('project_id') <span class="shooting-err-msg">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="form-lbl">Campagna Marketing <span class="u-text-meta u-text-muted">(Richiesta se nessun progetto)</span></label>
                        <select wire:model="marketing_campaign_id" class="form-in shooting-input-full">
                            <option value="">Nessuna campagna (solo gestionale)</option>
                            @foreach($campaigns as $camp)
                                <option value="{{ $camp->id }}">{{ $camp->client->name }} - {{ $camp->name }}</option>
                            @endforeach
                        </select>
                        @error('marketing_campaign_id') <span class="shooting-err-msg">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-row full shooting-form-row">
                    <div>
                        <label class="form-lbl">Fotografo Assegnato</label>
                        <select wire:model="photographer_id" class="form-in shooting-input-full">
                            <option value="">Da definire</option>
                            @foreach($photographers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @error('photographer_id') <span class="shooting-err-msg">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-row full shooting-form-row">
                    <div>
                        <label class="form-lbl">Location</label>
                        <input type="text" wire:model="location" class="form-in shooting-input-full" placeholder="Indirizzo o link Maps">
                        @error('location') <span class="shooting-err-msg">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="form-lbl">Note per il Cliente</label>
                    <textarea wire:model="client_notes" class="form-in shooting-input-full" rows="2" placeholder="Visibili al cliente in fase di approvazione"></textarea>
                    @error('client_notes') <span class="shooting-err-msg">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label class="form-lbl">Note Interne</label>
                    <textarea wire:model="internal_notes" class="form-in shooting-input-full" rows="2" placeholder="Solo uso interno (es. lenti consigliate)"></textarea>
                    @error('internal_notes') <span class="shooting-err-msg">{{ $message }}</span> @enderror
                </div>

            </div>
        </x-panel>

        {{-- SLOT PROPOSTI --}}
        <div class="shooting-main-col">
            <x-panel title="Slot Proposti" dot="var(--blue)" padded>
                <p class="shooting-desc-text">
                    Indica le date disponibili. Il fotografo potrà accettarne solo una.
                </p>

                <div class="shooting-col-gap12">
                    @foreach($proposedSlots as $index => $slot)
                        <div class="shooting-slot-card">
                            
                            @if(count($proposedSlots) > 1)
                            <button wire:click="removeSlot({{ $index }})" class="shooting-slot-del hover-danger">
                                <i data-lucide="x" class="shooting-icon-sm"></i>
                            </button>
                            @endif
                            
                            <div>
                                <label class="form-lbl shooting-slot-lbl">Data Proposta</label>
                                <input type="date" wire:model="proposedSlots.{{ $index }}.date" class="form-in shooting-input-full shooting-input-bg">
                                @error('proposedSlots.'.$index.'.date') <span class="shooting-err-msg">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="form-lbl shooting-slot-lbl">Fascia Oraria</label>
                                <select wire:model="proposedSlots.{{ $index }}.period" class="form-in shooting-input-full shooting-input-bg" required>
                                    <option value="morning">Mattina (09:00 - 13:00)</option>
                                    <option value="intermediate">Intermedio (11:00 - 16:00)</option>
                                    <option value="afternoon">Pomeriggio (15:00 - 20:00)</option>
                                    <option value="full_day">Tutta la giornata</option>
                                </select>
                            </div>
                        </div>
                    @endforeach
                </div>
                
                @error('slots')
                    <div class="shooting-err-summary">
                        <i data-lucide="alert-circle" class="shooting-err-icon"></i> {{ $message }}
                    </div>
                @enderror

                <button wire:click="addSlot" class="btn btn-g shooting-btn-full">
                    <i data-lucide="plus" class="shooting-icon-sm"></i> Aggiungi Slot
                </button>
            </x-panel>
            
            <button wire:click="save" class="btn btn-p shooting-btn-full-primary">
                <i data-lucide="send" class="shooting-icon-sm"></i> Invia Richiesta
            </button>
        </div>
    </div>
</div>