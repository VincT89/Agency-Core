<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['log']));

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

foreach (array_filter((['log']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
$icons = [
    'created'              => ['icon' => '+', 'color' => 'rgba(232,91,42,.15)'],
    'updated'              => ['icon' => '✎', 'color' => 'rgba(91,142,245,.15)'],
    'deleted'              => ['icon' => '✕', 'color' => 'rgba(245,75,75,.15)'],
    'status_changed'       => ['icon' => '↻', 'color' => 'rgba(245,200,66,.15)'],
    'payment_registered'   => ['icon' => '✓', 'color' => 'rgba(62,207,142,.15)'],
    'uploaded_attachment'  => ['icon' => '↑', 'color' => 'rgba(91,142,245,.15)'],
    'deleted_attachment'   => ['icon' => '✕', 'color' => 'rgba(245,75,75,.15)'],
];
$cfg = $icons[$log->action] ?? ['icon' => '·', 'color' => 'var(--bg3)'];
?>
<div class="audit-item">
    <span class="audit-time"><?php echo e($log->created_at->format('H:i:s')); ?></span>
    <div class="audit-icon" style="background:<?php echo e($cfg['color']); ?>"><?php echo e($cfg['icon']); ?></div>
    <div class="audit-body">
        <b><?php echo e($log->user?->name ?? 'Sistema'); ?></b>
        — azione: <span class="ent"><?php echo e($log->action); ?></span>
        su <?php echo e(class_basename($log->auditable_type)); ?> #<?php echo e($log->auditable_id); ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($log->description): ?>
            — <?php echo e($log->description); ?>

        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="audit-foot"><?php echo e($log->created_at->diffForHumans()); ?></div>
    </div>
</div><?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/components/audit-item.blade.php ENDPATH**/ ?>