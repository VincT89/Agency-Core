<?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Shooting da Gestire','dot' => 'var(--purple)']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Shooting da Gestire','dot' => 'var(--purple)']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('headerActions', null, []); ?> 
        <a href="<?php echo e(route('admin.shooting.index')); ?>" style="font-size:12px; color:var(--text2); text-decoration:none; font-weight:500; display:flex; align-items:center; gap:4px; padding:4px 8px; border-radius:4px; transition:background 0.2s;" onmouseover="this.style.background='var(--bg3)'; this.style.color='var(--text1)'" onmouseout="this.style.background='transparent'; this.style.color='var(--text2)'">
            Vedi tutti <i data-lucide="arrow-right" style="width:14px; height:14px;"></i>
        </a>
     <?php $__env->endSlot(); ?>
    
    <div class="g-3col shoot-kpi-row">
        <div class="shoot-stat-card neutral">
            <div class="shoot-stat-val"><?php echo e($waitingPhotographer); ?></div>
            <div class="shoot-stat-lbl">In attesa fotografo</div>
        </div>
        <div class="shoot-stat-card warn">
            <div class="shoot-stat-val"><?php echo e($waitingClient); ?></div>
            <div class="shoot-stat-lbl">In attesa cliente</div>
        </div>
        <div class="shoot-stat-card danger">
            <div class="shoot-stat-val"><?php echo e($clientRejected); ?></div>
            <div class="shoot-stat-lbl">Cliente Rifiutato</div>
        </div>
    </div>
    
    <div style="padding: 16px 16px 0 16px;">
        <div style="font-size:13px; font-weight:600; color:var(--text2); margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px;">Richiedono Azione</div>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($actionShoots) > 0): ?>
        <table class="t-table" style="border-top:1px solid var(--line); margin-top:-1px;">
            <thead>
                <tr>
                    <th>Shooting</th>
                    <th>Progetto</th>
                    <th>Stato</th>
                    <th style="text-align: right">Azione</th>
                </tr>
            </thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $actionShoots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shoot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <tr onclick="window.location='<?php echo e(route('admin.shooting.show', $shoot)); ?>'" style="cursor:pointer">
                        <td class="name-col"><?php echo e($shoot->title); ?></td>
                        <td><?php echo e($shoot->project->name ?? 'Nessun Progetto'); ?></td>
                        <td><?php if (isset($component)) { $__componentOriginal5e628402af61f25a30da6602df1203fb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5e628402af61f25a30da6602df1203fb = $attributes; } ?>
<?php $component = App\View\Components\Shooting\StatusBadge::resolve(['status' => $shoot->status,'context' => 'admin'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
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
<?php endif; ?></td>
                        <td style="text-align: right">
                            <?php
                                $cta = 'Apri';
                                if ($shoot->status->value === 'waiting_client') $cta = 'Conferma Cliente';
                                elseif ($shoot->status->value === 'client_rejected') $cta = 'Rivedi';
                            ?>
                            <span style="font-size:12px; font-weight:600; color:var(--purple); background:color-mix(in srgb, var(--purple) 15%, transparent); padding:4px 8px; border-radius:4px;"><?php echo e($cta); ?></span>
                        </td>
                    </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div style="padding:16px; margin: 0 16px 16px 16px; text-align:center; color:var(--text3); font-size:13px; background:var(--bg3); border-radius:8px;">
            Nessuno shooting recente.
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/livewire/admin/dashboard/shooting-overview.blade.php ENDPATH**/ ?>