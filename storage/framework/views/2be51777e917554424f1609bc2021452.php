<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'action', 
    'title' => 'Conferma Eliminazione', 
    'message' => 'Sei sicuro di voler eliminare questo elemento? Questa azione non può essere annullata.',
    'confirmText' => null,
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
    'action', 
    'title' => 'Conferma Eliminazione', 
    'message' => 'Sei sicuro di voler eliminare questo elemento? Questa azione non può essere annullata.',
    'confirmText' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div x-data="{ open: false, confirm: '' }" style="display: inline-block;">
    <div @click="open = true; confirm = ''">
        <?php echo e($slot); ?>

    </div>

    <div x-show="open" x-cloak style="display: none; align-items: center; justify-content: center; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000;" @click.self="open = false" @keydown.escape.window="open = false">
        <div style="background: var(--bg2); border: 1px solid var(--line2); border-radius: var(--r); width: 100%; max-width: 400px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);" @click.stop>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <div style="background: rgba(245, 75, 75, 0.1); color: var(--red); padding: 8px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                </div>
                <h3 style="font-family: var(--sans); font-size: 16px; font-weight: 600; color: var(--text); margin: 0;">
                    <?php echo e($title); ?>

                </h3>
            </div>
            
            <p style="color: var(--text2); font-size: 13.5px; margin-bottom: 24px; line-height: 1.5;">
                <?php echo e($message); ?>

            </p>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($confirmText): ?>
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 11px; color: var(--text3); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">
                    Digita <strong style="color:var(--text);user-select:all"><?php echo e($confirmText); ?></strong> per confermare
                </label>
                <input type="text" x-model="confirm" class="form-in" placeholder="<?php echo e($confirmText); ?>" style="width: 100%; padding: 8px 12px; font-family: var(--sans);">
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" @click="open = false" class="btn btn-g" style="padding: 8px 16px;">Annulla</button>
                <form action="<?php echo e($action); ?>" method="POST" style="margin: 0;">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                    <button type="submit" class="btn" 
                            style="background: var(--red); border-color: var(--red); color: white; padding: 8px 16px; transition: opacity 0.2s;"
                            <?php if($confirmText): ?> :disabled="confirm !== '<?php echo e(addslashes($confirmText)); ?>'" :style="confirm !== '<?php echo e(addslashes($confirmText)); ?>' ? 'opacity: 0.5; cursor: not-allowed;' : 'opacity: 1;'" <?php endif; ?>>
                        Sì, elimina
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/components/delete-modal.blade.php ENDPATH**/ ?>