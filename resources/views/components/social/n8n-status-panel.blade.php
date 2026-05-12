@props(['project'])

@php
    $status = $project->status->value;
    $updatedAt = $project->updated_at ? $project->updated_at->format('H:i d/m/Y') : '';
    
    $config = match($status) {
        'draft' => [
            'icon' => 'edit-3',
            'title' => 'Pronto per l\'invio',
            'desc' => 'Il progetto è in bozza e pronto per essere inviato a Sody.',
            'theme' => 'muted',
        ],
        'queued_to_n8n' => [
            'icon' => 'loader',
            'title' => 'Richiesta in coda',
            'desc' => 'Il sistema sta preparando l\'invio a Sody. Attendi qualche istante...',
            'theme' => 'warning',
            'spin' => true
        ],
        'submitted_to_n8n' => [
            'icon' => 'send',
            'title' => 'Inviato a Sody',
            'desc' => "Richiesta presa in carico da Sody. In attesa di ricezione dei contenuti.",
            'theme' => 'info',
        ],
        'n8n_failed' => [
            'icon' => 'alert-triangle',
            'title' => 'Invio fallito',
            'desc' => "C'è stato un problema di connessione con Sody.",
            'theme' => 'danger',
        ],
        'posts_received' => [
            'icon' => 'check-circle',
            'title' => 'Contenuti Ricevuti',
            'desc' => 'Sody ha elaborato con successo la richiesta e generato i post.',
            'theme' => 'success',
        ],
        default => [
            'icon' => 'info',
            'title' => 'Stato Progetto',
            'desc' => 'Il progetto si trova in uno stato avanzato o diverso da bozza.',
            'theme' => 'muted',
        ]
    };
@endphp

<div class="cmp-n8n-panel cmp-n8n-theme-{{ $config['theme'] }}">
    <div class="cmp-n8n-icon-wrap">
        <i data-lucide="{{ $config['icon'] }}" @if($config['spin'] ?? false) class="spin" @endif class="cmp-n8n-icon"></i>
    </div>
    <div class="cmp-n8n-content">
        <div class="cmp-n8n-header">
            <span>{{ $config['title'] }}</span>
            @if(in_array($status, ['submitted_to_n8n', 'n8n_failed', 'posts_received', 'queued_to_n8n']) && $updatedAt)
                <span class="cmp-n8n-updated">Ultimo agg: {{ $updatedAt }}</span>
            @endif
        </div>
        <div class="cmp-n8n-desc">
            {{ $config['desc'] }}
        </div>
    </div>
</div>
