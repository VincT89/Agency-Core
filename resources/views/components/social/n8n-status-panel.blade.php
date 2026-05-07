@props(['project'])

@php
    $status = $project->status->value;
    $updatedAt = $project->updated_at ? $project->updated_at->format('H:i d/m/Y') : '';
    
    $config = match($status) {
        'draft' => [
            'icon' => 'edit-3',
            'title' => 'Pronto per l\'invio',
            'desc' => 'Il progetto è in bozza e pronto per essere inviato a Sody.',
            'color' => 'var(--text2)',
            'bg' => 'var(--bg2)'
        ],
        'queued_to_n8n' => [
            'icon' => 'loader',
            'title' => 'Richiesta in coda',
            'desc' => 'Il sistema sta preparando l\'invio a Sody. Attendi qualche istante...',
            'color' => 'var(--orange)',
            'bg' => 'var(--orange-bg, rgba(245, 158, 11, 0.1))',
            'spin' => true
        ],
        'submitted_to_n8n' => [
            'icon' => 'send',
            'title' => 'Inviato a Sody',
            'desc' => "Richiesta presa in carico da Sody. In attesa di ricezione dei contenuti.",
            'color' => 'var(--blue)',
            'bg' => 'var(--blue-bg, rgba(59, 130, 246, 0.1))'
        ],
        'n8n_failed' => [
            'icon' => 'alert-triangle',
            'title' => 'Invio fallito',
            'desc' => "C'è stato un problema di connessione con Sody.",
            'color' => 'var(--red)',
            'bg' => 'var(--red-bg, rgba(239, 68, 68, 0.1))'
        ],
        'posts_received' => [
            'icon' => 'check-circle',
            'title' => 'Contenuti Ricevuti',
            'desc' => 'Sody ha elaborato con successo la richiesta e generato i post.',
            'color' => 'var(--green)',
            'bg' => 'var(--green-bg, rgba(16, 185, 129, 0.1))'
        ],
        default => [
            'icon' => 'info',
            'title' => 'Stato Progetto',
            'desc' => 'Il progetto si trova in uno stato avanzato o diverso da bozza.',
            'color' => 'var(--text2)',
            'bg' => 'var(--bg2)'
        ]
    };
@endphp

<div style="background: {{ $config['bg'] }}; border-radius: var(--r); padding: 16px; display: flex; align-items: flex-start; gap: 16px; border: 1px solid {{ $config['color'] }}33;">
    <div style="color: {{ $config['color'] }}; margin-top: 2px;">
        <i data-lucide="{{ $config['icon'] }}" @if($config['spin'] ?? false) class="spin" @endif style="width: 24px; height: 24px;"></i>
    </div>
    <div style="flex: 1;">
        <div style="font-weight: 600; font-size: 14px; color: {{ $config['color'] }}; margin-bottom: 4px; display: flex; align-items: center; justify-content: space-between;">
            <span>{{ $config['title'] }}</span>
            @if(in_array($status, ['submitted_to_n8n', 'n8n_failed', 'posts_received', 'queued_to_n8n']) && $updatedAt)
                <span style="font-size: 11px; font-weight: 400; color: {{ $config['color'] }}; opacity: 0.7;">Ultimo agg: {{ $updatedAt }}</span>
            @endif
        </div>
        <div style="font-size: 13px; color: {{ $config['color'] }}; opacity: 0.9; line-height: 1.4;">
            {{ $config['desc'] }}
        </div>
    </div>
</div>
