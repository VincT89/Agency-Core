<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['initials' => '', 'gradient' => 'linear-gradient(135deg,#5b8ef5,#a78bfa)', 'time' => '']));

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

foreach (array_filter((['initials' => '', 'gradient' => 'linear-gradient(135deg,#5b8ef5,#a78bfa)', 'time' => '']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div class="feed-item">
    <div class="feed-av" style="background:<?php echo e($gradient); ?>"><?php echo e($initials); ?></div>
    <div class="feed-body">
        <?php echo e($slot); ?>

        <div class="feed-time"><?php echo e($time); ?></div>
    </div>
</div><?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views\components\feed-item.blade.php ENDPATH**/ ?>