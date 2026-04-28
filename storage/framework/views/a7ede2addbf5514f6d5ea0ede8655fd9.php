<div>
    <div class="mb-4">
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['eyebrow' => 'Social','meta' => 'Wizard creazione progetto e piano editoriale']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['eyebrow' => 'Social','meta' => 'Wizard creazione progetto e piano editoriale']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('title', null, []); ?> <strong>Nuovo Progetto Marketing</strong> <?php $__env->endSlot(); ?>
             <?php $__env->slot('actions', null, []); ?> 
                <a href="<?php echo e(route('marketing-projects.index')); ?>" wire:navigate class="btn btn-g">← Indietro</a>
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
    </div>

    <!-- Progress Indicator -->
    <div class="mkt-wizard-progress">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php for($i = 1; $i <= 5; $i++): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($i == 4 && $type == 'one_shot'): ?> <?php continue; ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="mkt-wizard-step <?php echo e($step >= $i ? 'active' : 'inactive'); ?>"></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['padded' => 'true','title' => 'Step '.e($step).': '.e($step == 1 ? 'Seleziona Cliente' : (
        $step == 2 ? 'Tipo di Progetto' : (
        $step == 3 ? 'Brief e Dettagli' : (
        $step == 4 ? 'Piano Editoriale' : 'Riepilogo'
        )))).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padded' => 'true','title' => 'Step '.e($step).': '.e($step == 1 ? 'Seleziona Cliente' : (
        $step == 2 ? 'Tipo di Progetto' : (
        $step == 3 ? 'Brief e Dettagli' : (
        $step == 4 ? 'Piano Editoriale' : 'Riepilogo'
        )))).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    
        <form wire:submit.prevent="save">
            
            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step == 1): ?>
                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'step-1'; ?>wire:key="step-1">
                    <div class="form-g mb-3">
                        <label class="form-lbl">Cliente *</label>
                        <select wire:model.live="client_id" class="form-in" required>
                            <option value="">Seleziona...</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($client->id); ?>"><?php echo e($client->name); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['client_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($client_id): ?>
                    <div class="form-g mb-3">
                        <label class="form-lbl">Progetto Gestionale Associato *</label>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($projects) > 0): ?>
                            <select wire:model="project_id" class="form-in" required>
                                <option value="">Seleziona...</option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $proj): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <option value="<?php echo e($proj->id); ?>"><?php echo e($proj->name); ?></option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </select>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['project_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php else: ?>
                            <div style="padding:15px; background:var(--bg); border:1px solid var(--line2); border-radius:var(--r); color:var(--text2); font-size:14px;">
                                Questo cliente non ha progetti attivi. <a href="<?php echo e(route('projects.create')); ?>" style="color:var(--brand); text-decoration:underline;">Crea prima un progetto gestionale</a>.
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step == 2): ?>
                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'step-2'; ?>wire:key="step-2">
                    <div class="mkt-type-grid">
                        <div wire:click="$set('type', 'one_shot')" class="mkt-type-card <?php echo e($type == 'one_shot' ? 'selected' : ''); ?>">
                            <h3>Una Tantum</h3>
                            <p class="mkt-type-desc">Post singolo o campagna isolata. Nessun piano temporale complesso.</p>
                        </div>
                        <div wire:click="$set('type', 'editorial_plan')" class="mkt-type-card <?php echo e($type == 'editorial_plan' ? 'selected' : ''); ?>">
                            <h3>Piano Editoriale</h3>
                            <p class="mkt-type-desc">Pianificazione a medio termine (es. 30/45 giorni) con molteplici slot di pubblicazione.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step == 3): ?>
                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'step-3'; ?>wire:key="step-3">
                    <div class="form-g mb-3">
                        <label class="form-lbl">Titolo Progetto *</label>
                        <input type="text" wire:model="title" class="form-in" placeholder="Es. Lancio prodotto XYZ" required>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="form-g mb-3">
                        <label class="form-lbl">Briefing per l'AI / Creativi *</label>
                        <textarea wire:model="brief" class="form-in" rows="5" placeholder="Descrivi l'obiettivo, il tono di voce, il target..." required></textarea>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['brief'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="form-g mb-3">
                        <label class="form-lbl">Piattaforme *</label>
                        <div class="mkt-checkbox-group">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $availablePlatforms; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <label class="mkt-checkbox-label">
                                    <input type="checkbox" wire:model.live="platforms" value="<?php echo e($plat); ?>"> <?php echo e(ucfirst($plat)); ?>

                                </label>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['platforms'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($platforms) > 0 && $client_id && $this->clientSocialStatus): ?>
                            <div style="margin-top:10px; padding:12px; background:var(--bg); border:1px solid var(--line2); border-radius:var(--r);">
                                <div style="font-family:var(--mono); font-size:10px; color:var(--text3); margin-bottom:8px; text-transform:uppercase;">Stato Accessi Social</div>
                                
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array('facebook', $platforms) || in_array('instagram', $platforms)): ?>
                                    <div style="display:flex; justify-content:space-between; align-items:center; padding:4px 0; border-bottom:1px solid var(--line);">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <i data-lucide="facebook" style="width:14px; height:14px; color:var(--text2);"></i>
                                            <span style="font-size:12px; font-family:var(--sans);">Meta (Facebook / Instagram)</span>
                                        </div>
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->clientSocialStatus['is_meta_ready']): ?>
                                                <span style="font-size:10px; font-family:var(--mono); color:var(--green); padding:2px 6px; border:1px solid var(--green)40; border-radius:4px; background:var(--green)15;">PRONTO</span>
                                                <i data-lucide="check-circle" style="width:14px; height:14px; color:var(--green);" title="Pronto per la pubblicazione"></i>
                                            <?php else: ?>
                                                <span style="font-size:10px; font-family:var(--mono); color:var(--orange); padding:2px 6px; border:1px solid var(--orange)40; border-radius:4px; background:var(--orange)15;">INCOMPLETO</span>
                                                <i data-lucide="alert-triangle" style="width:14px; height:14px; color:var(--orange);" title="Accesso non operativo"></i>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array('tiktok', $platforms)): ?>
                                    <div style="display:flex; justify-content:space-between; align-items:center; padding:4px 0;">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text2);">
                                                <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>
                                            </svg>
                                            <span style="font-size:12px; font-family:var(--sans);">TikTok</span>
                                        </div>
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->clientSocialStatus['is_tiktok_ready']): ?>
                                                <span style="font-size:10px; font-family:var(--mono); color:var(--green); padding:2px 6px; border:1px solid var(--green)40; border-radius:4px; background:var(--green)15;">PRONTO</span>
                                                <i data-lucide="check-circle" style="width:14px; height:14px; color:var(--green);" title="Pronto per la pubblicazione"></i>
                                            <?php else: ?>
                                                <span style="font-size:10px; font-family:var(--mono); color:var(--text3); padding:2px 6px; border:1px solid var(--text3)40; border-radius:4px; background:var(--text3)15;">NON CONFIGURATO</span>
                                                <i data-lucide="alert-circle" style="width:14px; height:14px; color:var(--text3);" title="Accesso non configurato (Opzionale)"></i>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((in_array('facebook', $platforms) || in_array('instagram', $platforms)) && !$this->clientSocialStatus['is_meta_ready']): ?>
                                    <div style="margin-top:8px; font-size:11px; color:var(--red); display:flex; gap:6px;">
                                        <i data-lucide="alert-octagon" style="width:14px; height:14px; flex-shrink:0;"></i>
                                        <span><strong>Attenzione:</strong> Meta Business è richiesto per l'invio al workflow automatico. Il progetto sarà salvato in Bozza e l'invio verrà bloccato finché non configuri gli accessi.</span>
                                    </div>
                                <?php elseif(in_array('tiktok', $platforms) && !$this->clientSocialStatus['is_tiktok_ready']): ?>
                                    <div style="margin-top:8px; font-size:11px; color:var(--orange); display:flex; gap:6px;">
                                        <i data-lucide="info" style="width:14px; height:14px; flex-shrink:0;"></i>
                                        <span>TikTok non è configurato. Potrai comunque inviare il piano (poiché opzionale), ma la pubblicazione andrà gestita manualmente.</span>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="form-g mb-3">
                        <label class="form-lbl">Modalità Pubblicazione</label>
                        <select wire:model="publication_mode" class="form-in">
                            <option value="manual">Manuale (L'operatore pubblica sui social)</option>
                            <option value="automatic">Automatica (Tramite n8n/API se supportato)</option>
                        </select>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step == 4): ?>
                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'step-4'; ?>wire:key="step-4">
                    <div class="g-2col mb-3">
                        <div class="form-g">
                            <label class="form-lbl">Data Inizio</label>
                            <input type="date" wire:model="start_date" class="form-in">
                        </div>
                        <div class="form-g">
                            <label class="form-lbl">Data Fine</label>
                            <input type="date" wire:model="end_date" class="form-in">
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="mkt-header">
                            <label class="form-lbl" style="margin:0;">Slot di Pubblicazione</label>
                            <button type="button" wire:click="addSlot" class="btn btn-sm btn-secondary">+ Aggiungi Slot</button>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $planSlots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $slot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="mkt-slot-card" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'slot-'.e($index).''; ?>wire:key="slot-<?php echo e($index); ?>">
                                <button type="button" wire:click="removeSlot(<?php echo e($index); ?>)" class="mkt-slot-remove">&times; Rimuovi</button>
                                
                                <div class="g-2col mb-2">
                                    <div class="form-g">
                                        <label class="form-lbl">Data *</label>
                                        <input type="date" wire:model="planSlots.<?php echo e($index); ?>.date" class="form-in" required>
                                    </div>
                                    <div class="form-g">
                                        <label class="form-lbl">Orario *</label>
                                        <input type="time" wire:model="planSlots.<?php echo e($index); ?>.time" class="form-in" required>
                                    </div>
                                </div>
                                <div class="form-g mb-2">
                                    <label class="form-lbl">Topic (Opzionale)</label>
                                    <input type="text" wire:model="planSlots.<?php echo e($index); ?>.topic" class="form-in" placeholder="Es. Focus sui benefici del prodotto">
                                </div>
                                <div class="form-g">
                                    <label class="form-lbl" style="font-size:12px;">Piattaforme</label>
                                    <div class="mkt-slot-platforms">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $availablePlatforms; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <label class="mkt-checkbox-label">
                                                <input type="checkbox" wire:model="planSlots.<?php echo e($index); ?>.platforms" value="<?php echo e($plat); ?>"> <?php echo e(ucfirst($plat)); ?>

                                            </label>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($planSlots) === 0): ?>
                            <p class="mkt-slot-empty">Nessuno slot configurato. Aggiungine almeno uno per l'approvazione.</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step == 5): ?>
                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'step-5'; ?>wire:key="step-5">
                    <div class="mkt-summary-card">
                        <h3 class="mkt-summary-title">Riepilogo Progetto</h3>
                        
                        <table class="mkt-summary-table">
                            <tr><td class="label">Titolo</td><td><strong><?php echo e($title); ?></strong></td></tr>
                            <tr><td class="label">Tipo</td><td><?php echo e($type === 'one_shot' ? 'Una Tantum' : 'Piano Editoriale'); ?></td></tr>
                            <tr><td class="label">Piattaforme base</td><td><?php echo e(implode(', ', array_map('ucfirst', $platforms))); ?></td></tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($type === 'editorial_plan'): ?>
                                <tr><td class="label">Slot configurati</td><td><?php echo e(count($planSlots)); ?></td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </table>
                        
                        <div style="margin-top:20px;">
                            <h4 style="font-size:14px; margin-bottom:5px;">Brief:</h4>
                            <p style="font-size:13px; color:var(--text2); white-space:pre-wrap;"><?php echo e($brief); ?></p>
                        </div>
                    </div>

                    <div class="mkt-alert-info">
                        <i class="fa fa-info-circle"></i> Il progetto verrà salvato in stato <strong>Bozza</strong>. Potrai inviarlo a n8n in un secondo momento dalla pagina di dettaglio.
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <div class="mkt-wizard-footer">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step > 1): ?>
                    <button type="button" wire:click="prevStep" wire:target="prevStep" class="btn btn-secondary" wire:loading.attr="disabled">Indietro</button>
                <?php else: ?>
                    <div></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step < 5): ?>
                    <button type="button" wire:click="nextStep" wire:target="nextStep" class="btn btn-g" wire:loading.attr="disabled">Avanti</button>
                <?php else: ?>
                    <button type="submit" wire:target="save" class="btn btn-success" wire:loading.attr="disabled">Salva Progetto</button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

        </form>
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
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/livewire/social/marketing-projects/create.blade.php ENDPATH**/ ?>