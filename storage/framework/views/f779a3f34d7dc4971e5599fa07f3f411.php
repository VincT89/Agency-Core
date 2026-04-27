<div class="split-layout">
    
    <div class="split-left">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Media Versione Attuale (v<?php echo e($post->currentVersion->version_number ?? 1); ?>)</h2>
            </div>
            
            <div class="card-body" style="text-align: center; background: var(--bg); padding: 40px;">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->currentVersion && $post->currentVersion->image_path): ?>
                    <img src="<?php echo e(Storage::url($post->currentVersion->image_path)); ?>" alt="Preview" style="max-width: 100%; max-height: 500px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                <?php else: ?>
                    <div style="padding: 100px 0; color: var(--text3);">Nessuna immagine disponibile per questa versione.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            
            <div class="card-body">
                <h3 style="font-size: 14px; margin-bottom: 10px; color: var(--text2);">Caption (Testo del Post)</h3>
                <div style="padding: 15px; background: var(--bg3); border-radius: 8px; white-space: pre-wrap;"><?php echo e($post->currentVersion->caption ?? 'Nessuna caption fornita.'); ?></div>
            </div>
            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->currentVersion && $post->currentVersion->prompt_used): ?>
            <div class="card-body" style="border-top: 1px solid var(--line);">
                <h3 style="font-size: 14px; margin-bottom: 10px; color: var(--text2);">Prompt di Rigenerazione Usato</h3>
                <div style="font-family: var(--mono); font-size: 12px; color: var(--purple); background: rgba(147, 51, 234, 0.1); padding: 10px; border-radius: 4px;">
                    > <?php echo e($post->currentVersion->prompt_used); ?>

                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <div class="split-right">
        
        <div class="card mb">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <h3 class="card-title">Gestione e Stato</h3>
                <span class="badge" style="background: <?php echo e($post->status->color()); ?>; font-size: 14px; padding: 6px 12px;"><?php echo e($post->status->label()); ?></span>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; gap: 10px;">
                
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->status->value === 'regenerating'): ?>
                    <div style="padding: 15px; background: rgba(147, 51, 234, 0.1); border: 1px dashed var(--purple); color: var(--purple); border-radius: 8px; text-align: center;">
                        <i data-lucide="loader" class="spin mb" width="24"></i>
                        <div style="font-weight: 600; font-size: 13px;">Rigenerazione in corso da parte di n8n...</div>
                        <div style="font-size: 11px;">Attendi che il sistema riceva la nuova versione.</div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('requestRegeneration', $post)): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($post->status->value, ['internal_review', 'received', 'client_changes_requested', 'changes_requested'])): ?>
                        <button wire:click="$set('showRegenerateModal', true)" class="btn btn-secondary" style="width: 100%; border-color: var(--purple); color: var(--purple);">
                            <i data-lucide="refresh-cw" width="16"></i> Richiedi Rigenerazione a n8n
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?>

                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $post)): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($post->status->value, ['internal_review', 'received', 'client_changes_requested', 'changes_requested'])): ?>
                        <button wire:click="markAsReady" class="btn btn-primary" style="width: 100%;">
                            <i data-lucide="check-circle" width="16"></i> Segna Pronto per il Cliente
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?>

                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('sendToClient', $post)): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($post->status->value, ['ready_for_client'])): ?>
                        <button wire:click="sendToClient" class="btn btn-primary" style="width: 100%; background: var(--teal); border-color: var(--teal);">
                            <i data-lucide="send" width="16"></i> Genera Link e Invia al Cliente
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($post->status->value, ['sent_to_client', 'client_approved', 'client_changes_requested'])): ?>
                        <button wire:click="sendToClient" class="btn btn-secondary" style="width: 100%;">
                            <i data-lucide="link" width="16"></i> Visualizza Link Cliente Attivo
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb">
            <div class="card-header">
                <h3 class="card-title">Discussione Interna & Cliente</h3>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $post->comments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div style="margin-bottom: 15px; padding: 12px; border-radius: 8px; background: <?php echo e($comment->visibility->value === 'client' ? 'rgba(45, 212, 191, 0.1)' : 'var(--bg3)'); ?>; border-left: 3px solid <?php echo e($comment->visibility->value === 'client' ? 'var(--teal)' : 'var(--blue)'); ?>;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 11px; color: var(--text2);">
                            <strong><?php echo e($comment->user->name ?? $comment->client_name); ?></strong>
                            <span><?php echo e($comment->created_at->format('d/m/Y H:i')); ?> (v<?php echo e($comment->version->version_number ?? '?'); ?>)</span>
                        </div>
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($comment->type->value === 'change_request'): ?>
                            <div style="font-size: 10px; font-weight: bold; color: var(--orange); margin-bottom: 6px; text-transform: uppercase;">
                                RICHIESTA MODIFICA
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        
                        <div style="font-size: 13px; line-height: 1.4;"><?php echo e($comment->body); ?></div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div style="color: var(--text3); font-size: 12px; text-align: center; padding: 20px 0;">Nessun commento finora.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="card-body" style="border-top: 1px solid var(--line);">
                <form wire:submit="addInternalComment">
                    <textarea wire:model="newCommentBody" class="form-control mb" rows="3" placeholder="Scrivi un commento interno..." required></textarea>
                    <div style="text-align: right;">
                        <button type="submit" class="btn btn-primary btn-sm">Aggiungi Commento</button>
                    </div>
                </form>
            </div>
        </div>
        
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showRegenerateModal): ?>
    <div class="overlay open" style="display:flex;">
        <div class="modal" style="max-width: 500px;" @click.stop>
            <div class="modal-header">
                <h3>Richiedi Rigenerazione a n8n</h3>
                <button class="modal-close" wire:click="$set('showRegenerateModal', false)">&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size: 13px; color: var(--text2); margin-bottom: 15px;">
                    Indica nel prompt le modifiche che desideri apportare. N8n elaborerà la richiesta e creerà una nuova versione di questo post (v<?php echo e(($post->currentVersion->version_number ?? 1) + 1); ?>).
                </p>
                <form wire:submit="requestRegeneration">
                    <div class="form-group">
                        <label class="form-label">Prompt di Modifica (Change Request)</label>
                        <textarea wire:model="regenerationPrompt" class="form-control" rows="4" placeholder="Es. L'immagine è troppo scura, schiariscila e rendi la caption più breve..." required></textarea>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                        <button type="button" class="btn btn-secondary" wire:click="$set('showRegenerateModal', false)">Annulla</button>
                        <button type="submit" class="btn btn-primary" style="background: var(--purple);">
                            <div wire:loading wire:target="requestRegeneration" style="margin-right:8px;"><i data-lucide="loader" class="spin" width="14"></i></div>
                            Invia a n8n
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showSendClientModal): ?>
    <div class="overlay open" style="display:flex;">
        <div class="modal" style="max-width: 500px;" @click.stop>
            <div class="modal-header">
                <h3>Link Pubblico per il Cliente</h3>
                <button class="modal-close" wire:click="$set('showSendClientModal', false)">&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size: 13px; color: var(--text2); margin-bottom: 15px;">
                    Copia questo link e invialo al cliente per la revisione e approvazione.
                </p>
                <div class="form-group">
                    <input type="text" class="form-control" value="<?php echo e($clientLink); ?>" readonly onclick="this.select(); document.execCommand('copy'); toast('Link copiato!');">
                </div>
                <div style="display:flex; justify-content:flex-end; margin-top:20px;">
                    <button type="button" class="btn btn-primary" wire:click="$set('showSendClientModal', false)">Chiudi</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views\livewire\social\posts\social-post-show.blade.php ENDPATH**/ ?>