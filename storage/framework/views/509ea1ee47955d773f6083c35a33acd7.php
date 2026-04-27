<div>
    <div class="kpi-strip" style="grid-template-columns: repeat(3, 1fr);">
        <div class="kpi-cell <?php echo e($data->kpi_da_rispondere > 0 ? 'accent-line' : ''); ?>">
            <div class="kpi-label-t">Da Rispondere</div>
            <div class="kpi-val-t <?php echo e($data->kpi_da_rispondere > 0 ? 'orange' : ''); ?>"><?php echo e($data->kpi_da_rispondere); ?></div>
            <div class="kpi-delta-t <?php echo e($data->kpi_da_rispondere > 0 ? 'down' : ''); ?>">Nuove richieste</div>
        </div>
        
        <div class="kpi-cell">
            <div class="kpi-label-t">In Attesa Cliente</div>
            <div class="kpi-val-t"><?php echo e($data->kpi_in_attesa_cliente); ?></div>
            <div class="kpi-delta-t">Slot proposti</div>
        </div>
        
        <div class="kpi-cell">
            <div class="kpi-label-t">Pianificati</div>
            <div class="kpi-val-t"><?php echo e($data->kpi_pianificati); ?></div>
            <div class="kpi-delta-t">Confermati a calendario</div>
        </div>
    </div>

    <div class="dash-grid mt-panel">
        <div>
            <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Il tuo lavoro adesso','dot' => 'var(--accent)']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Il tuo lavoro adesso','dot' => 'var(--accent)']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <?php
                    $hasWork = count($data->queue_da_rispondere) > 0 || count($data->queue_oggi) > 0 || count($data->queue_in_attesa_cliente) > 0;
                ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$hasWork): ?>
                    <div style="text-align:center;color:var(--text3);padding:32px">
                        <i data-lucide="check-circle" style="width:32px; height:32px; margin-bottom:12px; opacity:0.5;"></i>
                        <div style="font-weight:500; font-size:14px; color:var(--text2);">Tutto in regola</div>
                        <div style="font-size:12px;">Non ci sono shooting che richiedono la tua attenzione al momento.</div>
                    </div>
                <?php else: ?>
                    <table class="t-table">
                        <thead>
                            <tr>
                                <th>Shooting / Progetto</th>
                                <th>Stato</th>
                                <th style="text-align: right">Azione</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = array_merge($data->queue_da_rispondere, $data->queue_oggi, $data->queue_in_attesa_cliente); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr onclick="window.location='<?php echo e($item->action_url); ?>'" style="cursor:pointer">
                                    <td class="name-col">
                                        <?php echo e($item->shoot_name); ?>

                                        <div style="font-size:12px;color:var(--text3);font-weight:normal;margin-top:4px"><?php echo e($item->project_name); ?> • <?php echo e($item->shoot_code); ?></div>
                                    </td>
                                    <td>
                                        <?php
                                            $color = $item->priority === 1 ? 'var(--orange)' : ($item->priority === 2 ? 'var(--text2)' : 'var(--blue)');
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

        <div>
            <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Task in scadenza','dot' => 'var(--blue)','padded' => true,'style' => 'margin-bottom: 20px;']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Task in scadenza','dot' => 'var(--blue)','padded' => true,'style' => 'margin-bottom: 20px;']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($data->upcoming_tasks) > 0): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $data->upcoming_tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--line);">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="width:4px; height:4px; border-radius:50%; background:var(--text3);"></div>
                                <a href="<?php echo e(route('tasks.show', $task->id)); ?>" style="font-weight:500;color:var(--text); text-decoration:none;"><?php echo e($task->title); ?></a>
                                <span style="color:var(--text3);">—</span>
                                <span style="font-size:13px; color:var(--text2);">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($task->due_date->isToday()): ?>
                                        <span style="color:var(--orange); font-weight:600;">oggi</span>
                                    <?php elseif($task->due_date->isTomorrow()): ?>
                                        domani
                                    <?php elseif($task->due_date->isPast()): ?>
                                        <span style="color:var(--red); font-weight:600;">scaduto</span>
                                    <?php else: ?>
                                        <?php echo e($task->due_date->format('d M')); ?>

                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </span>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <?php else: ?>
                    <div style="color:var(--text3);text-align:center;padding:16px">Nessun task in scadenza.</div>
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
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views\livewire\dashboard\photographer-dashboard.blade.php ENDPATH**/ ?>