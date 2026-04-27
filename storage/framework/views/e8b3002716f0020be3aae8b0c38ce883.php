<div style="max-width: 1000px; margin: 40px auto; padding: 20px;">
    
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="font-family: var(--serif); font-size: 32px; margin-bottom: 10px;">Revisione Contenuto Social</h1>
        <p style="color: var(--text2);"><?php echo e($post->title); ?></p>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="flash flash-success mb"><?php echo e(session('success')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="split-layout">
        
        <div class="split-left">
            <div class="card">
                <div class="card-header" style="background: var(--bg2);">
                    <h3 style="font-size: 16px;">Anteprima</h3>
                </div>
                <div class="card-body" style="text-align: center; background: #000; padding: 0;">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->currentVersion && $post->currentVersion->image_path): ?>
                        <img src="<?php echo e(Storage::url($post->currentVersion->image_path)); ?>" alt="Preview" style="max-width: 100%; height: auto; display: block; margin: 0 auto;">
                    <?php else: ?>
                        <div style="padding: 100px 0; color: var(--text3);">Nessuna immagine disponibile.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="card-body">
                    <p style="white-space: pre-wrap; font-size: 14px; line-height: 1.6; color: var(--text);"><?php echo e($post->currentVersion->caption ?? ''); ?></p>
                </div>
            </div>
        </div>

        
        <div class="split-right">
            
            <div class="card mb">
                <div class="card-header" style="background: var(--bg2);">
                    <h3 style="font-size: 16px;">Il tuo feedback</h3>
                </div>
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->status->value === 'client_approved'): ?>
                    <div class="card-body" style="text-align: center; padding: 40px 20px;">
                        <i data-lucide="check-circle" width="48" height="48" style="color: var(--green); margin-bottom: 20px;"></i>
                        <h3 style="color: var(--green); margin-bottom: 10px;">Post Approvato</h3>
                        <p style="color: var(--text2); font-size: 14px;">Grazie per la tua approvazione! Provvederemo alla programmazione del post.</p>
                    </div>
                <?php else: ?>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Il tuo nome</label>
                            <input type="text" wire:model="clientName" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Commenti o modifiche (opzionale se approvi)</label>
                            <textarea wire:model="commentBody" class="form-control" rows="4" placeholder="Scrivi qui eventuali richieste di modifica o annotazioni..."></textarea>
                        </div>

                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button wire:click="requestChanges" class="btn btn-secondary" style="flex: 1; border-color: var(--orange); color: var(--orange);">
                                <i data-lucide="edit-3" width="16"></i> Richiedi Modifiche
                            </button>
                            
                            <button wire:click="approve" class="btn btn-primary" style="flex: 1; background: var(--green);">
                                <i data-lucide="check" width="16"></i> Approva Post
                            </button>
                        </div>
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($commentBody)): ?>
                            <div style="text-align: center; margin-top: 15px;">
                                <button wire:click="addComment" style="background: none; border: none; color: var(--text2); text-decoration: underline; cursor: pointer; font-size: 13px;">
                                    Invia solo il commento senza approvare
                                </button>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->clientComments->count() > 0): ?>
            <div class="card">
                <div class="card-header" style="background: var(--bg2);">
                    <h3 style="font-size: 16px;">Cronologia Comunicazioni</h3>
                </div>
                <div class="card-body">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $post->clientComments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div style="margin-bottom: 15px; padding: 15px; border-radius: 8px; background: rgba(255,255,255,0.03); border: 1px solid var(--line);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 12px; color: var(--text2);">
                                <strong><?php echo e($comment->client_name ?? 'Agenzia'); ?></strong>
                                <span><?php echo e($comment->created_at->format('d/m/Y H:i')); ?></span>
                            </div>
                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($comment->type->value === 'change_request'): ?>
                                <div style="font-size: 10px; font-weight: bold; color: var(--orange); margin-bottom: 8px; text-transform: uppercase;">
                                    Richiesta di modifica
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            
                            <div style="font-size: 14px; line-height: 1.5; color: var(--text);"><?php echo e($comment->body); ?></div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </div>
    </div>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views\livewire\client\social\social-post-review.blade.php ENDPATH**/ ?>