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

    <div class="filters-row">
        <input type="text" wire:model.live="search" class="form-in" placeholder="Cerca post o cliente..." style="width:250px">
    </div>

    <div class="g-3col">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $posts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $post): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
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

                <div style="display:flex; flex-direction:column; height: 100%;">
                    <div style="flex:1;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                            <span class="badge ba">
                                <?php echo e($post->marketingProject->client->name ?? 'Nessun Cliente'); ?>

                            </span>
                            <span style="font-family:var(--mono); font-size:10px; color:var(--text3);">
                                <?php echo e($post->created_at->format('d/m/Y')); ?>

                            </span>
                        </div>

                        <h3 style="font-size:16px; margin-bottom:10px; color:var(--text);"><?php echo e($post->title); ?></h3>
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->editorialPlanSlot): ?>
                            <div style="font-size:13px; color:var(--text2); margin-bottom:15px;">
                                <strong style="color:var(--text);">Data prevista:</strong> <?php echo e($post->editorialPlanSlot->scheduled_date?->format('d/m/Y')); ?> <?php echo e(\Carbon\Carbon::parse($post->editorialPlanSlot->scheduled_time)->format('H:i')); ?>

                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div style="margin-bottom:15px;">
                            <div style="font-family:var(--mono); font-size:10px; color:var(--text3); margin-bottom:6px; letter-spacing:.04em; text-transform:uppercase;">Piattaforme & Accessi</div>
                            <div style="display:flex; flex-direction:column; gap:6px;">
                                <?php
                                    $platforms = $post->editorialPlanSlot ? $post->editorialPlanSlot->platforms : ($post->marketingProject->platforms ?? []);
                                    $client = $post->marketingProject->client;
                                    $allReady = true;
                                    $tiktokWarning = false;
                                ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $platforms ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php
                                        $acc = $client?->socialAccountFor($plat);
                                        $status = $acc?->access_status ?? \App\Enums\Social\SocialAccessStatus::NotStarted;
                                        $color = $status->badgeColor();
                                        
                                        if ($plat !== 'tiktok' && !($acc?->isReadyToPublish() ?? false)) {
                                            $allReady = false;
                                        }
                                        if ($plat === 'tiktok' && !($acc?->isReadyToPublish() ?? false)) {
                                            $tiktokWarning = true;
                                        }
                                    ?>
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; background:var(--bg); border:1px solid var(--line2); padding:6px 8px; border-radius:4px;">
                                        <div style="display:flex; flex-direction:column; gap:4px;">
                                            <div style="display:flex; align-items:center; gap:6px;">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($plat === 'tiktok'): ?>
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text2);"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/></svg>
                                                <?php else: ?>
                                                    <i data-lucide="<?php echo e($plat); ?>" style="width:12px; height:12px; color:var(--text2);"></i>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                <span style="font-size:11px; font-family:var(--sans); font-weight:500;"><?php echo e(ucfirst($plat)); ?></span>
                                            </div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($acc): ?>
                                            <div style="font-size:9px; color:var(--text3); font-family:var(--mono);">
                                                BM: <?php echo e($acc->business_manager_id ?? 'N/A'); ?> | Metodo: <?php echo e($acc->access_method?->label() ?? 'N/A'); ?>

                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($acc->notes): ?>
                                                    <br><span style="color:var(--orange)">Note: <?php echo e(str($acc->notes)->limit(30)); ?></span>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                        <div style="display:flex; align-items:center; gap:6px;" title="Metodo: <?php echo e($acc?->access_method?->label() ?? 'Sconosciuto'); ?>">
                                            <span style="font-family:var(--mono); font-size:9px; padding:2px 4px; border-radius:3px; background:<?php echo e($color); ?>15; color:<?php echo e($color); ?>; border:1px solid <?php echo e($color); ?>30;">
                                                <?php echo e(strtoupper($status->label())); ?>

                                            </span>
                                        </div>
                                    </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$allReady): ?>
                                <div style="margin-top:8px; padding:6px; border-radius:4px; background:var(--orange)20; border:1px solid var(--orange)40; font-size:10px; color:var(--orange); display:flex; gap:4px;">
                                    <i data-lucide="alert-triangle" style="width:12px; height:12px; flex-shrink:0;"></i>
                                    <span>Attenzione: una o più piattaforme OBBLIGATORIE non sono pronte operativamente.</span>
                                </div>
                            <?php elseif($tiktokWarning): ?>
                                <div style="margin-top:8px; padding:6px; border-radius:4px; background:var(--text3)15; border:1px solid var(--line2); font-size:10px; color:var(--text2); display:flex; gap:4px;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/></svg>
                                    <span>Avviso: TikTok non è pronto. Pubblicazione consentita perché piattaforma opzionale.</span>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    <div style="border-top:1px solid var(--line); padding-top:15px; margin-top:15px; display:flex; flex-direction:column; gap:10px;">
                        <?php
                            $isMetaReady = $client ? $client->isMetaReady() : false;
                            $requiresMeta = collect($platforms ?? [])->intersect(['facebook', 'instagram'])->isNotEmpty();
                            
                            $canPublish = !$requiresMeta || $isMetaReady;
                        ?>
                        
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->currentVersion && $post->currentVersion->caption): ?>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="navigator.clipboard.writeText(`<?php echo e(addslashes(str_replace('`', '\`', $post->currentVersion->caption))); ?>`); alert('Caption copiata!')">
                                Copia Caption
                            </button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->currentVersion && $post->currentVersion->image_path): ?>
                            <a href="<?php echo e(Storage::url($post->currentVersion->image_path)); ?>" download class="btn btn-sm btn-secondary" target="_blank">
                                Scarica Media
                            </a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $platforms ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php
                                    $acc = $client?->socialAccountFor($plat);
                                    $url = $acc?->account_url;
                                    if (!$url) {
                                        if ($plat === 'facebook') $url = 'https://business.facebook.com/';
                                        elseif ($plat === 'instagram') $url = 'https://business.facebook.com/creatorstudio/';
                                        elseif ($plat === 'tiktok') $url = 'https://ads.tiktok.com/business/';
                                    }
                                ?>
                                <a href="<?php echo e($url); ?>" target="_blank" class="btn btn-sm btn-secondary">Apri <?php echo e(ucfirst($plat)); ?></a>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($client): ?>
                                <a href="<?php echo e(route('clients.show', $client)); ?>" class="btn btn-sm btn-secondary" style="margin-left:auto;">Accessi Cliente ↗</a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$canPublish): ?>
                            <div style="padding:8px; background:var(--red)10; border:1px solid var(--red)30; border-radius:4px; font-size:11px; color:var(--red); display:flex; gap:6px;">
                                <i data-lucide="lock" style="width:14px; height:14px; flex-shrink:0;"></i>
                                <span>Pubblicazione bloccata: è richiesto l'accesso Meta Business.</span>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px;">
                            <a href="<?php echo e(route('social.posts.show', $post->id)); ?>" wire:navigate class="btn btn-sm btn-g" style="border:none;">
                                Dettagli →
                            </a>
                            
                            <button wire:click="markAsPublished(<?php echo e($post->id); ?>)" class="btn btn-sm btn-p" wire:loading.attr="disabled" <?php echo e(!$canPublish ? 'disabled' : ''); ?> onclick="return confirm('Hai effettivamente pubblicato il post sulle piattaforme previste?') || event.stopImmediatePropagation()">
                                Segna Pubblicato
                            </button>
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
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <div style="grid-column: 1 / -1;">
                <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['padded' => 'true','style' => 'text-align:center; padding:40px;']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padded' => 'true','style' => 'text-align:center; padding:40px;']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                    <div style="color:var(--text3); margin-bottom:10px;"><i data-lucide="inbox" style="width:32px; height:32px; opacity:0.5;"></i></div>
                    <h3 style="color:var(--text); font-size:14px; margin-bottom:4px;">Nessun post da pubblicare</h3>
                    <p style="color:var(--text2); font-size:12px;">Tutti i post approvati sono stati pubblicati.</p>
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
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div style="margin-top:20px;">
        <?php echo e($posts->links()); ?>

    </div>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/livewire/social/publication-board.blade.php ENDPATH**/ ?>