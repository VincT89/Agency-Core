<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'title' => 'Conferma', 
    'message' => 'Sei sicuro di voler procedere?',
    'confirmText' => 'Conferma',
    'confirmMethod' => null,
    'btnClass' => 'btn btn-p',
    'btnStyle' => '',
    'icon' => 'alert-circle',
    'iconColor' => 'var(--orange)',
    'iconBg' => 'rgba(255, 150, 0, 0.1)',
    'disabled' => false
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
    'title' => 'Conferma', 
    'message' => 'Sei sicuro di voler procedere?',
    'confirmText' => 'Conferma',
    'confirmMethod' => null,
    'btnClass' => 'btn btn-p',
    'btnStyle' => '',
    'icon' => 'alert-circle',
    'iconColor' => 'var(--orange)',
    'iconBg' => 'rgba(255, 150, 0, 0.1)',
    'disabled' => false
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div x-data="{ open: false }" style="display: inline-block;">
    <div @click="if(!<?php echo e($disabled ? 'true' : 'false'); ?>) open = true">
        <?php echo e($slot); ?>

    </div>

    <template x-teleport="body" wire:ignore>
        <div x-show="open" x-cloak style="display: flex; align-items: center; justify-content: center; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 9999;" @click.self="open = false" @keydown.escape.window="open = false">
        <div style="background: var(--bg2); border: 1px solid var(--line2); border-radius: var(--r); width: 100%; max-width: 400px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);" @click.stop>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <div style="background: <?php echo e($iconBg); ?>; color: <?php echo e($iconColor); ?>; padding: 8px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="<?php echo e($icon); ?>" style="width: 20px; height: 20px;"></i>
                </div>
                <h3 style="font-family: var(--sans); font-size: 16px; font-weight: 600; color: var(--text); margin: 0;">
                    <?php echo e($title); ?>

                </h3>
            </div>
            
            <p style="color: var(--text2); font-size: 13.5px; margin-bottom: 24px; line-height: 1.5;">
                <?php echo e($message); ?>

            </p>
            
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" @click="open = false" class="btn btn-g" style="padding: 8px 16px;">Annulla</button>
                <button type="button" class="<?php echo e($btnClass); ?>" 
                        style="padding: 8px 16px; <?php echo e($btnStyle); ?>"
                        <?php if($confirmMethod): ?> wire:click="<?php echo e($confirmMethod); ?>" <?php endif; ?>
                        @click="open = false">
                    <?php echo e($confirmText); ?>

                </button>
            </div>
        </div>
        </div>
    </template>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/components/confirm-modal.blade.php ENDPATH**/ ?>