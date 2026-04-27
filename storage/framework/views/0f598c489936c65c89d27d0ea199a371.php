<div>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <h1 style="font-size:24px; font-weight:600; color:var(--text1); margin:0;">Richieste Shooting</h1>
        <a href="<?php echo e(route('social.shooting.create')); ?>" class="btn btn-p" style="display:inline-flex; align-items:center; gap:6px;">
            <i data-lucide="plus" style="width:16px; height:16px;"></i> Nuova Richiesta
        </a>
    </div>

    <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['padded' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padded' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

        <div style="display:flex; gap:16px; margin-bottom:24px;">
            <input type="text" wire:model.live.debounce.300ms="search" class="form-in" placeholder="Cerca per titolo o codice..." style="max-width:300px;">
            <select wire:model.live="status" class="form-in" style="max-width:200px;">
                <option value="">Tutti gli stati</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option value="<?php echo e($st->value); ?>"><?php echo e($st->labelForContext('social')); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
        </div>

        <table class="t-table" style="width:100%;">
            <thead>
                <tr>
                    <th>Codice</th>
                    <th>Titolo / Progetto</th>
                    <th>Fotografo</th>
                    <th>Stato</th>
                    <th>Data Creazione</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $shoots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shoot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <tr>
                        <td style="font-weight:600; color:var(--purple);"><?php echo e($shoot->code); ?></td>
                        <td>
                            <div style="font-weight:500; color:var(--text1);"><?php echo e($shoot->title); ?></div>
                            <div style="font-size:12px; color:var(--text3);"><?php echo e($shoot->project->name); ?></div>
                        </td>
                        <td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($shoot->photographer): ?>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div class="avatar-sm"><?php echo e(substr($shoot->photographer->name, 0, 1)); ?></div>
                                    <span style="font-size:13px; color:var(--text2);"><?php echo e($shoot->photographer->name); ?></span>
                                </div>
                            <?php else: ?>
                                <span style="font-size:12px; color:var(--text3);">Non assegnato</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($component)) { $__componentOriginal5e628402af61f25a30da6602df1203fb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5e628402af61f25a30da6602df1203fb = $attributes; } ?>
<?php $component = App\View\Components\Shooting\StatusBadge::resolve(['status' => $shoot->status,'context' => 'social'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shooting.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Shooting\StatusBadge::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5e628402af61f25a30da6602df1203fb)): ?>
<?php $attributes = $__attributesOriginal5e628402af61f25a30da6602df1203fb; ?>
<?php unset($__attributesOriginal5e628402af61f25a30da6602df1203fb); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5e628402af61f25a30da6602df1203fb)): ?>
<?php $component = $__componentOriginal5e628402af61f25a30da6602df1203fb; ?>
<?php unset($__componentOriginal5e628402af61f25a30da6602df1203fb); ?>
<?php endif; ?>
                        </td>
                        <td style="font-size:13px; color:var(--text2);"><?php echo e($shoot->created_at->format('d/m/Y')); ?></td>
                        <td>
                            <a href="<?php echo e(route('social.shooting.show', $shoot)); ?>" class="btn btn-outline" style="padding:4px 8px; font-size:12px;">Dettaglio</a>
                        </td>
                    </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:32px; color:var(--text3);">Nessuno shooting trovato.</td>
                    </tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
        
        <div style="margin-top:24px;">
            <?php echo e($shoots->links()); ?>

        </div>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $attributes = $__attributesOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $component = $__componentOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__componentOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views\livewire\social\shooting\requests-index.blade.php ENDPATH**/ ?>