<div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid var(--line); transition:background 0.15s;" onmouseover="this.style.background='var(--bg3)'" onmouseout="this.style.background='transparent'">
    <div style="display:flex; flex-direction:column; gap:4px;">
        <div style="display:flex; align-items:center; gap:8px;">
            <a href="<?php echo e($item->action_url); ?>" style="font-family:var(--sans); font-size:14px; font-weight:600; color:var(--text); text-decoration:none;"><?php echo e($item->shoot_name); ?></a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($highlight === 'red'): ?>
                <span style="font-size:10px; font-weight:600; padding:2px 6px; border-radius:4px; background:rgba(245,75,75,0.1); color:var(--red);"><?php echo e($item->status_label); ?></span>
            <?php elseif($highlight === 'blue'): ?>
                <span style="font-size:10px; font-weight:600; padding:2px 6px; border-radius:4px; background:rgba(66,133,244,0.1); color:var(--blue);"><?php echo e($item->status_label); ?></span>
            <?php else: ?>
                <span style="font-size:10px; font-weight:600; padding:2px 6px; border-radius:4px; background:var(--bg3); color:var(--text2);"><?php echo e($item->status_label); ?></span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div style="font-size:12px; color:var(--text3);">
            <?php echo e($item->project_name); ?> <span style="margin:0 4px; opacity:0.5;">&bull;</span> <?php echo e($item->shoot_code); ?>

        </div>
    </div>
    <div>
        <a href="<?php echo e($item->action_url); ?>" class="btn btn-sm" style="background:var(--bg); border:1px solid var(--line); color:var(--text2); text-decoration:none;"><?php echo e($item->action_label); ?></a>
    </div>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views\livewire\dashboard\partials\_queue-item.blade.php ENDPATH**/ ?>