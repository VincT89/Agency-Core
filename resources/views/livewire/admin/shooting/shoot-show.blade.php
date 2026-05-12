<div>
    <div class="u-mb-sm">
        <a href="{{ route('admin.shooting.index') }}" wire:navigate class="u-text-muted u-text-sm u-no-underline">← Torna agli shooting</a>
    </div>

    <x-page-header eyebrow="Amministrazione">
        <x-slot:title>
            <strong>{{ $shoot->title }}</strong> <span class="u-text-base u-text-muted u-font-normal u-ml-sm">{{ $shoot->code }}</span>
        </x-slot:title>
        <x-slot name="actions">
            <x-shooting.status-badge :status="$shoot->status" context="admin" />
        </x-slot>
    </x-page-header>

    <div class="g-shoot-detail">
        
        {{-- Main Column --}}
        <div class="u-flex-col u-gap-md">
            <x-panel title="Dettagli Shooting" dot="var(--purple)">
                <div class="u-p-lg">
                    <div class="g-shoot-2col">
                        <div>
                            <div class="u-text-sm u-text-muted u-uppercase u-text-strong u-tracking-wide u-mb-xs">Progetto</div>
                            <div class="u-text-strong u-text-primary">{{ $shoot->project->name }}</div>
                        </div>
                        <div>
                            <div class="u-text-sm u-text-muted u-uppercase u-text-strong u-tracking-wide u-mb-xs">Fotografo</div>
                            @if($shoot->photographer)
                                <div class="u-flex u-items-center u-gap-xs">
                                    <div class="avatar-sm">{{ substr($shoot->photographer->name, 0, 1) }}</div>
                                    <span class="u-text-md u-text-strong u-text-primary">{{ $shoot->photographer->name }}</span>
                                </div>
                            @else
                                <span class="u-text-muted u-text-md">Da definire</span>
                            @endif
                        </div>
                    </div>
                    
                    @if($shoot->location)
                        <div class="u-mb-lg">
                            <div class="u-text-sm u-text-muted u-uppercase u-text-strong u-tracking-wide u-mb-xs">Location</div>
                            <div class="u-text-md u-text-primary">{{ $shoot->location }}</div>
                        </div>
                    @endif
                    
                    <div class="g-shoot-2col u-mb-0">
                        <div>
                            <div class="u-text-sm u-text-muted u-uppercase u-text-strong u-tracking-wide u-mb-xs">Note Cliente</div>
                            <div class="shoot-note-box">
                                {{ $shoot->client_notes ?: 'Nessuna nota per il cliente.' }}
                            </div>
                        </div>
                        <div>
                            <div class="u-text-sm u-text-muted u-uppercase u-text-strong u-tracking-wide u-mb-xs">Note Interne</div>
                            <div class="shoot-note-box purple">
                                {{ $shoot->internal_notes ?: 'Nessuna nota interna.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </x-panel>
            
            {{-- Azione Cliente --}}
            @if($shoot->status->value === 'waiting_client')
                <x-panel title="Conferma Cliente" dot="var(--yellow)">
                    <div class="u-p-lg">
                        <p class="u-text-sm u-text-secondary u-mb-md">
                            Il fotografo ha accettato uno slot temporale. Attendi conferma dal cliente e seleziona l'esito.
                        </p>
                        <div class="u-flex u-gap-sm">
                            <button wire:click="confirmForClient" wire:confirm="Questa azione creerà evento e task." wire:loading.attr="disabled" class="btn btn-success">Cliente ha Accettato</button>
                            <button wire:click="rejectForClient" wire:confirm="Questa azione riporterà gli slot in revisione." wire:loading.attr="disabled" class="btn btn-outline btn-outline-danger">Cliente ha Rifiutato</button>
                        </div>
                    </div>
                </x-panel>
            @endif
            
            {{-- Slots --}}
            <x-panel title="Slot Temporali" dot="var(--blue)">
                <div class="u-p-lg">
                    <x-shooting.slot-list 
                        :shoot="$shoot" 
                        :interactive="$shoot->status->value === 'waiting_photographer'" 
                        :showWarning="false" 
                    />
                </div>
            </x-panel>
            
            @if($shoot->calendarEvent || $shoot->task)
                <x-panel title="Entità Collegate" dot="var(--gray)">
                    <div class="u-p-lg">
                        @if($shoot->calendarEvent)
                            <div class="u-mb-sm">
                                <a href="{{ route('calendar-events.show', $shoot->calendarEvent) }}" class="btn btn-outline u-text-sm u-flex u-items-center u-gap-xs">
                                    <i data-lucide="calendar" class="u-icon-xs"></i> Vedi Evento Calendario
                                </a>
                            </div>
                        @endif
                        @if($shoot->task)
                            <div>
                                <a href="{{ route('tasks.show', $shoot->task) }}" class="btn btn-outline u-text-sm u-flex u-items-center u-gap-xs">
                                    <i data-lucide="check-square" class="u-icon-xs"></i> Vedi Task
                                </a>
                            </div>
                        @endif
                    </div>
                </x-panel>
            @endif
        </div>
        
        {{-- Sidebar --}}
        <div>
            <x-panel title="Avanzamento" dot="var(--green)">
                <div class="u-p-lg">
                    <x-shooting.workflow-timeline :shoot="$shoot" />
                </div>
            </x-panel>
            
            <div class="mt-panel">
                <x-panel title="Storico Attività" dot="var(--gray)">
                    <div class="u-p-md">
                        @forelse($shoot->auditLogs()->latest()->get() as $log)
                            <x-audit-item :log="$log" />
                        @empty
                            <div class="u-text-muted u-text-sm">Nessuna attività registrata.</div>
                        @endforelse
                    </div>
                </x-panel>
            </div>
        </div>
        
    </div>
</div>
