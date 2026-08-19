<div>
    <div class="page-back-row">
        <a href="{{ route('social.shooting.index') }}" wire:navigate class="btn btn-g btn-sm">
            <i data-lucide="arrow-left" class="u-icon-sm" aria-hidden="true"></i>
            Torna alle richieste
        </a>
    </div>

    <div class="mb-4">
        <x-page-header eyebrow="Social" meta="Compila i dettagli e proponi le date per avviare il workflow con il reparto fotografia.">
            <x-slot:title><strong>Nuova Richiesta Shooting</strong></x-slot:title>
        </x-page-header>
    </div>

    <div class="g-2col-main shooting-request-layout shooting-2col-main-start">
        {{-- DETTAGLI --}}
        <x-panel title="Dettagli Shooting" dot="var(--purple)" padded>
            <div class="shooting-main-col">
                
                <div class="form-row full shooting-form-row">
                    <div>
                        <label for="shoot-title" class="form-lbl">Titolo shooting</label>
                        <input id="shoot-title" type="text" wire:model="title" class="form-in shooting-input-full" placeholder="Es. Shooting esterno campagna estiva (facoltativo)">
                        @error('title') <span class="shooting-err-msg">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-row shooting-form-row">
                    <div>
                        <label for="shoot-project" class="form-lbl">Progetto di fatturazione <span class="u-text-meta u-text-muted">(richiesto se non scegli una campagna)</span></label>
                        <select id="shoot-project" wire:model="project_id" class="form-in shooting-input-full">
                            <option value="">Seleziona progetto (costi/budget)...</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        @error('project_id') <span class="shooting-err-msg">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="shoot-campaign" class="form-lbl">Campagna marketing <span class="u-text-meta u-text-muted">(richiesta se non scegli un progetto)</span></label>
                        <select id="shoot-campaign" wire:model="marketing_campaign_id" class="form-in shooting-input-full">
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
                        <label for="shoot-photographer" class="form-lbl">Fotografo assegnato *</label>
                        <select id="shoot-photographer" wire:model="photographer_id" class="form-in shooting-input-full" required @disabled($photographers->isEmpty())>
                            <option value="">Seleziona fotografo...</option>
                            @foreach($photographers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @error('photographer_id') <span class="shooting-err-msg">{{ $message }}</span> @enderror
                        @if($photographers->isEmpty())
                            <div class="u-alert-error u-mt-sm" role="alert">Non ci sono fotografi attivi. Attivane uno prima di creare la richiesta.</div>
                        @endif
                    </div>
                </div>

                <div class="form-row full shooting-form-row">
                    <div>
                        <label for="shoot-location" class="form-lbl">Luogo</label>
                        <input id="shoot-location" type="text" wire:model="location" class="form-in shooting-input-full" placeholder="Indirizzo o link Maps">
                        @error('location') <span class="shooting-err-msg">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label for="shoot-client-notes" class="form-lbl">Note da comunicare al cliente</label>
                    <textarea id="shoot-client-notes" wire:model="client_notes" class="form-in shooting-input-full" rows="2" placeholder="Informazioni che il marketing inserirà nel messaggio al cliente"></textarea>
                    @error('client_notes') <span class="shooting-err-msg">{{ $message }}</span> @enderror
                </div>
                
                <div>
                    <label for="shoot-internal-notes" class="form-lbl">Note interne</label>
                    <textarea id="shoot-internal-notes" wire:model="internal_notes" class="form-in shooting-input-full" rows="2" placeholder="Solo uso interno (es. lenti consigliate)"></textarea>
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
                        <div class="shooting-slot-card" wire:key="proposed-slot-{{ $index }}">
                            
                            @if(count($proposedSlots) > 1)
                            <button type="button" wire:click="removeSlot({{ $index }})" class="shooting-slot-del hover-danger" aria-label="Rimuovi slot {{ $index + 1 }}">
                                <i data-lucide="x" class="shooting-icon-sm"></i>
                            </button>
                            @endif
                            
                            <div>
                                <label for="shoot-slot-date-{{ $index }}" class="form-lbl shooting-slot-lbl">Data proposta</label>
                                <input id="shoot-slot-date-{{ $index }}" type="date" wire:model="proposedSlots.{{ $index }}.date" class="form-in shooting-input-full shooting-input-bg" min="{{ now()->toDateString() }}" required>
                                @error('proposedSlots.'.$index.'.date') <span class="shooting-err-msg">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label for="shoot-slot-period-{{ $index }}" class="form-lbl shooting-slot-lbl">Fascia oraria</label>
                                <select id="shoot-slot-period-{{ $index }}" wire:model="proposedSlots.{{ $index }}.period" class="form-in shooting-input-full shooting-input-bg" required>
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

                <button type="button" wire:click="addSlot" class="btn btn-g shooting-btn-full">
                    <i data-lucide="plus" class="shooting-icon-sm"></i> Aggiungi Slot
                </button>
            </x-panel>
            
            <button type="button" wire:click="save" class="btn btn-p shooting-btn-full-primary" @disabled($photographers->isEmpty())>
                <i data-lucide="send" class="shooting-icon-sm"></i> Invia Richiesta
            </button>
        </div>
    </div>
</div>
