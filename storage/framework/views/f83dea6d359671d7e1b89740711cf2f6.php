<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'title' => null,
    'message' => 'Nessun elemento trovato.', 
    'icon' => 'inbox',
    'actionLabel' => null,
    'actionHref' => null
]));

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

foreach (array_filter(([
    'title' => null,
    'message' => 'Nessun elemento trovato.', 
    'icon' => 'inbox',
    'actionLabel' => null,
    'actionHref' => null
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:32px 16px; text-align:center; color:var(--text3); border-radius:var(--r); background:var(--bg2); border:1px dashed var(--line);">
    <div style="margin-bottom:12px; opacity:0.6;">
        <i data-lucide="<?php echo e($icon); ?>" <?php if($icon === 'loader'): ?> class="spin" <?php endif; ?> style="width:32px; height:32px;"></i>
    </div>
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($title): ?>
        <div style="font-size:16px; font-weight:600; color:var(--text1); margin-bottom:4px;">
            <?php echo e($title); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    
    <div style="font-size:14px; font-weight:400; max-width:400px; margin:0 auto;">
        <?php echo e($message); ?>

    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($actionLabel && $actionHref): ?>
        <div style="margin-top:16px;">
            <a href="<?php echo e($actionHref); ?>" class="btn btn-p" style="padding:8px 16px; font-size:12px; display:inline-flex; align-items:center; justify-content:center;">
                <?php echo e($actionLabel); ?>

            </a>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/components/empty-state.blade.php ENDPATH**/ ?>