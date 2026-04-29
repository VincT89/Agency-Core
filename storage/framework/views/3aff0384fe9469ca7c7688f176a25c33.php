<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve(['title' => 'Fattura '.e($invoice->number).''] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['eyebrow' => 'Fattura num.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['eyebrow' => 'Fattura num.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('title', null, []); ?> <strong><?php echo e($invoice->number); ?></strong> <?php $__env->endSlot(); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['status' => $invoice->status,'label' => $invoice->status_label]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($invoice->status),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($invoice->status_label)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $invoice)): ?>
                <a href="<?php echo e(route('invoices.edit', $invoice)); ?>" class="btn btn-g">Modifica</a>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create', App\Models\Payment::class)): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!in_array($invoice->status, ['paid', 'cancelled'])): ?>
                    <a href="<?php echo e(route('payments.create')); ?>?invoice_id=<?php echo e($invoice->id); ?>"
                       class="btn btn-p">
                        + Registra pagamento
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?>
        
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete', $invoice)): ?>
                <form action="<?php echo e(route('invoices.destroy', $invoice)); ?>" method="POST"
                      onsubmit="return confirm('Eliminare la fattura <?php echo e(addslashes($invoice->number)); ?>? Operazione irreversibile.')">
                    <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                    <button type="submit" class="btn btn-g" style="color:var(--red);border-color:rgba(245,75,75,.3)">
                        Elimina
                    </button>
                </form>
            <?php endif; ?>
         <?php $__env->endSlot(); ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>

    <div class="g-invoice">
            <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Dati Generali','dot' => 'var(--accent)','padded' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Dati Generali','dot' => 'var(--accent)','padded' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <div class="g-2col">
                    <div>
                        <div class="form-lbl" style="margin-bottom:4px;">Cliente</div>
                        <div style="font-family:var(--sans);font-weight:600;font-size:16px;">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invoice->client): ?>
                                <a href="<?php echo e(route('clients.show', $invoice->client)); ?>" style="color:var(--text);text-decoration:none"><?php echo e($invoice->client->name); ?></a>
                            <?php else: ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <div class="form-lbl" style="margin-bottom:4px;">Progetto</div>
                        <div style="font-family:var(--sans);font-weight:500;font-size:15px;">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invoice->project): ?>
                                <a href="<?php echo e(route('projects.show', $invoice->project)); ?>" style="color:var(--text2);text-decoration:none"><?php echo e($invoice->project->name); ?></a>
                            <?php else: ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="g-2col" style="margin-top:20px;padding-top:20px;border-top:1px solid var(--line);">
                    <div>
                        <div class="form-lbl" style="margin-bottom:4px;">Emissione</div>
                        <div style="font-family:var(--mono);color:var(--text);"><?php echo e($invoice->issue_date?->format('d/m/Y') ?? '—'); ?></div>
                    </div>
                    <div>
                        <div class="form-lbl" style="margin-bottom:4px;">Scadenza</div>
                        <div style="font-family:var(--mono);color:<?php echo e($invoice->due_date?->isPast() && $invoice->residual > 0 ? 'var(--red)' : 'var(--text)'); ?>;">
                            <?php echo e($invoice->due_date?->format('d/m/Y') ?? '—'); ?>

                        </div>
                    </div>
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
            
            <div style="margin-top:20px">
                <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Pagamenti Associati']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Pagamenti Associati']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                    <table class="t-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Metodo</th>
                                <th>Importo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $invoice->payments ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr onclick="window.location='<?php echo e(route('payments.show', $payment)); ?>'" style="cursor:pointer">
                                <td><?php echo e($payment->payment_date?->format('d/m/Y')); ?></td>
                                <td><?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['status' => $payment->method,'label' => $payment->method_label]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($payment->method),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($payment->method_label)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?></td>
                                <td class="mono-col">€ <?php echo e(number_format($payment->amount, 2, ',', '.')); ?></td>
                            </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr><td colspan="3" style="text-align:center;color:var(--text3);padding:16px">Nessun pagamento registrato</td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
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

        <div>
            <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Riepilogo Economico','dot' => 'var(--green)','padded' => true,'style' => 'background:var(--bg2);']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Riepilogo Economico','dot' => 'var(--green)','padded' => true,'style' => 'background:var(--bg2);']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <div style="text-align:center;margin-bottom:20px;">
                    <div class="form-lbl" style="margin-bottom:4px;">Totale Fattura</div>
                    <div style="font-size:28px;font-weight:700;font-family:var(--mono);color:var(--text);">€ <?php echo e(number_format($invoice->total, 2, ',', '.')); ?></div>
                    <div style="font-size:12px;color:var(--text3);margin-top:4px;">
                        Imponibile: € <?php echo e(number_format($invoice->subtotal, 2, ',', '.')); ?> | Imposte: € <?php echo e(number_format($invoice->tax_amount, 2, ',', '.')); ?>

                    </div>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:white;border-radius:6px;border:1px solid var(--line);margin-bottom:8px;">
                    <span style="font-size:13px;color:var(--text2);">Incassato</span>
                    <strong style="font-family:var(--mono);color:var(--green);font-size:18px;">€ <?php echo e(number_format($invoice->paid_total, 2, ',', '.')); ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:white;border-radius:6px;border:1px solid var(--line);margin-bottom:16px;">
                    <span style="font-size:13px;color:var(--text2);">Da Incassare</span>
                    <strong style="font-family:var(--mono);color:<?php echo e($invoice->residual > 0 ? 'var(--red)' : 'var(--text3)'); ?>;font-size:18px;">€ <?php echo e(number_format($invoice->residual, 2, ',', '.')); ?></strong>
                </div>

                <?php
                    $percent = $invoice->total > 0 ? ($invoice->paid_total / $invoice->total) * 100 : 0;
                ?>
                <div style="margin-top:10px;">
                    <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px;color:var(--text3);">
                        <span>Avanzamento</span>
                        <span><?php echo e(round($percent)); ?>%</span>
                    </div>
                    <?php if (isset($component)) { $__componentOriginalc24ad5afb65df15418363d12b07ac941 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc24ad5afb65df15418363d12b07ac941 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workload-bar','data' => ['percent' => $percent]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workload-bar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['percent' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($percent)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc24ad5afb65df15418363d12b07ac941)): ?>
<?php $attributes = $__attributesOriginalc24ad5afb65df15418363d12b07ac941; ?>
<?php unset($__attributesOriginalc24ad5afb65df15418363d12b07ac941); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc24ad5afb65df15418363d12b07ac941)): ?>
<?php $component = $__componentOriginalc24ad5afb65df15418363d12b07ac941; ?>
<?php unset($__componentOriginalc24ad5afb65df15418363d12b07ac941); ?>
<?php endif; ?>
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
    </div>


    
    <?php if (isset($component)) { $__componentOriginal215d7fd4cd6c9b2bd76b654f7a4f097b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal215d7fd4cd6c9b2bd76b654f7a4f097b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.audit-timeline','data' => ['logs' => $invoice->auditLogs]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('audit-timeline'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['logs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($invoice->auditLogs)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal215d7fd4cd6c9b2bd76b654f7a4f097b)): ?>
<?php $attributes = $__attributesOriginal215d7fd4cd6c9b2bd76b654f7a4f097b; ?>
<?php unset($__attributesOriginal215d7fd4cd6c9b2bd76b654f7a4f097b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal215d7fd4cd6c9b2bd76b654f7a4f097b)): ?>
<?php $component = $__componentOriginal215d7fd4cd6c9b2bd76b654f7a4f097b; ?>
<?php unset($__componentOriginal215d7fd4cd6c9b2bd76b654f7a4f097b); ?>
<?php endif; ?>

    
    <?php if (isset($component)) { $__componentOriginal27272fa58b14bd2a656b44955fdc374a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal27272fa58b14bd2a656b44955fdc374a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.attachments-panel','data' => ['model' => $invoice]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('attachments-panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['model' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($invoice)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal27272fa58b14bd2a656b44955fdc374a)): ?>
<?php $attributes = $__attributesOriginal27272fa58b14bd2a656b44955fdc374a; ?>
<?php unset($__attributesOriginal27272fa58b14bd2a656b44955fdc374a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal27272fa58b14bd2a656b44955fdc374a)): ?>
<?php $component = $__componentOriginal27272fa58b14bd2a656b44955fdc374a; ?>
<?php unset($__componentOriginal27272fa58b14bd2a656b44955fdc374a); ?>
<?php endif; ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/invoices/show.blade.php ENDPATH**/ ?>