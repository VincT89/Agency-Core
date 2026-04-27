<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['message' => 'Nessun elemento trovato.', 'icon' => 'inbox']));

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

foreach (array_filter((['message' => 'Nessun elemento trovato.', 'icon' => 'inbox']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:32px 16px; text-align:center; color:var(--text3); border-radius:var(--r); background:var(--bg2); border:1px dashed var(--line);">
    <div style="margin-bottom:12px; opacity:0.6;">
        <i data-lucide="<?php echo e($icon); ?>" style="width:32px; height:32px;"></i>
    </div>
    <div style="font-size:14px; font-weight:500;">
        <?php echo e($message); ?>

    </div>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views\components\empty-state.blade.php ENDPATH**/ ?>