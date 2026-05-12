@props(['logs' => []])

@if(auth()->user()->canViewAuditLogs())
<div class="audit-timeline-container">
    <x-panel title="Attività Recenti" dot="var(--text3)" padded>
        @if(count($logs))
            <div class="audit-timeline-list">
                @foreach($logs as $log)
                <div class="audit-timeline-item">
                    <div class="audit-timeline-dot"></div>
                    <div class="audit-timeline-content">
                        <div class="audit-timeline-text">
                            {{ $log->description ?? ($log->user?->name . ' ha eseguito log di sistema: ' . $log->action) }}
                        </div>
                        <div class="audit-timeline-date">
                            {{ $log->created_at->format('d/m/Y H:i') }}
                        </div>
                        
                        @if(!empty($log->new_values) || !empty($log->old_values))
                            <details class="audit-timeline-details">
                                <summary class="audit-timeline-summary">Mostra dettagli tecnici</summary>
                                <div class="audit-timeline-payload">
                                    @if(!empty($log->old_values))
                                        <div class="audit-timeline-payload-old"><strong>Prima:</strong> <span class="audit-timeline-mono">{{ json_encode($log->old_values, JSON_UNESCAPED_UNICODE) }}</span></div>
                                    @endif
                                    @if(!empty($log->new_values))
                                        <div class="audit-timeline-payload-new"><strong>Dopo:</strong> <span class="audit-timeline-mono">{{ json_encode($log->new_values, JSON_UNESCAPED_UNICODE) }}</span></div>
                                    @endif
                                </div>
                            </details>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        @else
            <div class="audit-timeline-empty">Nessuna attività registrata.</div>
        @endif
    </x-panel>
</div>
@endif
