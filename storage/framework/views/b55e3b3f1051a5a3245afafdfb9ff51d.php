<div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showWarning && $interactive): ?>
        <div style="background:rgba(245,200,66,0.1); border:1px solid rgba(245,200,66,0.3); padding:12px; border-radius:var(--r2); margin-bottom:16px; display:flex; align-items:center; gap:8px;">
            <i data-lucide="alert-triangle" style="width:16px; height:16px; color:var(--yellow); flex-shrink:0;"></i>
            <span style="font-size:12px; color:var(--text2);">Accettando uno slot, tutti gli altri verranno automaticamente rifiutati.</span>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="g-cards">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $shoot->slots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php
                $isSelected = $shoot->selected_slot_id === $slot->id;
                $hasSelection = !is_null($shoot->selected_slot_id);
                $isRejected = $slot->status->value === 'rejected' || ($hasSelection && !$isSelected);
                
                $borderColor = $isSelected ? 'var(--green)' : ($isRejected ? 'var(--line)' : 'var(--line2)');
                $bgColor = $isSelected ? 'rgba(62,207,142,0.08)' : 'var(--bg2)';
                $opacity = $isRejected ? '0.4' : '1';
                $grayscale = $isRejected ? 'grayscale(1)' : 'none';
            ?>
            <div style="border:1px solid <?php echo e($borderColor); ?>; border-radius:var(--r2); padding:16px; background:<?php echo e($bgColor); ?>; opacity:<?php echo e($opacity); ?>; filter:<?php echo e($grayscale); ?>; position:relative; transition:all 0.2s;">
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isSelected): ?>
                    <div style="position:absolute; top:-10px; right:10px; background:var(--green); color:var(--bg); font-family:var(--mono); font-size:9px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; padding:3px 8px; border-radius:2px;">
                        Selezionato
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                
                <div style="font-weight:600; color:var(--text); margin-bottom:8px; font-size:14px;">
                    <?php echo e($slot->date->format('d/m/Y')); ?>

                </div>
                
                <div style="font-size:12px; color:var(--text2); margin-bottom:12px;">
                    <?php echo e($slot->period->label()); ?><br>
                    <span style="font-family:var(--mono); font-size:11px;"><?php echo e($slot->starts_at); ?> - <?php echo e($slot->ends_at); ?></span>
                </div>
                
                <div style="margin-bottom:12px;">
                    <?php
                        $badgeClass = 'bd';
                        $badgeLabel = $slot->status->label();
                        if ($isSelected || $slot->status->value === 'accepted') {
                            $badgeClass = 'bg'; 
                            $badgeLabel = 'Confermato';
                        }
                        elseif ($isRejected) {
                            $badgeClass = 'bd'; 
                            $badgeLabel = ($hasSelection && !$isSelected) ? 'Scartato automaticamente' : 'Scartato';
                        }
                    ?>
                    <span class="badge <?php echo e($badgeClass); ?>"><?php echo e($badgeLabel); ?></span>
                </div>
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($interactive && $slot->status->value === 'proposed'): ?>
                    <button wire:click="acceptSlot(<?php echo e($slot->id); ?>)" wire:loading.attr="disabled" class="btn btn-p" style="width:100%; font-size:12px; padding:6px;">Accetta questo Slot</button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($interactive && $shoot->slots->where('status.value', 'proposed')->count() > 0): ?>
        <div style="margin-top:24px; padding-top:16px; border-top:1px solid var(--line);">
            <label class="form-lbl">Note per il rifiuto (opzionale)</label>
            <textarea wire:model="photographerNote" class="form-in" rows="2" style="margin-bottom:8px;"></textarea>
            <button wire:click="rejectAllSlots" wire:loading.attr="disabled" class="btn btn-outline" style="width:100%; color:var(--red);">Rifiuta Tutti gli Slot</button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views\components\shooting\slot-list.blade.php ENDPATH**/ ?>