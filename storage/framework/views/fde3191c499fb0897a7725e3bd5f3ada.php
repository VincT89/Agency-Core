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
        <a href="<?php echo e(route('social.posts.index')); ?>" style="font-size:12px; color:var(--text2); text-decoration:none; font-weight:500; display:flex; align-items:center; gap:4px; padding:4px 8px; border-radius:4px; transition:background 0.2s;" onmouseover="this.style.background='var(--bg3)'; this.style.color='var(--text1)'" onmouseout="this.style.background='transparent'; this.style.color='var(--text2)'">
            Gestione Social <i data-lucide="arrow-right" style="width:14px; height:14px;"></i>
        </a>
     <?php $__env->endSlot(); ?>

    
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px;">
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
            <div style="font-size:13px; font-weight:600; color:var(--text2); margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px;">Richiedono Attenzione</div>
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
                                    <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px;">
                                        [<?php echo e($post->client->name ?? 'N/D'); ?>] <?php echo e($post->title); ?>

                                    </div>
                                    <div style="font-size: 11px; color: var(--text3); font-weight: normal;"><?php echo e($post->updated_at->diffForHumans()); ?></div>
                                </td>
                                <td><span class="badge" style="background: <?php echo e($post->status->color()); ?>; font-size: 10px;"><?php echo e($post->status->label()); ?></span></td>
                                <td style="text-align: right">
                                    <span style="font-size:12px; font-weight:600; color:var(--teal); background:color-mix(in srgb, var(--teal) 15%, transparent); padding:4px 8px; border-radius:4px;">Vedi</span>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="padding:16px; text-align:center; color:var(--text3); font-size:13px; background:var(--bg3); border-radius:8px;">
                    Nessun post critico in questo momento.
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div style="flex: 1;">
            <div style="font-size:13px; font-weight:600; color:var(--text2); margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px;">Alert di Sistema</div>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $staleSentToClient; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $post): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div style="padding: 12px; background: rgba(234, 179, 8, 0.1); border-left: 3px solid var(--yellow); border-radius: 4px; cursor: pointer;" onclick="window.location='<?php echo e(route('social.posts.show', $post)); ?>'">
                        <div style="font-weight: 600; font-size: 12px; color: var(--yellow);">Inviato al cliente da > 48h senza risposta</div>
                        <div style="font-size: 12px; margin-top: 4px; color: var(--text);"><?php echo e($post->title); ?></div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $staleRegenerating; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $post): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div style="padding: 12px; background: rgba(239, 68, 68, 0.1); border-left: 3px solid var(--red); border-radius: 4px; cursor: pointer;" onclick="window.location='<?php echo e(route('social.posts.show', $post)); ?>'">
                        <div style="font-weight: 600; font-size: 12px; color: var(--red);">In rigenerazione da > 1h (Possibile blocco n8n)</div>
                        <div style="font-size: 12px; margin-top: 4px; color: var(--text);"><?php echo e($post->title); ?></div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $staleInternalReview; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $post): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div style="padding: 12px; background: rgba(147, 51, 234, 0.1); border-left: 3px solid var(--purple); border-radius: 4px; cursor: pointer;" onclick="window.location='<?php echo e(route('social.posts.show', $post)); ?>'">
                        <div style="font-weight: 600; font-size: 12px; color: var(--purple);">Fermo in revisione interna da > 24h</div>
                        <div style="font-size: 12px; margin-top: 4px; color: var(--text);"><?php echo e($post->title); ?></div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($staleSentToClient->isEmpty() && $staleRegenerating->isEmpty() && $staleInternalReview->isEmpty()): ?>
                    <div style="padding: 20px; text-align: center; color: var(--text3); font-size: 13px; background: var(--bg2); border-radius: 8px;">Nessun alert attivo. Tutto regolare.</div>
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
<?php endif; ?>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views\livewire\admin\dashboard\social-overview.blade.php ENDPATH**/ ?>