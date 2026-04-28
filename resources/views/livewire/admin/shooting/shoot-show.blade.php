<div>
    <div style="margin-bottom:15px">
        <a href="{{ route('admin.shooting.index') }}" wire:navigate style="color:var(--text3);font-size:12px;text-decoration:none">← Torna agli shooting</a>
    </div>

    <x-page-header eyebrow="Amministrazione">
        <x-slot:title>
            <strong>{{ $shoot->title }}</strong> <span style="font-size:16px; color:var(--text3); font-weight:400; margin-left:8px;">{{ $shoot->code }}</span>
        </x-slot:title>
        <x-slot name="actions">
            <x-shooting.status-badge :status="$shoot->status" context="admin" />
        </x-slot>
    </x-page-header>

    <div class="g-shoot-detail">
        
        <!-- Main Column -->
        <div style="display:flex; flex-direction:column; gap:24px;">
            <x-panel title="Dettagli Shooting" dot="var(--purple)">
                <div style="padding:24px;">
                    <div class="g-shoot-2col">
                        <div>
                            <div style="font-size:12px; color:var(--text3); text-transform:uppercase; font-weight:600; letter-spacing:0.5px; margin-bottom:4px;">Progetto</div>
                            <div style="font-weight:500; color:var(--text1);">{{ $shoot->project->name }}</div>
                        </div>
                        <div>
                            <div style="font-size:12px; color:var(--text3); text-transform:uppercase; font-weight:600; letter-spacing:0.5px; margin-bottom:4px;">Fotografo</div>
                            @if($shoot->photographer)
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div class="avatar-sm">{{ substr($shoot->photographer->name, 0, 1) }}</div>
                                    <span style="font-size:14px; font-weight:500; color:var(--text1);">{{ $shoot->photographer->name }}</span>
                                </div>
                            @else
                                <span style="color:var(--text3); font-size:14px;">Da definire</span>
                            @endif
                        </div>
                    </div>
                    
                    @if($shoot->location)
                        <div style="margin-bottom:24px;">
                            <div style="font-size:12px; color:var(--text3); text-transform:uppercase; font-weight:600; letter-spacing:0.5px; margin-bottom:4px;">Location</div>
                            <div style="font-size:14px; color:var(--text1);">{{ $shoot->location }}</div>
                        </div>
                    @endif
                    
                    <div class="g-shoot-2col" style="margin-bottom:0;">
                        <div>
                            <div style="font-size:12px; color:var(--text3); text-transform:uppercase; font-weight:600; letter-spacing:0.5px; margin-bottom:4px;">Note Cliente</div>
                            <div class="shoot-note-box">
                                {{ $shoot->client_notes ?: 'Nessuna nota per il cliente.' }}
                            </div>
                        </div>
                        <div>
                            <div style="font-size:12px; color:var(--text3); text-transform:uppercase; font-weight:600; letter-spacing:0.5px; margin-bottom:4px;">Note Interne</div>
                            <div class="shoot-note-box purple">
                                {{ $shoot->internal_notes ?: 'Nessuna nota interna.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </x-panel>
            
            <!-- Azione Cliente -->
            @if($shoot->status->value === 'waiting_client')
                <x-panel title="Conferma Cliente" dot="var(--yellow)">
                    <div style="padding:24px;">
                        <p style="font-size:13px; color:var(--text2); margin-bottom:16px;">
                            Il fotografo ha accettato uno slot temporale. Attendi conferma dal cliente e seleziona l'esito.
                        </p>
                        <div style="display:flex; gap:12px;">
                            <button wire:click="confirmForClient" wire:confirm="Questa azione creerà evento e task." wire:loading.attr="disabled" class="btn btn-p" style="background:var(--green); border-color:var(--green);">Cliente ha Accettato</button>
                            <button wire:click="rejectForClient" wire:confirm="Questa azione riporterà gli slot in revisione." wire:loading.attr="disabled" class="btn btn-outline" style="color:var(--red); border-color:var(--line);">Cliente ha Rifiutato</button>
                        </div>
                    </div>
                </x-panel>
            @endif
            
            <!-- Slots -->
            <x-panel title="Slot Temporali" dot="var(--blue)">
                <div style="padding:24px;">
                    <x-shooting.slot-list 
                        :shoot="$shoot" 
                        :interactive="$shoot->status->value === 'waiting_photographer'" 
                        :showWarning="false" 
                    />
                </div>
            </x-panel>
            
            @if($shoot->calendarEvent || $shoot->task)
                <x-panel title="Entità Collegate" dot="var(--gray)">
                    <div style="padding:24px;">
                        @if($shoot->calendarEvent)
                            <div style="margin-bottom:12px;">
                                <a href="{{ route('calendar-events.show', $shoot->calendarEvent) }}" class="btn btn-outline" style="font-size:13px; display:inline-flex; align-items:center; gap:6px;">
                                    <i data-lucide="calendar" style="width:14px; height:14px;"></i> Vedi Evento Calendario
                                </a>
                            </div>
                        @endif
                        @if($shoot->task)
                            <div>
                                <a href="{{ route('tasks.show', $shoot->task) }}" class="btn btn-outline" style="font-size:13px; display:inline-flex; align-items:center; gap:6px;">
                                    <i data-lucide="check-square" style="width:14px; height:14px;"></i> Vedi Task
                                </a>
                            </div>
                        @endif
                    </div>
                </x-panel>
            @endif
        </div>
        
        <!-- Sidebar -->
        <div>
            <x-panel title="Avanzamento" dot="var(--green)">
                <div style="padding:24px;">
                    <x-shooting.workflow-timeline :shoot="$shoot" />
                </div>
            </x-panel>
            
            <div class="mt-panel">
                <x-panel title="Storico Attività" dot="var(--gray)">
                    <div style="padding:16px;">
                        @forelse($shoot->auditLogs()->latest()->get() as $log)
                            <x-audit-item :log="$log" />
                        @empty
                            <div style="color:var(--text3);font-size:13px;">Nessuna attività registrata.</div>
                        @endforelse
                    </div>
                </x-panel>
            </div>
        </div>
        
    </div>
</div>
