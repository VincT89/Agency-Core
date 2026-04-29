<div class="client-review-page">
    <header class="client-review-header">
        <h1>Revisione Post</h1>
        <p>Controlla il contenuto prima della pubblicazione. Puoi approvarlo o richiedere modifiche al team.</p>
    </header>

    <main class="client-review-layout">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$isExpired && !$tokenObj->used_at): ?>
            
            <section class="cr-card cr-content">
                <div class="cr-section-title">
                    <i data-lucide="eye" width="16" height="16"></i>
                    <span>Anteprima del post</span>
                </div>

                <div class="cr-media-wrap">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->currentVersion?->preview_url): ?>
                        <img
                            src="<?php echo e($post->currentVersion->preview_url); ?>"
                            alt="Anteprima contenuto"
                            class="cr-media"
                        >
                    <?php else: ?>
                        <div class="cr-media-placeholder" style="padding: 100px 20px; text-align: center; color: var(--text3); font-family: var(--mono); font-size: 13px;">
                            Nessuna anteprima multimediale disponibile
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <div class="cr-format-badge">
                        1080 × 1350<br>
                        <small>Formato verticale 4:5</small>
                    </div>
                </div>

                <div class="cr-caption-block">
                    <div class="cr-label">Testo del post (Caption)</div>
                    <div class="cr-caption"><?php echo e($post->currentVersion?->caption ?? 'Nessun testo inserito.'); ?></div>
                </div>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <aside class="cr-card cr-actions">
            <div class="cr-identity">
                <div class="cr-section-title">
                    <i data-lucide="user-check" width="16" height="16" style="color: var(--blue);"></i>
                    <span>Conferma la tua identità</span>
                </div>

                <label>Il tuo nome e cognome</label>
                <input
                    type="text"
                    wire:model.defer="clientName"
                    class="form-in"
                    readonly
                    style="opacity: 0.7; cursor: not-allowed; pointer-events: none;"
                >
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['clientName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tokenObj->used_at): ?>
                <div style="margin-top: 32px; padding: 20px; border-radius: var(--r); background: rgba(62, 207, 142, 0.08); border: 1px solid rgba(62, 207, 142, 0.2);">
                    <h3 style="color: var(--green); margin: 0 0 8px 0; font-family: var(--serif); font-size: 20px;">Risposta registrata</h3>
                    <p style="margin: 0; font-size: 14px; color: var(--text2);">Questa pagina è ora in modalità sola lettura poiché hai già inviato una risposta.</p>
                </div>
            <?php elseif($isExpired): ?>
                <div style="margin-top: 32px; padding: 20px; border-radius: var(--r); background: rgba(245, 75, 75, 0.08); border: 1px solid rgba(245, 75, 75, 0.2);">
                    <h3 style="color: var(--red); margin: 0 0 8px 0; font-family: var(--serif); font-size: 20px;">Link scaduto</h3>
                    <p style="margin: 0; font-size: 14px; color: var(--text2);">Questo link non è più valido o è stato revocato dal team marketing.</p>
                </div>
            <?php else: ?>
                <div class="cr-action-box cr-approve">
                    <h3>Approva per la pubblicazione</h3>
                    <p>Il contenuto verrà siglato e passerà direttamente al team per la pianificazione.</p>

                    <label style="display: flex; align-items: flex-start; gap: 8px; margin: 16px 0; cursor: pointer;">
                        <input type="checkbox" wire:model.live="hasReadContent" class="form-check" style="margin-top: 3px;">
                        <span style="font-size: 13px; color: var(--text2); line-height: 1.4;">
                            Dichiaro di aver visionato con attenzione l'immagine e letto l'intero testo del post.
                        </span>
                    </label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['hasReadContent'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="color:var(--red); font-size:12px; margin-bottom:12px; display:block;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if (isset($component)) { $__componentOriginal2cfaf2d8c559a20e3495c081df2d0b10 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cfaf2d8c559a20e3495c081df2d0b10 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-modal','data' => ['title' => 'Approva Contenuto','message' => 'Confermi di voler approvare definitivamente questo contenuto? Passerà al team per la pubblicazione.','confirmText' => 'Sì, Approva','confirmMethod' => 'approve','btnClass' => 'btn btn-p cr-btn '.e(!$hasReadContent ? 'disabled' : '').'','icon' => 'check-circle']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Approva Contenuto','message' => 'Confermi di voler approvare definitivamente questo contenuto? Passerà al team per la pubblicazione.','confirmText' => 'Sì, Approva','confirmMethod' => 'approve','btnClass' => 'btn btn-p cr-btn '.e(!$hasReadContent ? 'disabled' : '').'','icon' => 'check-circle']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                        <button type="button" class="btn btn-p cr-btn" <?php if(!$hasReadContent): ?> disabled style="opacity:0.5;cursor:not-allowed;" <?php endif; ?>>
                            Approva e continua
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

                <div class="cr-action-box cr-change">
                    <h3>Richiedi modifiche</h3>
                    <p>Se qualcosa non va, scrivi qui le tue indicazioni per il team.</p>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$showChangesForm): ?>
                        <button
                            type="button"
                            wire:click="$toggle('showChangesForm')"
                            class="btn btn-g cr-btn"
                            style="border: 1px solid var(--orange); color: var(--orange);"
                        >
                            <i data-lucide="edit-3" width="14" height="14" style="margin-right: 4px; display: inline-block; vertical-align: -2px;"></i> Richiedi modifiche
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showChangesForm): ?>
                        <textarea
                            wire:model.defer="feedback"
                            class="form-ta"
                            style="min-height: 120px; margin-top: 16px;"
                            placeholder="Descrivi cosa vuoi modificare..."
                        ></textarea>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['feedback'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="color:var(--red); font-size:12px; margin-top:4px; display:block;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <button
                            wire:click="requestChanges"
                            wire:loading.attr="disabled"
                            wire:target="requestChanges"
                            class="btn btn-p cr-btn"
                            style="background: var(--orange); color: white; border-color: var(--orange); margin-top: 16px;"
                        >
                            Invia richiesta al team
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </aside>
    </main>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/livewire/client/social/social-post-review.blade.php ENDPATH**/ ?>