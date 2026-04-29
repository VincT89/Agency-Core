<div style="border-left:2px solid var(--line); margin-left:12px; padding-left:24px; position:relative;">
    
    <!-- Step 1: Richiesta Creata -->
    <div style="position:relative; margin-bottom:24px;">
        <div style="position:absolute; left:-33px; top:0; width:16px; height:16px; border-radius:50%; background:var(--green); border:2px solid var(--bg1);"></div>
        <div style="font-weight:600; font-size:14px; color:var(--text1);">Richiesta Creata</div>
        <div style="font-size:13px; color:var(--text3);">Da: <?php echo e($shoot->creator->name ?? 'N/D'); ?> il <?php echo e($shoot->created_at->format('d/m/Y H:i')); ?></div>
    </div>
    
    <!-- Step 2: Risposta Fotografo -->
    <div style="position:relative; margin-bottom:24px;">
        <?php
            $hasResponded = in_array($shoot->status->value, ['waiting_client', 'client_rejected', 'photographer_rejected', 'scheduled', 'client_confirmed']);
            $isRejected = $shoot->status->value === 'photographer_rejected';
        ?>
        <div style="position:absolute; left:-33px; top:0; width:16px; height:16px; border-radius:50%; background:<?php echo e($hasResponded ? ($isRejected ? 'var(--red)' : 'var(--green)') : 'var(--line)'); ?>; border:2px solid var(--bg1); display:flex; align-items:center; justify-content:center;">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasResponded && $isRejected): ?>
                <i data-lucide="x" style="color:var(--bg1); width:10px; height:10px; stroke-width:3px;"></i>
            <?php elseif($hasResponded): ?>
                <i data-lucide="check" style="color:var(--bg1); width:10px; height:10px; stroke-width:3px;"></i>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div style="font-weight:600; font-size:14px; color:<?php echo e($hasResponded ? ($isRejected ? 'var(--red)' : 'var(--text1)') : 'var(--text3)'); ?>;">
            Risposta Fotografo
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasResponded): ?>
            <div style="font-size:13px; color:var(--text3);">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isRejected): ?>
                    <span style="color:var(--red); font-weight:600;"><i data-lucide="x" style="width:12px; height:12px; display:inline-block; margin-right:4px; vertical-align:middle;"></i>Il fotografo ha rifiutato.</span>
                <?php else: ?>
                    Il fotografo ha accettato uno slot.
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php else: ?>
            <div style="font-size:13px; color:var(--text3);">In attesa di risposta...</div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    
    <!-- Step 3: Conferma Cliente -->
    <div style="position:relative;">
        <?php
            // Se il fotografo ha rifiutato, questo step è annullato o non applicabile
            $isPhotographerRejected = $shoot->status->value === 'photographer_rejected';
            $hasConfirmed = in_array($shoot->status->value, ['scheduled', 'client_confirmed', 'client_rejected']);
            $clientRejected = $shoot->status->value === 'client_rejected';
        ?>
        
        <div style="position:absolute; left:-33px; top:0; width:16px; height:16px; border-radius:50%; background:<?php echo e($isPhotographerRejected ? 'var(--bg3)' : ($hasConfirmed ? ($clientRejected ? 'var(--red)' : 'var(--green)') : 'var(--line)')); ?>; border:2px solid var(--bg1); display:flex; align-items:center; justify-content:center;">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasConfirmed && $clientRejected): ?>
                <i data-lucide="x" style="color:var(--bg1); width:10px; height:10px; stroke-width:3px;"></i>
            <?php elseif($hasConfirmed): ?>
                <i data-lucide="check" style="color:var(--bg1); width:10px; height:10px; stroke-width:3px;"></i>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div style="font-weight:600; font-size:14px; color:<?php echo e($isPhotographerRejected ? 'var(--text3)' : ($hasConfirmed ? ($clientRejected ? 'var(--red)' : 'var(--text1)') : 'var(--text3)')); ?>; <?php echo e($isPhotographerRejected ? 'text-decoration:line-through;' : ''); ?>">
            Conferma Cliente
        </div>
        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isPhotographerRejected): ?>
            <div style="font-size:13px; color:var(--text3);">Interrotto.</div>
        <?php elseif($hasConfirmed): ?>
            <div style="font-size:13px; color:var(--text3);">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clientRejected): ?>
                    <span style="color:var(--red); font-weight:600;"><i data-lucide="x" style="width:12px; height:12px; display:inline-block; margin-right:4px; vertical-align:middle;"></i>Il cliente ha rifiutato lo slot.</span>
                <?php else: ?>
                    Il cliente ha confermato lo shooting.
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php else: ?>
            <div style="font-size:13px; color:var(--text3);">In attesa del cliente...</div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/components/shooting/workflow-timeline.blade.php ENDPATH**/ ?>