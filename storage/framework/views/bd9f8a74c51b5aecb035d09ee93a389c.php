<div>
<style>
    html, body.guest-body {
        overflow-y: auto !important;
        height: 100% !important;
        background: var(--bg);
        margin: 0;
        padding: 0;
    }
    
    .review-wrapper {
        max-width: 1100px;
        margin: 0 auto;
        padding: 40px 20px;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .review-header {
        text-align: center;
        margin-bottom: 30px;
        flex-shrink: 0;
    }
    
    .review-header-title {
        font-family: var(--serif);
        font-size: 28px;
        color: var(--text);
        margin-bottom: 4px;
    }
    
    .review-header-subtitle {
        font-family: var(--mono);
        font-size: 11px;
        color: var(--text2);
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .review-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        flex: 1;
        min-height: 0;
    }

    /* Left Side: Preview */
    .review-preview-col {
        display: flex;
        flex-direction: column;
        background: var(--bg1);
        border: 1px solid var(--line);
        border-radius: 12px;
        overflow: hidden;
        height: 100%;
    }

    .review-media {
        background: #000;
        flex: 1;
        min-height: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid var(--line);
        padding: 10px;
    }
    
    .review-media img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        display: block;
        border-radius: 6px;
    }

    .review-caption {
        padding: 20px;
        font-size: 13px;
        line-height: 1.5;
        color: var(--text);
        white-space: pre-wrap;
        max-height: 150px;
        overflow-y: auto;
    }

    /* Right Side: Form / Status */
    .review-action-col {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .review-form-card, .review-status-card {
        background: var(--bg2);
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 24px;
    }

    .review-status-card {
        text-align: center;
        padding: 40px 24px;
    }

    .btn-massive {
        padding: 14px 20px;
        font-size: 14px;
        font-weight: 600;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.2s;
        border: 1px solid transparent;
        width: 100%;
    }

    .btn-approve { background: var(--green); color: #fff; }
    .btn-approve:hover { opacity: 0.9; transform: translateY(-2px); }
    .btn-reject { background: transparent; border-color: var(--orange); color: var(--orange); }
    .btn-reject:hover { background: rgba(255, 150, 0, 0.1); }

    .history-card {
        background: var(--bg1);
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 20px;
        flex: 1;
        overflow-y: auto;
        min-height: 150px;
    }

    @media (max-width: 800px) {
        .review-grid { grid-template-columns: 1fr; }
        .review-wrapper { height: auto; display: block; }
        .review-preview-col { height: auto; max-height: none; margin-bottom: 30px; }
        .review-media { min-height: 300px; }
        .review-caption { max-height: none; }
        .history-card { min-height: auto; }
    }
</style>

<div class="review-wrapper">
    
    <div class="review-header">
        <h1 class="review-header-title">Revisione Contenuto</h1>
        <p class="review-header-subtitle"><?php echo e($post->title); ?></p>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div style="background: rgba(0, 200, 83, 0.1); border: 1px solid var(--green); color: var(--green); padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 600; font-size: 13px;">
            <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="review-grid">
        
        
        <div class="review-preview-col">
            <div class="review-media">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->currentVersion?->preview_url): ?>
                    <img src="<?php echo e($post->currentVersion->preview_url); ?>" alt="Anteprima Media">
                <?php else: ?>
                    <div style="color: var(--text3); font-family: var(--mono); font-size: 11px;">Nessun media allegato</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="review-caption">
                <?php echo e($post->currentVersion->caption ?? 'Nessun testo fornito per questo post.'); ?>

            </div>
        </div>

        
        <div class="review-action-col">
            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tokenObj->used_at): ?>
                <div class="review-status-card" style="padding: 20px;">
                    <div style="background: rgba(0, 200, 83, 0.1); border: 1px solid var(--green); color: var(--green); padding: 12px; border-radius: 8px; text-align: center; font-weight: 600; font-size: 13px;">
                        Risposta già inviata. Questa pagina resta visibile solo in consultazione.
                    </div>
                </div>
            <?php elseif($isExpired): ?>
                <div class="review-status-card" style="padding: 20px;">
                    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--red); color: var(--red); padding: 12px; border-radius: 8px; text-align: center; font-weight: 600; font-size: 13px;">
                        Questo link è scaduto. Contatta il team marketing.
                    </div>
                </div>
            <?php elseif($post->status->value === 'sent_to_client'): ?>
                <div class="review-form-card">
                    <h3 style="font-size: 16px; margin-bottom: 16px; font-weight: 600;">Lascia il tuo feedback</h3>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 11px; color: var(--text2); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em;">Il tuo nome *</label>
                        <input type="text" wire:model="clientName" style="width: 100%; background: var(--bg); border: 1px solid var(--line2); color: var(--text); padding: 10px 14px; border-radius: 6px; font-size: 14px;" placeholder="Mario Rossi" required>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 11px; color: var(--text2); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em;">Richieste di modifica (opzionale)</label>
                        <textarea wire:model="commentBody" style="width: 100%; background: var(--bg); border: 1px solid var(--line2); color: var(--text); padding: 10px 14px; border-radius: 6px; font-size: 14px; height: 80px; resize: none;" placeholder="Scrivi qui eventuali modifiche..."></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <button wire:click="requestChanges" class="btn-massive btn-reject">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            Richiedi Modifiche
                        </button>
                        
                        <button wire:click="approve" class="btn-massive btn-approve">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            Approva Post
                        </button>
                    </div>
                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($commentBody)): ?>
                        <div style="text-align: center; margin-top: 16px;">
                            <button wire:click="addComment" style="background: none; border: none; color: var(--text2); text-decoration: underline; cursor: pointer; font-size: 12px;">
                                Invia solo un commento senza cambiare lo stato
                            </button>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="review-status-card">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->status->value === 'client_approved'): ?>
                        <div style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background: rgba(0,200,83,0.1); color: var(--green); margin-bottom: 16px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        </div>
                        <h3 style="font-size: 20px; color: var(--text); margin-bottom: 8px;">Post Approvato</h3>
                        <p style="color: var(--text2); font-size: 13px;">Il post è stato approvato e verrà gestito dal team per la pubblicazione.</p>
                    <?php elseif($post->status->value === 'client_changes_requested'): ?>
                        <div style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background: rgba(255,150,0,0.1); color: var(--orange); margin-bottom: 16px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        </div>
                        <h3 style="font-size: 20px; color: var(--text); margin-bottom: 8px;">Modifiche Richieste</h3>
                        <p style="color: var(--text2); font-size: 13px;">Riceverai presto un nuovo link per la revisione.</p>
                    <?php else: ?>
                        <div style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background: var(--bg); color: var(--text3); margin-bottom: 16px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                        </div>
                        <h3 style="font-size: 20px; color: var(--text); margin-bottom: 8px;">In Lavorazione</h3>
                        <p style="color: var(--text2); font-size: 13px;">Non in attesa di revisione da parte del cliente.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($post->clientComments->count() > 0): ?>
                <div class="history-card">
                    <h3 style="font-size: 13px; margin-bottom: 16px; border-bottom: 1px solid var(--line); padding-bottom: 8px; color: var(--text2); text-transform: uppercase; letter-spacing: 0.05em;">Cronologia Comunicazioni</h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $post->clientComments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div style="padding: 12px; border-radius: 8px; background: var(--bg); border: 1px solid var(--line2);">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 11px;">
                                    <strong style="color: var(--text);"><?php echo e($comment->client_name ?? 'Agenzia'); ?></strong>
                                    <span style="color: var(--text3); font-family: var(--mono);"><?php echo e($comment->created_at->format('d/m/Y H:i')); ?></span>
                                </div>
                                
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($comment->type->value === 'change_request'): ?>
                                    <div style="display: inline-block; font-size: 9px; font-weight: bold; color: var(--orange); margin-bottom: 6px; text-transform: uppercase; background: rgba(255,150,0,0.1); padding: 3px 6px; border-radius: 3px; letter-spacing: 0.05em;">
                                        Richiesta di Modifica
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                
                                <div style="font-size: 13px; line-height: 1.5; color: var(--text2);">
                                    <?php echo e($comment->body); ?>

                                </div>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </div>
    </div>
</div>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/livewire/client/social/social-post-review.blade.php ENDPATH**/ ?>