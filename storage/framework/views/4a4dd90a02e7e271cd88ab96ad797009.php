<div>
    <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['eyebrow' => 'Dettaglio Post','meta' => 'Ultima modifica: ' . $post->updated_at->diffForHumans()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['eyebrow' => 'Dettaglio Post','meta' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Ultima modifica: ' . $post->updated_at->diffForHumans())]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

         <?php $__env->slot('title', null, []); ?> <strong><?php echo e($post->title); ?></strong> <?php $__env->endSlot(); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <a href="<?php echo e(route('social.posts.index')); ?>" class="btn btn-g">Torna all'Archivio</a>
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

    <div class="g-2col-main">
        
        <div>
            <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Media Versione Attuale (v'.e($post->currentVersion->version_number ?? 1).')','dot' => 'var(--purple)','class' => 'social-left-panel']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Media Versione Attuale (v'.e($post->currentVersion->version_number ?? 1).')','dot' => 'var(--purple)','class' => 'social-left-panel']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <div class="social-media-preview-container">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->currentVersion?->preview_url): ?>
                        <img src="<?php echo e($post->currentVersion->preview_url); ?>" alt="Preview" class="social-media-preview-img rounded-lg">
                    <?php else: ?>
                        <div class="social-empty-preview text-gray-400 text-sm">Nessuna immagine disponibile per questa versione.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                
                <div class="social-detail-section social-caption-wrapper">
                    <div class="social-section-title">Caption (Testo del Post)</div>
                    <div class="social-caption-box"><?php echo e($post->currentVersion->caption ?? 'Nessuna caption fornita.'); ?></div>
                </div>
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->currentVersion && $post->currentVersion->prompt_used): ?>
                <div class="social-detail-section">
                    <div class="social-section-title">Prompt di Rigenerazione Usato</div>
                    <div class="social-prompt-box">
                        > <?php echo e($post->currentVersion->prompt_used); ?>

                    </div>
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

        
        <div style="display: flex; flex-direction: column; gap: 20px;">
            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->marketingProject): ?>
            <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Progetto Marketing','dot' => 'var(--orange)']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Progetto Marketing','dot' => 'var(--orange)']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <div class="social-panel-section">
                    <div style="font-size: 13px;">
                        <span style="color:var(--text2);">Progetto:</span> 
                        <a href="<?php echo e(route('marketing-projects.show', $post->marketing_project_id)); ?>" style="color:var(--text); font-weight:600; text-decoration:none;"><?php echo e($post->marketingProject->title); ?></a>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->editorialPlanSlot): ?>
                        <div style="margin-top: 8px; font-size: 13px;">
                            <span style="color:var(--text2);">Slot Editoriale:</span>
                            <span style="font-weight:600;">
                                <?php echo e($post->editorialPlanSlot->scheduled_date?->format('d/m/Y')); ?> 
                                <?php echo e(\Carbon\Carbon::parse($post->editorialPlanSlot->scheduled_time)->format('H:i')); ?>

                            </span>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Gestione e Stato','dot' => 'var(--accent)']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Gestione e Stato','dot' => 'var(--accent)']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                 <?php $__env->slot('headerActions', null, []); ?> 
                    <span class="badge" style="background: <?php echo e($post->status->color()); ?>; color: #fff; border: none; font-size: 11px;"><?php echo e($post->status->label()); ?></span>
                 <?php $__env->endSlot(); ?>
                <div class="social-panel-section">
                    
                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->status->value === 'regenerating'): ?>
                        <div class="social-regenerating-banner">
                            <i data-lucide="loader" class="spin mb" width="24"></i>
                            <div style="font-weight: 600; font-size: 13px;">Rigenerazione in corso da parte di n8n...</div>
                            <div style="font-size: 11px;">Attendi che il sistema riceva la nuova versione.</div>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('requestRegeneration', $post)): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($post->status->value, ['internal_review', 'received', 'client_changes_requested', 'changes_requested'])): ?>
                            <button wire:click="$set('showRegenerateModal', true)" class="btn btn-g social-btn-regenerate">
                                <i data-lucide="refresh-cw" width="16"></i> Richiedi Rigenerazione a n8n
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $post)): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($post->status->value, ['internal_review', 'received', 'client_changes_requested', 'changes_requested'])): ?>
                            <button wire:click="markAsReady" class="btn btn-p social-btn-ready">
                                <i data-lucide="check-circle" width="16"></i> Segna Pronto per il Cliente
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('sendToClient', $post)): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($post->status->value, ['ready_for_client'])): ?>
                            <button wire:click="sendToClient" class="btn btn-p social-btn-client">
                                <i data-lucide="send" width="16"></i> Genera Link e Invia al Cliente
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($post->status->value, ['sent_to_client', 'client_approved', 'client_changes_requested'])): ?>
                            <button wire:click="sendToClient" class="btn btn-g social-btn-client-active">
                                <i data-lucide="link" width="16"></i> Visualizza Link Cliente Attivo
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('schedule', $post)): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->isPlannable()): ?>
                            <button wire:click="$set('showScheduleModal', true)" class="btn btn-p" style="background: var(--accent); border-color: var(--accent); width: 100%; margin-top: 8px;">
                                <i data-lucide="calendar" width="16"></i> Pianifica Pubblicazione
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->activeEditorialSlot): ?>
                            <div class="social-panel-section" style="background: var(--bg2); padding: 12px; border-radius: 8px; margin-top: 12px;">
                                <div style="font-size: 12px; font-weight: 600; color: var(--text1); margin-bottom: 4px;">Slot Attivo</div>
                                <div style="font-size: 11px; color: var(--text2);">
                                    <i data-lucide="calendar-check" width="12" style="display: inline-block; vertical-align: middle; margin-right: 4px;"></i>
                                    <?php echo e($post->activeEditorialSlot->scheduled_at->format('d/m/Y H:i')); ?> su <?php echo e(ucfirst($post->activeEditorialSlot->platform->value)); ?>

                                    <br>
                                    <span style="color: <?php echo e($post->activeEditorialSlot->status->color()); ?>; font-weight: 600;"><?php echo e($post->activeEditorialSlot->status->label()); ?></span>
                                </div>
                                
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->activeEditorialSlot->status->value === 'scheduled'): ?>
                                    <div style="display: flex; gap: 8px; margin-top: 12px;">
                                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('publish', $post->activeEditorialSlot)): ?>
                                        <button wire:click="publishSlot" class="btn btn-p" style="flex: 1; font-size: 10px; padding: 4px; background: var(--green); border-color: var(--green);">
                                            Segna Pubblicato
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('cancel', $post->activeEditorialSlot)): ?>
                                        <div style="flex: 1;">
                                            <?php if (isset($component)) { $__componentOriginal2cfaf2d8c559a20e3495c081df2d0b10 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cfaf2d8c559a20e3495c081df2d0b10 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-modal','data' => ['title' => 'Annulla Pianificazione','message' => 'Sicuro di voler annullare questa pianificazione? Il post tornerà allo stato precedente e lo slot verrà liberato.','confirmText' => 'Sì, annulla slot','confirmMethod' => 'cancelSlot','btnClass' => 'btn','btnStyle' => 'background: var(--red); color: white; border-color: var(--red);','icon' => 'alert-triangle','iconColor' => 'var(--red)','iconBg' => 'rgba(245, 75, 75, 0.1)']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Annulla Pianificazione','message' => 'Sicuro di voler annullare questa pianificazione? Il post tornerà allo stato precedente e lo slot verrà liberato.','confirmText' => 'Sì, annulla slot','confirmMethod' => 'cancelSlot','btnClass' => 'btn','btnStyle' => 'background: var(--red); color: white; border-color: var(--red);','icon' => 'alert-triangle','iconColor' => 'var(--red)','iconBg' => 'rgba(245, 75, 75, 0.1)']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                                <button type="button" class="btn btn-g" style="width: 100%; font-size: 10px; padding: 4px; color: var(--red);">
                                                    Annulla Slot
                                                </button>
                                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cfaf2d8c559a20e3495c081df2d0b10)): ?>
<?php $attributes = $__attributesOriginal2cfaf2d8c559a20e3495c081df2d0b10; ?>
<?php unset($__attributesOriginal2cfaf2d8c559a20e3495c081df2d0b10); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cfaf2d8c559a20e3495c081df2d0b10)): ?>
<?php $component = $__componentOriginal2cfaf2d8c559a20e3495c081df2d0b10; ?>
<?php unset($__componentOriginal2cfaf2d8c559a20e3495c081df2d0b10); ?>
<?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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

            <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Discussione Interna & Cliente','dot' => 'var(--blue)']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Discussione Interna & Cliente','dot' => 'var(--blue)']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <div class="social-comments-container" style="padding: 16px;">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $post->comments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="social-comment-card <?php echo e($comment->visibility->value === 'client' ? 'client' : 'internal'); ?>">
                            <div class="social-comment-header">
                                <strong><?php echo e($comment->user->name ?? $comment->client_name); ?></strong>
                                <span><?php echo e($comment->created_at->format('d/m/Y H:i')); ?> (v<?php echo e($comment->version->version_number ?? '?'); ?>)</span>
                            </div>
                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($comment->type->value === 'change_request'): ?>
                                <div class="social-comment-change-req">
                                    RICHIESTA MODIFICA
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            
                            <div class="social-comment-body"><?php echo e($comment->body); ?></div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="social-empty-state">Nessun commento finora.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="social-panel-section bordered">
                    <form wire:submit="addInternalComment">
                        <textarea wire:model="newCommentBody" class="form-in" style="width: 100%; min-height: 80px; margin-bottom: 12px; resize: vertical;" placeholder="Scrivi un commento interno..." required></textarea>
                        <div style="text-align: right;">
                            <button type="submit" class="btn btn-p">Aggiungi Commento</button>
                        </div>
                    </form>
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

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showRegenerateModal): ?>
    <div class="overlay open">
        <div class="modal" @click.stop>
            <div class="modal-hd">
                <div class="modal-title">Richiedi Rigenerazione</div>
                <button class="modal-close" wire:click="$set('showRegenerateModal', false)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
            </div>
            <div style="font-family: var(--mono); font-size: 11px; color: var(--text2); margin-bottom: 20px;">
                Indica nel prompt le modifiche che desideri apportare. N8n elaborerà la richiesta e creerà una nuova versione di questo post (v<?php echo e(($post->currentVersion->version_number ?? 1) + 1); ?>).
            </div>
            <form wire:submit="requestRegeneration">
                <div class="form-g">
                    <label class="form-lbl">Prompt di Modifica (Change Request)</label>
                    <textarea wire:model="regenerationPrompt" class="form-ta" placeholder="Es. L'immagine è troppo scura, schiariscila e rendi la caption più breve..." required></textarea>
                </div>
                <div class="modal-ft">
                    <button type="button" class="btn btn-g" wire:click="$set('showRegenerateModal', false)">Annulla</button>
                    <button type="submit" class="btn btn-p" style="background: var(--purple); border-color: var(--purple);">
                        <div wire:loading wire:target="requestRegeneration" style="margin-right:8px;"><i data-lucide="loader" class="spin" width="14"></i></div>
                        Invia a n8n
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showSendClientModal): ?>
    <div class="overlay open">
        <div class="modal" @click.stop>
            <div class="modal-hd">
                <div class="modal-title">Link Pubblico Cliente</div>
                <button class="modal-close" wire:click="$set('showSendClientModal', false)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
            </div>
            <div style="font-family: var(--mono); font-size: 11px; color: var(--text2); margin-bottom: 20px;">
                Copia questo link e invialo al cliente per la revisione e approvazione.
            </div>
            <div class="form-g">
                <input type="text" class="form-in" value="<?php echo e($clientLink); ?>" readonly onclick="this.select(); document.execCommand('copy'); window.dispatchEvent(new CustomEvent('notify', { detail: 'Link copiato!' }));">
            </div>
            <div class="modal-ft">
                <button type="button" class="btn btn-p" wire:click="$set('showSendClientModal', false)">Chiudi</button>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showScheduleModal): ?>
    <div class="overlay open">
        <div class="modal" @click.stop>
            <div class="modal-hd">
                <div class="modal-title">Pianifica nel Calendario Editoriale</div>
                <button class="modal-close" wire:click="$set('showScheduleModal', false)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
            </div>
            <div style="font-family: var(--mono); font-size: 11px; color: var(--text2); margin-bottom: 20px;">
                Scegli la data di pubblicazione. Il post diventerà "Pianificato" e non potrà più essere modificato dal cliente senza prima annullare lo slot.
            </div>
            <form wire:submit="schedulePost">
                <div class="form-g">
                    <label class="form-lbl">Data e Ora (Futuro)</label>
                    <input type="datetime-local" wire:model="scheduleDate" class="form-in" required>
                </div>
                <div class="form-g">
                    <label class="form-lbl">Piattaforma Principale</label>
                    <select wire:model="schedulePlatform" class="form-sel" required>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Enums\Social\SocialPlatform::cases(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $platform): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($platform->value); ?>"><?php echo e($platform->label()); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <div class="form-g">
                    <label class="form-lbl">Note Operative (Opzionale)</label>
                    <textarea wire:model="scheduleNotes" class="form-ta" placeholder="Es. ricordarsi di taggare l'influencer..." rows="2"></textarea>
                </div>
                <div class="modal-ft">
                    <button type="button" class="btn btn-g" wire:click="$set('showScheduleModal', false)">Annulla</button>
                    <button type="submit" class="btn btn-p" style="background: var(--accent); border-color: var(--accent);">
                        <div wire:loading wire:target="schedulePost" style="margin-right:8px;"><i data-lucide="loader" class="spin" width="14"></i></div>
                        Conferma Pianificazione
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/livewire/social/posts/social-post-show.blade.php ENDPATH**/ ?>