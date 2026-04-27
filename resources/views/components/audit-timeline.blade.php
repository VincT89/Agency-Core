@props(['logs' => []])

@if(auth()->user()->canViewAuditLogs())
<div style="margin-top:20px;margin-bottom:20px;">
    <x-panel title="Attività Recenti" dot="var(--text3)" padded>
        @if(count($logs))
            <div style="display:flex;flex-direction:column;gap:12px;">
                @foreach($logs as $log)
                <div style="display:flex;gap:12px;align-items:flex-start;padding-bottom:12px;border-bottom:1px solid var(--line);">
                    <div style="width:8px;height:8px;border-radius:50%;background:var(--accent);margin-top:6px;flex-shrink:0;"></div>
                    <div style="flex:1;">
                        <div style="font-family:var(--sans);font-size:14px;color:var(--text);margin-bottom:4px;">
                            {{ $log->description ?? ($log->user?->name . ' ha eseguito log di sistema: ' . $log->action) }}
                        </div>
                        <div style="font-size:12px;color:var(--text3);">
                            {{ $log->created_at->format('d/m/Y H:i') }}
                        </div>
                        
                        @if(!empty($log->new_values) || !empty($log->old_values))
                            <details style="margin-top:8px;font-size:12px;">
                                <summary style="cursor:pointer;color:var(--text2);user-select:none;">Mostra dettagli tecnici</summary>
                                <div style="margin-top:8px;padding:8px;background:var(--bg3);border-radius:4px;overflow-x:auto;">
                                    @if(!empty($log->old_values))
                                        <div style="margin-bottom:4px;color:var(--text3)"><strong>Prima:</strong> <span style="font-family:var(--mono);">{{ json_encode($log->old_values, JSON_UNESCAPED_UNICODE) }}</span></div>
                                    @endif
                                    @if(!empty($log->new_values))
                                        <div style="color:var(--text)"><strong>Dopo:</strong> <span style="font-family:var(--mono);">{{ json_encode($log->new_values, JSON_UNESCAPED_UNICODE) }}</span></div>
                                    @endif
                                </div>
                            </details>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        @else
            <div style="text-align:center;color:var(--text3);padding:16px;">Nessuna attività registrata.</div>
        @endif
    </x-panel>
</div>
@endif
