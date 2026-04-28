<div class="social-accounts-container" style="width:100%; margin-top:30px;">
    <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Accessi Social','dot' => 'var(--accent)','padded' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Accessi Social','dot' => 'var(--accent)','padded' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

        <div class="social-tabs-nav" style="display:flex; gap:10px; border-bottom:1px solid var(--line); padding-bottom:10px; margin-bottom:20px;">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $platforms; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $platform): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $isMeta = $platform->value === 'facebook' || $platform->value === 'instagram';
                    $icon = $platform->value === 'facebook' ? 'facebook' : ($platform->value === 'instagram' ? 'instagram' : 'tiktok');
                    $isActive = $activeTab === $platform->value;
                ?>
                <button 
                    type="button" 
                    wire:click="$set('activeTab', '<?php echo e($platform->value); ?>')"
                    class="social-tab-btn"
                    style="padding:8px 16px; border-radius:6px; font-family:var(--sans); font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:8px; border:1px solid <?php echo e($isActive ? 'var(--accent)' : 'transparent'); ?>; background:<?php echo e($isActive ? 'var(--accent)15' : 'transparent'); ?>; color:<?php echo e($isActive ? 'var(--accent)' : 'var(--text2)'); ?>; transition:all 0.2s;"
                >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($platform->value === 'facebook'): ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="social-icon-sm">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    <?php elseif($platform->value === 'instagram'): ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="social-icon-sm">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                        </svg>
                    <?php elseif($platform->value === 'tiktok'): ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="social-icon-sm">
                          <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>
                        </svg>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php echo e($platform->label()); ?>

                </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        <div class="social-tab-contents">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $platforms; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $platform): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeTab === $platform->value): ?>
                    <?php
                        $isMeta = $platform->value === 'facebook' || $platform->value === 'instagram';
                        $titleSuffix = $isMeta ? ' (Obbligatorio)' : ' (Opzionale)';
                    ?>
                    <div class="social-account-panel">
                        <div style="margin-bottom:15px; padding-bottom:10px; border-bottom:1px dashed var(--line);">
                            <h4 style="font-family:var(--sans); font-size:16px; color:var(--text); margin:0;">
                                Configurazione <?php echo e($platform->label()); ?> <span style="font-size:12px; font-weight:normal; color:var(--text3);"><?php echo e($titleSuffix); ?></span>
                            </h4>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isMeta): ?>
                            <div class="social-account-req-notice" style="padding:10px; border-radius:6px; background:var(--orange)15; color:var(--orange); font-size:13px; margin-bottom:20px; border:1px solid var(--orange)30;">
                                <i data-lucide="alert-circle" style="width:14px; height:14px; display:inline-block; vertical-align:-2px;"></i> Richiede Meta Business Manager collegato.
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <form wire:submit="save('<?php echo e($platform->value); ?>')">
                        <div class="form-row">
                            <div class="form-g">
                                <label class="form-lbl">Account esiste?</label>
                                <select wire:model="forms.<?php echo e($platform->value); ?>.account_exists" class="form-sel w-100">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $existsOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <option value="<?php echo e($val); ?>"><?php echo e($label); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </div>
                            <div class="form-g">
                                <label class="form-lbl">Stato Accesso Operativo</label>
                                <select wire:model="forms.<?php echo e($platform->value); ?>.access_status" class="form-sel w-100">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $accessStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <option value="<?php echo e($status->value); ?>"><?php echo e($status->label()); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-g">
                                <label class="form-lbl">Nome Account (es. Pagina FB)</label>
                                <input type="text" wire:model="forms.<?php echo e($platform->value); ?>.account_name" class="form-in w-100">
                            </div>
                            <div class="form-g">
                                <label class="form-lbl">Username / Handle</label>
                                <input type="text" wire:model="forms.<?php echo e($platform->value); ?>.username" class="form-in w-100">
                            </div>
                        </div>

                        <div class="form-g mb-3">
                            <label class="form-lbl">URL Pubblico</label>
                            <input type="url" wire:model="forms.<?php echo e($platform->value); ?>.account_url" class="form-in w-100" placeholder="https://...">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['forms.'.$platform->value.'.account_url'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <div style="color:var(--red); font-size:11px; margin-top:4px;"><?php echo e($message); ?></div>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <div class="form-row">
                            <div class="form-g">
                                <label class="form-lbl">Metodo Accesso</label>
                                <select wire:model="forms.<?php echo e($platform->value); ?>.access_method" class="form-sel w-100">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $accessMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <option value="<?php echo e($method->value); ?>"><?php echo e($method->label()); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </div>
                            <div class="form-g">
                                <label class="form-lbl">Dove sono le credenziali?</label>
                                <input type="text" wire:model="forms.<?php echo e($platform->value); ?>.credential_location" class="form-in w-100" placeholder="es. Bitwarden, 1Password...">
                            </div>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($platform->value === 'facebook' || $platform->value === 'instagram'): ?>
                            <div class="form-row">
                                <div class="form-g">
                                    <label class="form-lbl">Business Manager ID</label>
                                    <input type="text" wire:model="forms.<?php echo e($platform->value); ?>.business_manager_id" class="form-in w-100">
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($platform->value === 'facebook'): ?>
                                    <div class="form-g">
                                        <label class="form-lbl">Page ID (Facebook)</label>
                                        <input type="text" wire:model="forms.<?php echo e($platform->value); ?>.page_id" class="form-in w-100">
                                    </div>
                                <?php else: ?>
                                    <div class="form-g">
                                        <label class="form-lbl">IG Business Account ID</label>
                                        <input type="text" wire:model="forms.<?php echo e($platform->value); ?>.instagram_business_account_id" class="form-in w-100">
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($platform->value === 'tiktok'): ?>
                            <div class="form-row">
                                <div class="form-g">
                                    <label class="form-lbl">Business Center ID</label>
                                    <input type="text" wire:model="forms.<?php echo e($platform->value); ?>.business_center_id" class="form-in w-100">
                                </div>
                                <div class="form-g">
                                    <label class="form-lbl">TikTok Account ID</label>
                                    <input type="text" wire:model="forms.<?php echo e($platform->value); ?>.tiktok_account_id" class="form-in w-100">
                                </div>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div class="form-g mb-3 social-ready-toggle">
                            <label class="form-check-lbl">
                                <input type="checkbox" wire:model="forms.<?php echo e($platform->value); ?>.is_ready_to_publish" class="social-ready-checkbox">
                                <span>Questo account è operativamente PRONTO per la pubblicazione?</span>
                            </label>
                        </div>

                        <div class="form-g mb-3">
                            <label class="form-lbl">Note Operative (Manuali)</label>
                            <textarea wire:model="forms.<?php echo e($platform->value); ?>.notes" class="form-ta w-100" rows="2"></textarea>
                        </div>

                        <details class="social-api-details">
                            <summary class="social-api-summary">Predisposizione API Ufficiali (Futuro)</summary>
                            <div class="social-api-content">
                                <div class="form-row">
                                    <div class="form-g">
                                        <label class="form-lbl">Provider API</label>
                                        <select wire:model="forms.<?php echo e($platform->value); ?>.api_provider" class="form-sel w-100">
                                            <option value="">-- Non Selezionato --</option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $apiProviders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $provider): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <option value="<?php echo e($provider->value); ?>"><?php echo e($provider->label()); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="form-g">
                                        <label class="form-lbl">Status API</label>
                                        <select wire:model="forms.<?php echo e($platform->value); ?>.api_status" class="form-sel w-100">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $apiStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <option value="<?php echo e($status->value); ?>"><?php echo e($status->label()); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-g mt-2">
                                    <label class="form-lbl">Note API (Es. Scadenze token o warning)</label>
                                    <textarea wire:model="forms.<?php echo e($platform->value); ?>.api_notes" class="form-ta w-100" rows="1"></textarea>
                                </div>
                                <div class="social-api-disclaimer">
                                    I Token crittografati sono gestiti lato server e non esposti in questa UI.
                                </div>
                            </div>
                        </details>

                        <div class="form-actions social-form-actions">
                            <div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('success_'.$platform->value)): ?>
                                    <div class="form-success-msg">
                                        <i data-lucide="check-circle" class="social-icon-sm"></i>
                                        <?php echo e(session('success_'.$platform->value)); ?>

                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <button 
                                type="submit" 
                                class="btn btn-p social-save-btn"
                                wire:loading.attr="disabled"
                                wire:target="save('<?php echo e($platform->value); ?>')"
                            >
                                <span wire:loading.remove wire:target="save('<?php echo e($platform->value); ?>')">
                                    <i data-lucide="save" class="social-icon-sm"></i> Salva <?php echo e($platform->label()); ?>

                                </span>
                                <span wire:loading wire:target="save('<?php echo e($platform->value); ?>')">
                                    Salvataggio...
                                </span>
                            </button>
                        </div>
                    </form>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
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
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/livewire/client/client-social-account-form.blade.php ENDPATH**/ ?>