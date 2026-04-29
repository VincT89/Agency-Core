<div>
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
            <span class="mkt-meta-label">Tipo:</span>
            <strong class="mkt-meta-value">{{ $project->type->label() }}</strong>
        </div>
        <div class="mkt-meta-sep"></div>
        <div>
            <x-badge :status="$project->status->value" :label="$project->status->label()" />
        </div>
        
        <div class="mkt-meta-action">
            @if(in_array($project->status->value, ['draft', 'n8n_failed']))
                <button wire:click="submitToN8n" class="btn btn-p" style="padding:8px 16px; font-size:12px; display:inline-flex; align-items:center; justify-content:center;" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submitToN8n">
                        @if($project->status->value === 'n8n_failed')
                            <i data-lucide="refresh-cw" style="width:14px; height:14px; vertical-align:-2px; margin-right:6px;"></i>Riprova Invio
                        @else
                            <i data-lucide="send" style="width:14px; height:14px; vertical-align:-2px; margin-right:6px;"></i>Invia a n8n
                        @endif
                    </span>
                    <span wire:loading wire:target="submitToN8n">
                        <i data-lucide="loader" class="spin" style="width:14px; height:14px; vertical-align:-2px; margin-right:6px;"></i>Invio in corso...
                    </span>
                </button>
            @endif
        </div>
    </div>

    <div class="g-2col" style="align-items:start;">
        <div>
            <x-panel title="Dettagli Briefing" dot="var(--brand)" padded>
                <div style="margin-bottom:20px;">
                    <div class="mkt-section-label">Piattaforme Destinazione</div>
                    <div class="mkt-platform-pills">
                        @forelse($project->platforms ?? [] as $plat)
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

            <x-panel title="Integrazione n8n" dot="var(--purple)" padded>
                @if($project->n8n_request_id)
                    <div class="mkt-n8n-success-box">
                        <div class="mkt-n8n-header">
                            <div class="mkt-n8n-icon-box">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            </div>
                            <div>
                                <div class="mkt-n8n-title">Webhook Inviato</div>
                                <div class="mkt-n8n-time">{{ $project->submitted_to_n8n_at?->format('d/m/Y H:i:s') }}</div>
                            </div>
                        </div>
                        <div class="mkt-section-label" style="margin-bottom:4px;">Execution ID</div>
                        <div class="mkt-n8n-id-val">
                            {{ $project->n8n_request_id }}
                        </div>
                    </div>
                @else
                    <div class="mkt-n8n-empty-box">
                        <div class="mkt-n8n-empty-icon">
                            <i data-lucide="server-off" style="width:20px; height:20px; color:var(--text3);"></i>
                        </div>
                        <h4 style="font-size:13px; color:var(--text); margin-bottom:6px;">In attesa di invio</h4>
                        <p style="font-size:12px; color:var(--text3); line-height:1.5; margin-bottom:0;">
                            Il progetto non è stato ancora inviato a n8n. Usa il pulsante in alto.
                        </p>
                    </div>
                @endif
            </x-panel>
        </div>
    </div>
</div>
