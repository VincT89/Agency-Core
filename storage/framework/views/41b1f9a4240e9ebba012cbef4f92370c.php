<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['project']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['project']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $status = $project->status->value;
    $updatedAt = $project->updated_at ? $project->updated_at->format('H:i d/m/Y') : '';
    
    $config = match($status) {
        'draft' => [
            'icon' => 'edit-3',
            'title' => 'Pronto per l\'invio',
            'desc' => 'Il progetto è in bozza e pronto per essere inviato a n8n.',
            'color' => 'var(--text2)',
            'bg' => 'var(--bg2)'
        ],
        'queued_to_n8n' => [
            'icon' => 'loader',
            'title' => 'Richiesta in coda',
            'desc' => 'Il sistema sta preparando l\'invio a n8n. Attendi qualche istante...',
            'color' => 'var(--orange)',
            'bg' => 'var(--orange-bg, rgba(245, 158, 11, 0.1))',
            'spin' => true
        ],
        'submitted_to_n8n' => [
            'icon' => 'send',
            'title' => 'Inviato a n8n',
            'desc' => "Richiesta presa in carico da n8n. In attesa di ricezione dei contenuti.",
            'color' => 'var(--blue)',
            'bg' => 'var(--blue-bg, rgba(59, 130, 246, 0.1))'
        ],
        'n8n_failed' => [
            'icon' => 'alert-triangle',
            'title' => 'Invio fallito',
            'desc' => "C'è stato un problema di connessione con n8n.",
            'color' => 'var(--red)',
            'bg' => 'var(--red-bg, rgba(239, 68, 68, 0.1))'
        ],
        'posts_received' => [
            'icon' => 'check-circle',
            'title' => 'Contenuti Ricevuti',
            'desc' => 'n8n ha elaborato con successo la richiesta e generato i post.',
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
?>

<div style="background: <?php echo e($config['bg']); ?>; border-radius: var(--r); padding: 16px; display: flex; align-items: flex-start; gap: 16px; border: 1px solid <?php echo e($config['color']); ?>33;">
    <div style="color: <?php echo e($config['color']); ?>; margin-top: 2px;">
        <i data-lucide="<?php echo e($config['icon']); ?>" <?php if($config['spin'] ?? false): ?> class="spin" <?php endif; ?> style="width: 24px; height: 24px;"></i>
    </div>
    <div style="flex: 1;">
        <div style="font-weight: 600; font-size: 14px; color: <?php echo e($config['color']); ?>; margin-bottom: 4px; display: flex; align-items: center; justify-content: space-between;">
            <span><?php echo e($config['title']); ?></span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($status, ['submitted_to_n8n', 'n8n_failed', 'posts_received', 'queued_to_n8n']) && $updatedAt): ?>
                <span style="font-size: 11px; font-weight: 400; color: <?php echo e($config['color']); ?>; opacity: 0.7;">Ultimo agg: <?php echo e($updatedAt); ?></span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div style="font-size: 13px; color: <?php echo e($config['color']); ?>; opacity: 0.9; line-height: 1.4;">
            <?php echo e($config['desc']); ?>

        </div>
    </div>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/components/social/n8n-status-panel.blade.php ENDPATH**/ ?>