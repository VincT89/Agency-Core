<div @if($project->status->value === 'queued_to_n8n') wire:poll.visible.10s @endif>
    <div style="margin-bottom: 20px;">
        <a href="{{ route('marketing-projects.index') }}" wire:navigate class="btn btn-g" style="font-size:12px; padding:6px 12px; display:inline-flex; align-items:center; gap:6px;">
            <i data-lucide="arrow-left" style="width:14px; height:14px;"></i> Torna ai progetti
        </a>
    </div>

    <x-page-header eyebrow="Progetto Marketing">
        <x-slot:title>{{ $project->title }}</x-slot:title>
    </x-page-header>

    {{-- METADATA ROW --}}
    <div class="panel mkt-meta-bar" style="padding:16px 20px;">
        <div class="mkt-meta-item">
            <i data-lucide="building-2" class="mkt-meta-icon"></i>
            <span class="mkt-meta-label">Cliente:</span>
            <strong class="mkt-meta-value">{{ $project->client->name ?? '-' }}</strong>
        </div>
        <div class="mkt-meta-sep"></div>
        <div class="mkt-meta-item">
            <i data-lucide="tag" class="mkt-meta-icon"></i>
            <span class="mkt-meta-label">Servizio:</span>
            <strong class="mkt-meta-value">{{ ucfirst(str_replace('_', ' ', $project->service_type ?? 'other')) }}</strong>
        </div>
        <div class="mkt-meta-sep"></div>
        <div class="mkt-meta-item">
            <i data-lucide="calendar" class="mkt-meta-icon"></i>
            <span class="mkt-meta-label">Struttura:</span>
            <strong class="mkt-meta-value">{{ ucfirst(str_replace('_', ' ', $project->campaign_structure ?? 'one_shot')) }}</strong>
        </div>
        <div class="mkt-meta-sep"></div>
        <div>
            <x-badge :status="$project->status->value" :label="$project->status->label()" />
        </div>
        
        <div class="mkt-meta-action">
            @if(in_array($project->status->value, ['draft', 'n8n_failed']))
                @if($project->status->value === 'n8n_failed')
                    <x-confirm-modal 
                        title="Riprova Invio a n8n" 
                        message="Vuoi riprovare l'invio a n8n? Verrà generato un nuovo tentativo di elaborazione." 
                        confirmText="Sì, riprova invio" 
                        confirmMethod="submitToN8n" 
                        btnClass="btn btn-p" 
                        btnStyle="background: var(--orange); border-color: var(--orange);"
                        icon="refresh-cw" 
                        iconColor="var(--orange)" 
                        iconBg="rgba(255, 150, 0, 0.1)">
                        <button class="btn btn-p" style="padding:8px 16px; font-size:12px; display:inline-flex; align-items:center; justify-content:center;" type="button">
                            <i data-lucide="refresh-cw" style="width:14px; height:14px; vertical-align:-2px; margin-right:6px;"></i>Riprova Invio
                        </button>
                    </x-confirm-modal>
                @else
                    <button wire:click="submitToN8n" class="btn btn-p" style="padding:8px 16px; font-size:12px; display:inline-flex; align-items:center; justify-content:center;" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitToN8n">
                            <i data-lucide="send" style="width:14px; height:14px; vertical-align:-2px; margin-right:6px;"></i>Invia a n8n
                        </span>
                        <span wire:loading wire:target="submitToN8n">
                            <i data-lucide="loader" class="spin" style="width:14px; height:14px; vertical-align:-2px; margin-right:6px;"></i>Invio in corso...
                        </span>
                    </button>
                @endif
            @endif
        </div>
    </div>

    <div class="g-2col" style="align-items:start;">
        <div>
            <x-panel title="Dettagli Briefing" dot="var(--brand)" padded>
                <div style="margin-bottom:20px;">
                    <div class="mkt-section-label">Piattaforme Destinazione</div>
                    <div class="mkt-platform-pills">
                        @forelse($project->getServiceOption('platforms', []) as $plat)
                            <span class="badge bd mkt-platform-pill">
                                @if($plat === 'facebook') <i data-lucide="facebook" class="mkt-platform-icon"></i>
                                @elseif($plat === 'instagram') <i data-lucide="instagram" class="mkt-platform-icon"></i>
                                @elseif($plat === 'tiktok') <svg class="mkt-platform-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/></svg>
                                @endif
                                {{ ucfirst($plat) }}
                            </span>
                        @empty
                            <span style="color:var(--text3); font-size:12px; font-style:italic;">Nessuna piattaforma specificata</span>
                        @endforelse
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <div class="mkt-section-label">Modalità Pubblicazione</div>
                    <span class="badge mkt-mode-badge">
                        {{ $project->publication_mode->label() }}
                    </span>
                </div>

                <div>
                    <div class="mkt-section-label">Brief / Note per Creativi</div>
                    @if($project->brief)
                        <div class="mkt-brief-box">{{ $project->brief }}</div>
                    @else
                        <div class="mkt-brief-empty">Nessun brief fornito.</div>
                    @endif
                </div>
            </x-panel>
            
            @if($project->shoots->isNotEmpty())
                <x-panel title="Shooting Collegati ({{ $project->shoots->count() }})" dot="var(--accent)" padded style="margin-top:20px;">
                    <div style="display:flex; flex-direction:column; gap:15px;">
                        @foreach($project->shoots as $shoot)
                            <div style="border:1px solid var(--line2); border-radius:var(--r); padding:15px; background:var(--bg2);">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                    <strong style="color:var(--text);">{{ $shoot->title }}</strong>
                                    <x-badge :status="$shoot->status->value" :label="$shoot->status->label()" />
                                </div>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:13px; margin-bottom:10px;">
                                    <div>
                                        <div style="color:var(--text3); font-size:11px; text-transform:uppercase;">Fotografo</div>
                                        <div style="color:var(--text2);">{{ $shoot->photographer->name ?? 'Da assegnare' }}</div>
                                    </div>
                                    <div>
                                        <div style="color:var(--text3); font-size:11px; text-transform:uppercase;">Data Proposta/Confermata</div>
                                        <div style="color:var(--text2);">
                                            @if($shoot->selectedSlot)
                                                {{ $shoot->selectedSlot->date->format('d/m/Y') }} ({{ $shoot->selectedSlot->period->label() }})
                                            @elseif($shoot->slots->isNotEmpty())
                                                Da confermare ({{ $shoot->slots->count() }} opzioni)
                                            @else
                                                Nessuna data
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div style="text-align:right;">
                                    <a href="{{ \App\Helpers\ShootingRouteResolver::showRouteFor(auth()->user(), $shoot) }}" class="btn btn-sm btn-g" style="font-size:11px; padding:4px 8px;">Vedi Dettaglio</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-panel>
            @endif
        </div>

        <div style="display:flex; flex-direction:column; gap:20px;">
            @if($project->type->value === 'editorial_plan' && $project->editorialPlan)
                <x-panel title="Piano Editoriale" dot="var(--blue)" padded>
                    <div class="mkt-plan-stats-grid">
                        <div>
                            <div class="mkt-stat-label">Inizio</div>
                            <div class="mkt-stat-val">{{ $project->editorialPlan->start_date?->format('d/m/Y') }}</div>
                        </div>
                        <div>
                            <div class="mkt-stat-label">Fine</div>
                            <div class="mkt-stat-val">{{ $project->editorialPlan->end_date?->format('d/m/Y') }}</div>
                        </div>
                        <div>
                            <div class="mkt-stat-label">Slot Totali</div>
                            <div class="mkt-stat-val">{{ $project->editorialPlan->post_count }}</div>
                        </div>
                    </div>

                    <div>
                        <div class="mkt-section-label">Slot Programmati</div>
                        
                        <div class="mkt-slot-list">
                            @foreach($project->editorialPlan->slots as $slot)
                                <div class="mkt-slot-card">
                                    <div>
                                        <div class="mkt-slot-time">
                                            <i data-lucide="calendar" style="width:12px; height:12px; display:inline-block; vertical-align:-2px;"></i>
                                            {{ $slot->scheduled_date?->format('d M Y') }} - {{ \Carbon\Carbon::parse($slot->scheduled_time)->format('H:i') }}
                                        </div>
                                        @if($slot->topic)
                                            <div class="mkt-slot-topic">{{ $slot->topic }}</div>
                                        @else
                                            <div style="font-size:12px; font-style:italic; color:var(--text3);">Nessun topic</div>
                                        @endif
                                    </div>
                                    <div>
                                        <x-badge :status="$slot->status->value" :label="$slot->status->label()" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-panel>
            @endif

            <x-social.n8n-status-panel :project="$project" />
            
            @if($project->n8n_request_id)
                <x-panel title="Dettagli Integrazione n8n" dot="var(--purple)" padded>
                    <div class="mkt-n8n-success-box">
                        <div class="mkt-n8n-header">
                            <div class="mkt-n8n-icon-box">
                                <i data-lucide="cpu" style="width:18px; height:18px; color:inherit;"></i>
                            </div>
                            <div>
                                <div class="mkt-n8n-title">Tracking Execution</div>
                                <div class="mkt-n8n-time">{{ $project->submitted_to_n8n_at?->format('d/m/Y H:i:s') }}</div>
                            </div>
                        </div>
                        <div class="mkt-section-label" style="margin-bottom:4px;">Request ID</div>
                        <div class="mkt-n8n-id-val">
                            {{ $project->n8n_request_id }}
                        </div>
                    </div>
                </x-panel>
            @endif
        </div>
    </div>
</div>
