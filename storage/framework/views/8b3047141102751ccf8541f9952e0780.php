<div>
    <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['eyebrow' => 'Social']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['eyebrow' => 'Social']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

         <?php $__env->slot('title', null, []); ?> <strong>Bacheca Pubblicazioni</strong> <?php $__env->endSlot(); ?>
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

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;justify-content:flex-end">
        <div style="position: relative;">
            <i data-lucide="search" style="position: absolute; left: 10px; top: 7px; width: 14px; height: 14px; color: var(--text3);"></i>
            <input type="text" wire:model.live="search" class="form-in" placeholder="Cerca post o cliente..." style="padding-left: 32px; width: 250px; padding-top: 5px; padding-bottom: 5px; font-size: 11px;">
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search): ?>
            <button wire:click="$set('search', '')" class="btn btn-g" style="padding:5px 10px;font-size:11px">Reset</button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                    <th style="width: 25%;">Post</th>
                    <th style="width: 20%;">Cliente</th>
                    <th style="width: 35%;">Piattaforme e Accessi</th>
                    <th style="text-align: right; width: 20%;">Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $posts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $post): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $platforms = $post->editorialPlanSlot ? $post->editorialPlanSlot->platforms : ($post->marketingProject->platforms ?? []);
                        $client = $post->marketingProject->client;
                        $allReady = true;
                        
                        foreach($platforms ?? [] as $plat) {
                            $acc = $client?->socialAccountFor($plat);
                            if ($plat !== 'tiktok' && !($acc?->isReadyToPublish() ?? false)) {
                                $allReady = false;
                            }
                        }
                        
                        $isMetaReady = $client ? $client->isMetaReady() : false;
                        $requiresMeta = collect($platforms ?? [])->intersect(['facebook', 'instagram'])->isNotEmpty();
                        $canPublish = !$requiresMeta || $isMetaReady;
                    ?>

                    <tr>
                        <td class="name-col" onclick="window.location='<?php echo e(route('social.posts.show', $post->id)); ?>'" style="cursor: pointer;">
                            <div style="margin-bottom: 4px;"><?php echo e($post->title); ?></div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->editorialPlanSlot): ?>
                                <div class="social-index-meta">
                                    <?php echo e($post->editorialPlanSlot->scheduled_date?->format('d/m/Y')); ?> <?php echo e(\Carbon\Carbon::parse($post->editorialPlanSlot->scheduled_time)->format('H:i')); ?>

                                </div>
                            <?php else: ?>
                                <div class="social-index-meta">Manuale</div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        
                        <td onclick="window.location='<?php echo e(route('social.posts.show', $post->id)); ?>'" style="cursor: pointer;">
                            <div><?php echo e($post->marketingProject->client->name ?? 'Nessun Cliente'); ?></div>
                            <div class="social-index-meta"><?php echo e($post->marketingProject->title ?? 'Nessun Progetto'); ?></div>
                        </td>
                        
                        <td>
                            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $platforms ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php
                                        $acc = $client?->socialAccountFor($plat);
                                        $status = $acc?->access_status ?? \App\Enums\Social\SocialAccessStatus::NotStarted;
                                        $color = $status->badgeColor();
                                        
                                        $url = $acc?->account_url;
                                        if (!$url) {
                                            if ($plat === 'facebook') $url = 'https://business.facebook.com/';
                                            elseif ($plat === 'instagram') $url = 'https://business.facebook.com/creatorstudio/';
                                            elseif ($plat === 'tiktok') $url = 'https://ads.tiktok.com/business/';
                                        }
                                    ?>
                                    <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 4px; background: rgba(255,255,255,0.02); border: 1px solid var(--line2); font-size: 11px;">
                                        <a href="<?php echo e($url); ?>" target="_blank" style="display: flex; align-items: center; gap: 4px; color: var(--text); text-decoration: none;" title="Apri <?php echo e(ucfirst($plat)); ?>">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($plat === 'tiktok'): ?>
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text2);"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/></svg>
                                            <?php else: ?>
                                                <i data-lucide="<?php echo e($plat); ?>" style="width: 12px; height: 12px; color: var(--text2);"></i>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <span style="font-weight: 500; text-transform: capitalize;"><?php echo e($plat); ?></span>
                                        </a>
                                        <span style="font-family: var(--mono); font-size: 9px; padding: 1px 4px; border-radius: 3px; background: <?php echo e($color); ?>15; color: <?php echo e($color); ?>; border: 1px solid <?php echo e($color); ?>30;" title="<?php echo e($acc?->notes ?? 'Nessuna nota'); ?>">
                                            <?php echo e(strtoupper($status->label())); ?>

                                        </span>
                                    </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($requiresMeta && ! $isMetaReady): ?>
                                <div style="margin-top: 6px; font-size: 10px; color: var(--red); display: flex; align-items: center; gap: 4px;">
                                    <i data-lucide="alert-octagon" style="width: 10px; height: 10px;"></i>
                                    Pubblicazione bloccata: accessi Meta Business incompleti.
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array('tiktok', $platforms ?? []) && ! $client?->socialAccountFor('tiktok')?->isReadyToPublish()): ?>
                                <div style="margin-top: 6px; font-size: 10px; color: var(--orange); display: flex; align-items: center; gap: 4px;">
                                    <i data-lucide="alert-triangle" style="width: 10px; height: 10px;"></i>
                                    TikTok non pronto: pubblicazione consentita perché opzionale.
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        
                        <td style="text-align: right; vertical-align: middle;">
                            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 8px;">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->currentVersion && $post->currentVersion->caption): ?>
                                    <button type="button" class="btn btn-sm btn-secondary" style="padding: 4px 6px;" onclick="navigator.clipboard.writeText(`<?php echo e(addslashes(str_replace('`', '\`', $post->currentVersion->caption))); ?>`); alert('Caption copiata!')" title="Copia Caption">
                                        <i data-lucide="copy" style="width: 12px; height: 12px;"></i>
                                    </button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->currentVersion?->preview_url): ?>
                                    <a href="<?php echo e($post->currentVersion->preview_url); ?>" class="btn btn-sm btn-secondary" style="padding: 4px 6px;" target="_blank" title="Apri / Scarica media">
                                        <i data-lucide="external-link" style="width: 12px; height: 12px;"></i>
                                    </a>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                
                                <button wire:click="markAsPublished(<?php echo e($post->id); ?>)" class="btn btn-sm btn-p" style="padding: 4px 10px;" wire:loading.attr="disabled" <?php echo e(!$canPublish ? 'disabled' : ''); ?> onclick="return confirm('Confermi di aver pubblicato manualmente il post sulle piattaforme previste?') || event.stopImmediatePropagation()">
                                    Pubblicato
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr>
                        <td colspan="4" class="social-empty-state" style="border: none;">
                            Nessun post da pubblicare.
                        </td>
                    </tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($posts->hasPages()): ?>
            <div style="padding: 15px; border-top: 1px solid var(--line);">
                <?php echo e($posts->links()); ?>

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
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/livewire/social/publication-board.blade.php ENDPATH**/ ?>