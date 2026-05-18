<div>
    <div class="shooting-back-link">
        <a href="{{ route('social.shooting.index') }}" wire:navigate>← Torna alle richieste</a>
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
            
            {{-- Slots --}}
            <x-panel title="Slot Temporali" dot="var(--blue)">
                <div class="shooting-panel-inner">
                    <x-shooting.slot-list :shoot="$shoot" :interactive="false" :showWarning="false" />
                </div>
            </x-panel>
        </div>
        
        {{-- Sidebar --}}
        <div>
            <x-panel title="Avanzamento" dot="var(--green)">
                <div class="shooting-panel-inner">
                    <x-shooting.workflow-timeline :shoot="$shoot" />
                </div>
            </x-panel>
            
            {{-- Audit Log --}}
            <div class="mt-panel">
                <x-panel title="Storico Attività" dot="var(--gray)">
                    <div class="shooting-panel-inner-sm">
                        @forelse($shoot->auditLogs()->latest()->get() as $log)
                            <x-audit-item :log="$log" />
                        @empty
                            <div class="shooting-empty-table">Nessuna attività registrata.</div>
                        @endforelse
                    </div>
                </x-panel>
            </div>
        </div>
        
    </div>
</div>
