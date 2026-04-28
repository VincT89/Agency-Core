<div>
    <div style="margin-bottom:15px">
        <a href="{{ route('marketing-projects.index') }}" wire:navigate style="color:var(--text3);font-size:12px;text-decoration:none">← Torna ai progetti</a>
    </div>

    <x-page-header eyebrow="Progetto Marketing">
        <x-slot:title><strong>{{ $project->title }}</strong></x-slot:title>
        <x-slot name="actions">
            @if(in_array($project->status->value, ['draft']))
                <button wire:click="submitToN8n" class="btn btn-p" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submitToN8n">Invia a n8n</span>
                    <span wire:loading wire:target="submitToN8n">Invio in corso...</span>
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div style="display:flex;gap:15px;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:1px solid var(--line);">
        <div style="font-size:13px;color:var(--text2);">Cliente: <strong style="color:var(--text);">{{ $project->client->name ?? '-' }}</strong></div>
        <div style="color:var(--line3);">|</div>
        <div style="font-size:13px;color:var(--text2);">Tipo: <strong style="color:var(--text);">{{ $project->type->label() }}</strong></div>
        <div style="color:var(--line3);">|</div>
        <div><x-badge :status="$project->status->value" :label="$project->status->label()" /></div>
    </div>

    @if (session()->has('success'))
        <div class="flash flash-success" style="margin-bottom:20px;">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="flash flash-error" style="margin-bottom:20px;">
            {{ session('error') }}
        </div>
    @endif

    <div class="g-2col" style="align-items:start;">
        <div>
            <x-panel title="Dettagli Briefing" padded>
                <div style="margin-bottom:15px;">
                    <strong class="mkt-detail-label">Piattaforme Destinazione</strong>
                    <div class="mkt-checkbox-group" style="margin-top:5px;">
                        @foreach($project->platforms ?? [] as $plat)
                            <span class="badge bd">{{ ucfirst($plat) }}</span>
                        @endforeach
                    </div>
                </div>

                <div style="margin-bottom:15px;">
                    <strong class="mkt-detail-label">Modalità Pubblicazione</strong>
                    <p class="mkt-detail-value">{{ $project->publication_mode->label() }}</p>
                </div>

                <div>
                    <strong class="mkt-detail-label">Brief / Note per Creativi</strong>
                    <div class="mkt-brief-box">{{ $project->brief }}</div>
                </div>
            </x-panel>
        </div>

        <div>
            @if($project->type->value === 'editorial_plan' && $project->editorialPlan)
                <x-panel title="Piano Editoriale" padded>
                    <div class="mkt-plan-stats">
                        <span>Inizio: <strong>{{ $project->editorialPlan->start_date?->format('d/m/Y') }}</strong></span>
                        <span>Fine: <strong>{{ $project->editorialPlan->end_date?->format('d/m/Y') }}</strong></span>
                        <span>Slot: <strong>{{ $project->editorialPlan->post_count }}</strong></span>
                    </div>

                    <div>
                        <strong class="mkt-detail-label">Slot Programmati</strong>
                        
                        <div class="mkt-slot-list">
                            @foreach($project->editorialPlan->slots as $slot)
                                <div class="mkt-slot-item">
                                    <div>
                                        <div class="mkt-slot-date">{{ $slot->scheduled_date?->format('d M Y') }} - {{ \Carbon\Carbon::parse($slot->scheduled_time)->format('H:i') }}</div>
                                        @if($slot->topic)
                                            <div class="mkt-slot-topic">{{ $slot->topic }}</div>
                                        @endif
                                    </div>
                                    <div style="text-align:right;">
                                        <x-badge :status="$slot->status->value" :label="$slot->status->label()" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-panel>
            @endif

            <x-panel title="Integrazione n8n" padded style="margin-top:20px;">
                @if($project->n8n_request_id)
                    <div class="mkt-info-box">
                        <div style="margin-bottom:8px;"><strong class="mkt-detail-label" style="display:inline;">Richiesta ID:</strong> <br><span class="mkt-monospace">{{ $project->n8n_request_id }}</span></div>
                        <div><strong class="mkt-detail-label" style="display:inline;">Inviato il:</strong> <br>{{ $project->submitted_to_n8n_at?->format('d/m/Y H:i:s') }}</div>
                    </div>
                @else
                    <p class="mkt-empty-msg">Il progetto non è ancora stato inviato a n8n per la generazione.</p>
                @endif
            </x-panel>
        </div>
    </div>
</div>
