<div>
    <div class="kpi-strip">
        <div class="kpi-cell">
            <div class="kpi-label-t">Shooting Attivi</div>
            <div class="kpi-val-t"><?php echo e($data->kpi_shooting_attivi); ?></div>
            <div class="kpi-delta-t">In produzione</div>
        </div>
        
        <div class="kpi-cell <?php echo e($data->kpi_waiting_client > 0 ? 'accent-line' : ''); ?>">
            <div class="kpi-label-t">In attesa Cliente</div>
            <div class="kpi-val-t <?php echo e($data->kpi_waiting_client > 0 ? 'orange' : ''); ?>"><?php echo e($data->kpi_waiting_client); ?></div>
            <div class="kpi-delta-t <?php echo e($data->kpi_waiting_client > 0 ? 'down' : ''); ?>">Da confermare</div>
        </div>
        
        <div class="kpi-cell <?php echo e($data->kpi_client_rejected > 0 ? 'accent-line' : ''); ?>">
            <div class="kpi-label-t">Cliente Rifiutato</div>
            <div class="kpi-val-t <?php echo e($data->kpi_client_rejected > 0 ? 'red' : ''); ?>"><?php echo e($data->kpi_client_rejected); ?></div>
            <div class="kpi-delta-t <?php echo e($data->kpi_client_rejected > 0 ? 'down' : ''); ?>">Da rivedere</div>
        </div>

        <div class="kpi-cell">
            <div class="kpi-label-t">In attesa Fotografo</div>
            <div class="kpi-val-t"><?php echo e($data->kpi_waiting_photographer); ?></div>
            <div class="kpi-delta-t">In attesa risposta</div>
        </div>
        
        <div class="kpi-cell">
            <div class="kpi-label-t">Pianificati</div>
            <div class="kpi-val-t"><?php echo e($data->kpi_scheduled); ?></div>
            <div class="kpi-delta-t">Confermati e in calendario</div>
        </div>
    </div>

    <div class="mt-panel">
        <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Workflow (Attention List)','dot' => 'var(--accent)']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Workflow (Attention List)','dot' => 'var(--accent)']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($data->attention_list) === 0): ?>
                <div style="text-align:center;color:var(--text3);padding:32px">
                    <i data-lucide="check-circle" style="width:32px; height:32px; margin-bottom:12px; opacity:0.5;"></i>
                    <div style="font-weight:500; font-size:14px; color:var(--text2);">Nessun blocco rilevato</div>
                    <div style="font-size:12px;">Nessuno shooting richiede l'intervento dell'admin.</div>
                </div>
            <?php else: ?>
                <table class="t-table">
                    <thead>
                        <tr>
                            <th>Shooting / Progetto</th>
                            <th>Stato Attuale</th>
                            <th style="text-align: right">Azione</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $data->attention_list; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr onclick="window.location='<?php echo e($item->action_url); ?>'" style="cursor:pointer">
                                <td class="name-col">
                                    <?php echo e($item->shoot_name); ?>

                                    <div style="font-size:12px;color:var(--text3);font-weight:normal;margin-top:4px"><?php echo e($item->project_name); ?> • <?php echo e($item->shoot_code); ?></div>
                                </td>
                                <td>
                                    <?php
                                        $color = $item->priority === 1 ? 'var(--orange)' : ($item->priority === 2 ? 'var(--red)' : 'var(--blue)');
                                    ?>
                                    <span style="font-size:11px; font-weight:600; padding:4px 8px; border-radius:4px; background:var(--bg3); color:<?php echo e($color); ?>;"><?php echo e($item->status_label); ?></span>
                                </td>
                                <td style="text-align: right">
                                    <a href="<?php echo e($item->action_url); ?>" class="btn btn-sm" style="background:var(--bg); border:1px solid var(--line); color:var(--text2); text-decoration:none;"><?php echo e($item->action_label); ?></a>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
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
    </div>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views\livewire\dashboard\admin-dashboard.blade.php ENDPATH**/ ?>