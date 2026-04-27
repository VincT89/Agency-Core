<?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Social Overview','dot' => 'var(--teal)','padded' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Social Overview','dot' => 'var(--teal)','padded' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('headerActions', null, []); ?> 
        <a href="<?php echo e(route('social.posts.index')); ?>" class="social-header-link">
            Gestione Social <i data-lucide="arrow-right" style="width:14px; height:14px;"></i>
        </a>
     <?php $__env->endSlot(); ?>

    
    <div class="social-kpi-grid">
        <div class="shoot-stat-card neutral">
            <div class="shoot-stat-val" style="color: var(--purple);"><?php echo e($internalReviewCount); ?></div>
            <div class="shoot-stat-lbl">In Rev. Interna</div>
        </div>
        <div class="shoot-stat-card danger">
            <div class="shoot-stat-val" style="color: var(--red);"><?php echo e($regeneratingCount); ?></div>
            <div class="shoot-stat-lbl">In Rigenerazione</div>
        </div>
        <div class="shoot-stat-card warn">
            <div class="shoot-stat-val" style="color: var(--orange);"><?php echo e($clientChangesCount); ?></div>
            <div class="shoot-stat-lbl">Modifiche Cliente</div>
        </div>
        <div class="shoot-stat-card success">
            <div class="shoot-stat-val" style="color: var(--teal);"><?php echo e($sentToClientCount); ?></div>
            <div class="shoot-stat-lbl">Inviati al Cliente</div>
        </div>
        <div class="shoot-stat-card success">
            <div class="shoot-stat-val" style="color: var(--green);"><?php echo e($clientApprovedCount); ?></div>
            <div class="shoot-stat-lbl">Approvati</div>
        </div>
        <div class="shoot-stat-card neutral">
            <div class="shoot-stat-val" style="color: var(--blue);"><?php echo e($readyForClientCount); ?></div>
            <div class="shoot-stat-lbl">Pronti x Cliente</div>
        </div>
    </div>

    <div class="split-layout" style="gap: 20px;">
        
        
        <div style="flex: 1;">
            <div class="social-section-title">Richiedono Attenzione</div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($attentionPosts) > 0): ?>
                <table class="t-table" style="border-top:1px solid var(--line); margin-top:-1px;">
                    <thead>
                        <tr>
                            <th>Post</th>
                            <th>Stato</th>
                            <th style="text-align: right">Azione</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $attentionPosts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $post): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr onclick="window.location='<?php echo e(route('social.posts.show', $post)); ?>'" style="cursor:pointer">
                                <td class="name-col">
                                    <div class="social-post-title">
                                        [<?php echo e($post->client->name ?? 'N/D'); ?>] <?php echo e($post->title); ?>

                                    </div>
                                    <div class="social-post-meta"><?php echo e($post->updated_at->diffForHumans()); ?></div>
                                </td>
                                <td><span class="badge" style="background: <?php echo e($post->status->color()); ?>; font-size: 10px;"><?php echo e($post->status->label()); ?></span></td>
                                <td style="text-align: right">
                                    <span class="social-action-btn">Vedi</span>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="social-empty-state">
                    Nessun post critico in questo momento.
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div style="flex: 1;">
            <div class="social-section-title">Alert di Sistema</div>
            <div class="social-alerts-container">
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $staleSentToClient; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $post): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="social-alert-card yellow" onclick="window.location='<?php echo e(route('social.posts.show', $post)); ?>'">
                        <div class="social-alert-title yellow">Inviato al cliente da > 48h senza risposta</div>
                        <div class="social-alert-link"><?php echo e($post->title); ?></div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $staleRegenerating; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $post): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="social-alert-card red" onclick="window.location='<?php echo e(route('social.posts.show', $post)); ?>'">
                        <div class="social-alert-title red">In rigenerazione da > 1h (Possibile blocco n8n)</div>
                        <div class="social-alert-link"><?php echo e($post->title); ?></div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $staleInternalReview; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $post): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="social-alert-card purple" onclick="window.location='<?php echo e(route('social.posts.show', $post)); ?>'">
                        <div class="social-alert-title purple">Fermo in revisione interna da > 24h</div>
                        <div class="social-alert-link"><?php echo e($post->title); ?></div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($staleSentToClient->isEmpty() && $staleRegenerating->isEmpty() && $staleInternalReview->isEmpty()): ?>
                    <div class="social-empty-state">Nessun alert attivo. Tutto regolare.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

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
<?php endif; ?><?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/livewire/admin/dashboard/social-overview.blade.php ENDPATH**/ ?>