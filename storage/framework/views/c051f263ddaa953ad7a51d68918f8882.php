<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve(['title' => 'Fatture'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['eyebrow' => 'Modulo · Amministrazione','meta' => $invoices->total() . ' totali']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['eyebrow' => 'Modulo · Amministrazione','meta' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($invoices->total() . ' totali')]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('title', null, []); ?> <strong>Fatture</strong> <?php $__env->endSlot(); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create', App\Models\Invoice::class)): ?>
                <a href="<?php echo e(route('invoices.create')); ?>" class="btn btn-p">+ Nuova fattura</a>
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

    <div class="kpi-strip" style="grid-template-columns: repeat(3, 1fr); margin-bottom:20px">
        <div class="kpi-cell">
            <div class="kpi-label-t">Da incassare</div>
            <div class="kpi-val-t">€ <?php echo e(number_format($unpaidTotal, 2, ',', '.')); ?></div>
            <div class="kpi-delta-t">Totale residuo aperto</div>
        </div>
        <div class="kpi-cell <?php echo e($overdueCount > 0 ? 'accent-line' : ''); ?>">
            <div class="kpi-label-t">Scadute</div>
            <div class="kpi-val-t" style="<?php echo e($overdueCount > 0 ? 'color:var(--red)' : ''); ?>"><?php echo e($overdueCount); ?></div>
            <div class="kpi-delta-t <?php echo e($overdueCount > 0 ? 'down' : ''); ?>">Fatture oltre termine</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label-t">Bozze in sospeso</div>
            <div class="kpi-val-t"><?php echo e($draftCount); ?></div>
            <div class="kpi-delta-t">Ancora da emettere</div>
        </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
        <?php $currentStatus = request('status'); ?>
        <div class="pills" style="margin:0">
            <a href="<?php echo e(route('invoices.index', array_filter(['search' => request('search')]))); ?>" class="pill <?php echo e(!$currentStatus ? 'on' : ''); ?>">Tutte</a>
            <a href="<?php echo e(route('invoices.index', array_filter(['status' => 'issued', 'search' => request('search')]))); ?>" class="pill <?php echo e($currentStatus === 'issued' ? 'on' : ''); ?>">Emesse</a>
            <a href="<?php echo e(route('invoices.index', array_filter(['status' => 'partially_paid', 'search' => request('search')]))); ?>" class="pill <?php echo e($currentStatus === 'partially_paid' ? 'on' : ''); ?>">Parziali</a>
            <a href="<?php echo e(route('invoices.index', array_filter(['status' => 'paid', 'search' => request('search')]))); ?>" class="pill <?php echo e($currentStatus === 'paid' ? 'on' : ''); ?>">Pagate</a>
            <a href="<?php echo e(route('invoices.index', array_filter(['status' => 'overdue', 'search' => request('search')]))); ?>" class="pill <?php echo e($currentStatus === 'overdue' ? 'on' : ''); ?>">Scadute</a>
            <a href="<?php echo e(route('invoices.index', array_filter(['status' => 'draft', 'search' => request('search')]))); ?>" class="pill <?php echo e($currentStatus === 'draft' ? 'on' : ''); ?>">Bozze</a>
        </div>
        <form method="GET" action="<?php echo e(route('invoices.index')); ?>" style="display:flex;gap:8px;margin-left:auto">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentStatus): ?>
                <input type="hidden" name="status" value="<?php echo e($currentStatus); ?>">
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="Cerca fattura o cliente..." class="form-in" style="padding:5px 10px;font-size:11px;width:200px">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request('search') || $currentStatus): ?>
                <a href="<?php echo e(route('invoices.index')); ?>" class="btn btn-g" style="padding:5px 10px;font-size:11px">Reset</a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </form>
    </div>

    <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

        <table class="t-table">
            <thead>
                <tr>
                    <th>Num / Rif</th>
                    <th>Data Emiss.</th>
                    <th>Scadenza</th>
                    <th>Cliente</th>
                    <th>Totale</th>
                    <th>Residuo</th>
                    <th>Stato</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <tr onclick="window.location='<?php echo e(route('invoices.show', $invoice)); ?>'" style="cursor:pointer">
                    <td class="name-col"><?php echo e($invoice->number); ?></td>
                    <td class="mono-col"><?php echo e($invoice->issue_date?->format('d/m/Y')); ?></td>
                    <td class="mono-col" style="<?php echo e($invoice->status === 'overdue' ? 'color:var(--red)' : ''); ?>"><?php echo e($invoice->due_date?->format('d/m/Y') ?? '—'); ?></td>
                    <td>
                        <span style="font-size:12px;color:var(--text)"><?php echo e($invoice->client?->name ?? '—'); ?></span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invoice->project): ?>
                            <div style="font-family:var(--mono);font-size:10px;color:var(--text3)"><?php echo e($invoice->project->name); ?></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td class="mono-col">€ <?php echo e(number_format($invoice->total, 2, ',', '.')); ?></td>
                    <td class="mono-col" style="<?php echo e($invoice->residual > 0 ? 'color:var(--yellow)' : 'color:var(--green)'); ?>">€ <?php echo e(number_format($invoice->residual, 2, ',', '.')); ?></td>
                    <td><?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
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
<?php endif; ?></td>
                    <td>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $invoice)): ?>
                            <a href="<?php echo e(route('invoices.edit', $invoice)); ?>" class="btn-icon" onclick="event.stopPropagation()">✎</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <tr><td colspan="8" style="text-align:center;color:var(--text3);padding:32px">Nessuna fattura trovata</td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
        <?php echo e($invoices->links()); ?>

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
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views\invoices\index.blade.php ENDPATH**/ ?>