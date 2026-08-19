<div>
    <div class="page-back-row">
        <a href="{{ route('social.shooting.index') }}" wire:navigate class="btn btn-g btn-sm">
            <i data-lucide="arrow-left" class="u-icon-sm" aria-hidden="true"></i>
            Torna alle richieste
        </a>
    </div>

    <x-page-header eyebrow="Social">
        <x-slot:title>
            <strong>{{ $shoot->title }}</strong> <span class="shooting-header-code">{{ $shoot->code }}</span>
        </x-slot:title>
        <x-slot name="actions">
            <x-shooting.status-badge :status="$shoot->status" context="social" />
        </x-slot>
    </x-page-header>

    <div class="g-shoot-detail">
        
        {{-- Main Column --}}
        <div class="shooting-main-col">
            {{-- Info --}}
            <x-panel title="Dettagli Shooting" dot="var(--purple)">
                <div class="shooting-panel-inner">
                    <div class="g-shoot-2col">
                        <div>
                            @if($shoot->project)
                                <div class="shooting-lbl-caps">Progetto</div>
                                <div class="shooting-text-val-bold">{{ $shoot->project->name }}</div>
                            @endif
                            
                            @if($shoot->marketingCampaign)
                                <div class="shooting-lbl-caps {{ $shoot->project ? 'u-mt-sm' : '' }}">Campagna Marketing</div>
                                <div class="shooting-text-val-bold u-text-purple">{{ $shoot->marketingCampaign->client->name }} - {{ $shoot->marketingCampaign->name }}</div>
                            @endif
                            
                            @if(!$shoot->project && !$shoot->marketingCampaign)
                                <div class="shooting-lbl-caps">Riferimento</div>
                                <div class="shooting-text-val-bold u-text-muted">Nessun riferimento</div>
                            @endif
                        </div>
                        <div>
                            <div class="shooting-lbl-caps">Fotografo</div>
                            @if($shoot->photographer)
                                <div class="shooting-flex-center-gap8">
                                    <div class="avatar-sm">{{ substr($shoot->photographer->name, 0, 1) }}</div>
                                    <span class="shooting-text-val-bold">{{ $shoot->photographer->name }}</span>
                                </div>
                            @else
                                <span class="shooting-unassigned">Da definire</span>
                            @endif
                        </div>
                    </div>
                    
                    @if($shoot->location)
                        <div class="shooting-mb-24">
                            <div class="shooting-lbl-caps">Location</div>
                            <div class="shooting-text-val">{{ $shoot->location }}</div>
                        </div>
                    @endif
                    
                    <div class="g-shoot-2col shooting-mb-0">
                        <div>
                            <div class="shooting-lbl-caps">Note Cliente</div>
                            <div class="shoot-note-box">
                                {{ $shoot->client_notes ?: 'Nessuna nota per il cliente.' }}
                            </div>
                        </div>
                        <div>
                            <div class="shooting-lbl-caps">Note Interne</div>
                            <div class="shoot-note-box purple">
                                {{ $shoot->internal_notes ?: 'Nessuna nota interna.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </x-panel>

            @if($shoot->status === \App\Enums\Shooting\ShootStatus::WaitingClient)
                @can('confirmClient', $shoot)
                    <x-panel title="Comunicazione e risposta cliente" dot="var(--yellow)">
                        <div class="shooting-panel-inner">
                            <p class="shooting-desc-text">
                                Il fotografo ha confermato la propria disponibilità. Usa il messaggio preparato,
                                registra il canale utilizzato e poi inserisci la risposta del cliente.
                            </p>
                            <x-shooting.client-contact-panel
                                :shoot="$shoot"
                                :communication="$communication"
                                :client-channels="$clientChannels" />
                        </div>
                    </x-panel>
                @endcan
            @endif

            @if(in_array($shoot->status, [
                \App\Enums\Shooting\ShootStatus::PhotographerRejected,
                \App\Enums\Shooting\ShootStatus::ClientRejected,
            ], true))
                @can('revise', $shoot)
                    <x-panel title="Prepara una nuova proposta" dot="var(--yellow)">
                        <div class="shooting-panel-inner">
                        <p class="shooting-desc-text">
                            Aggiorna il fotografo se necessario e inserisci nuove date. La nuova proposta
                            riaprirà il flusso dalla conferma del fotografo.
                        </p>

                        <div class="shooting-revision-grid">
                            <div class="form-g">
                                <label class="form-lbl" for="revision-photographer">Fotografo *</label>
                                <select id="revision-photographer"
                                        wire:model="revisionPhotographerId"
                                        class="form-sel @error('revisionPhotographerId') is-invalid @enderror"
                                        required @disabled($photographers->isEmpty())>
                                    <option value="">Seleziona fotografo...</option>
                                    @foreach($photographers as $photographer)
                                        <option value="{{ $photographer->id }}">{{ $photographer->name }}</option>
                                    @endforeach
                                </select>
                                @error('revisionPhotographerId')
                                    <div class="shooting-err-msg">{{ $message }}</div>
                                @enderror
                                @if($photographers->isEmpty())
                                    <div class="u-alert-error u-mt-sm" role="alert">Non ci sono fotografi attivi disponibili.</div>
                                @endif
                            </div>

                            <div class="shooting-revision-slots">
                                @foreach($revisionSlots as $index => $slot)
                                    <div class="shooting-revision-slot" wire:key="revision-slot-{{ $index }}">
                                        <div class="form-g">
                                            <label for="revision-date-{{ $index }}" class="form-lbl">Nuova data *</label>
                                            <input id="revision-date-{{ $index }}" type="date"
                                                   wire:model="revisionSlots.{{ $index }}.date"
                                                   class="form-in @error('revisionSlots.'.$index.'.date') is-invalid @enderror"
                                                   min="{{ now()->toDateString() }}" required>
                                            @error('revisionSlots.'.$index.'.date')
                                                <div class="shooting-err-msg">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="form-g">
                                            <label for="revision-period-{{ $index }}" class="form-lbl">Fascia oraria *</label>
                                            <select id="revision-period-{{ $index }}" wire:model="revisionSlots.{{ $index }}.period" class="form-sel" required>
                                                <option value="morning">Mattina</option>
                                                <option value="intermediate">Intermedio</option>
                                                <option value="afternoon">Pomeriggio</option>
                                                <option value="full_day">Giornata intera</option>
                                            </select>
                                        </div>
                                        <button type="button"
                                                wire:click="removeRevisionSlot({{ $index }})"
                                                class="btn btn-g btn-sm"
                                                aria-label="Rimuovi data {{ $index + 1 }}"
                                                @disabled(count($revisionSlots) === 1)>
                                            Rimuovi
                                        </button>
                                    </div>
                                @endforeach
                                @error('revisionSlots')
                                    <div class="shooting-err-msg">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="shooting-revision-actions">
                            <button type="button" wire:click="addRevisionSlot" class="btn btn-g">
                                Aggiungi data
                            </button>
                            <button type="button"
                                    wire:click="reopenProposal"
                                    wire:loading.attr="disabled"
                                    wire:target="reopenProposal"
                                    @disabled($photographers->isEmpty())
                                    class="btn btn-p">
                                Invia nuova proposta
                            </button>
                        </div>
                        </div>
                    </x-panel>
                @endcan
            @endif
            
            {{-- Slots --}}
            <x-panel title="Slot Temporali" dot="var(--blue)">
                <div class="shooting-panel-inner">
                    <x-shooting.slot-list :shoot="$shoot" :interactive="false" :showWarning="false" />
                </div>
            </x-panel>

            @if($shoot->calendarEvent || $shoot->task)
                <x-panel title="Pianificazione creata" dot="var(--green)">
                    <div class="shooting-panel-inner shooting-contact-actions">
                        @if($shoot->calendarEvent)
                            <a href="{{ route('calendar-events.show', $shoot->calendarEvent) }}" class="btn btn-g">
                                Vedi evento nel calendario
                            </a>
                        @endif
                        @if($shoot->task)
                            <a href="{{ route('tasks.show', $shoot->task) }}" class="btn btn-g">
                                Vedi task del fotografo
                            </a>
                        @endif
                    </div>
                </x-panel>
            @endif
        </div>
        
        {{-- Sidebar --}}
        <div>
            <x-panel title="Avanzamento" dot="var(--green)">
                <div class="shooting-panel-inner">
                    <x-shooting.workflow-timeline :shoot="$shoot" />
                </div>
            </x-panel>
            
            @can('system.admin')
                <div class="mt-panel">
                    <x-panel title="Storico attività" dot="var(--gray)">
                        <div class="shooting-panel-inner-sm">
                            @forelse($shoot->auditLogs()->latest()->get() as $log)
                                <x-audit-item :log="$log" />
                            @empty
                                <div class="shooting-empty-table">Nessuna attività registrata.</div>
                            @endforelse
                        </div>
                    </x-panel>
                </div>
            @endcan
        </div>
        
    </div>
</div>
