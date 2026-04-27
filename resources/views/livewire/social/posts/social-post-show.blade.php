<div>
    <x-page-header
        eyebrow="Dettaglio Post"
        :meta="'Ultima modifica: ' . $post->updated_at->diffForHumans()"
    >
        <x-slot:title><strong>{{ $post->title }}</strong></x-slot:title>
        <x-slot:actions>
            <a href="{{ route('social.posts.index') }}" class="btn btn-g">Torna all'Archivio</a>
        </x-slot:actions>
    </x-page-header>

    <div class="g-2col-main">
        {{-- PANNELLO SINISTRO: Media e Dettagli --}}
        <div>
            <x-panel title="Media Versione Attuale (v{{ $post->currentVersion->version_number ?? 1 }})" dot="var(--purple)" class="social-left-panel">
                <div class="social-media-preview-container">
                    @if($post->currentVersion && $post->currentVersion->image_path)
                        <img src="{{ Storage::url($post->currentVersion->image_path) }}" alt="Preview" class="social-media-preview-img">
                    @else
                        <div class="social-empty-preview">Nessuna immagine disponibile per questa versione.</div>
                    @endif
                </div>
                
                <div class="social-detail-section social-caption-wrapper">
                    <div class="social-section-title">Caption (Testo del Post)</div>
                    <div class="social-caption-box">{{ $post->currentVersion->caption ?? 'Nessuna caption fornita.' }}</div>
                </div>
                
                @if($post->currentVersion && $post->currentVersion->prompt_used)
                <div class="social-detail-section">
                    <div class="social-section-title">Prompt di Rigenerazione Usato</div>
                    <div class="social-prompt-box">
                        > {{ $post->currentVersion->prompt_used }}
                    </div>
                </div>
                @endif
            </x-panel>
        </div>

        {{-- PANNELLO DESTRO: Storico, Azioni, Commenti --}}
        <div style="display: flex; flex-direction: column; gap: 20px;">
            
            <x-panel title="Gestione e Stato" dot="var(--accent)">
                <x-slot:headerActions>
                    <span class="badge" style="background: {{ $post->status->color() }}; color: #fff; border: none; font-size: 11px;">{{ $post->status->label() }}</span>
                </x-slot:headerActions>
                <div class="social-panel-section">
                    
                    {{-- Azioni Contestuali --}}
                    @if($post->status->value === 'regenerating')
                        <div class="social-regenerating-banner">
                            <i data-lucide="loader" class="spin mb" width="24"></i>
                            <div style="font-weight: 600; font-size: 13px;">Rigenerazione in corso da parte di n8n...</div>
                            <div style="font-size: 11px;">Attendi che il sistema riceva la nuova versione.</div>
                        </div>
                    @endif

                    @can('requestRegeneration', $post)
                        @if(in_array($post->status->value, ['internal_review', 'received', 'client_changes_requested', 'changes_requested']))
                            <button wire:click="$set('showRegenerateModal', true)" class="btn btn-g social-btn-regenerate">
                                <i data-lucide="refresh-cw" width="16"></i> Richiedi Rigenerazione a n8n
                            </button>
                        @endif
                    @endcan

                    @can('update', $post)
                        @if(in_array($post->status->value, ['internal_review', 'received', 'client_changes_requested', 'changes_requested']))
                            <button wire:click="markAsReady" class="btn btn-p social-btn-ready">
                                <i data-lucide="check-circle" width="16"></i> Segna Pronto per il Cliente
                            </button>
                        @endif
                    @endcan

                    @can('sendToClient', $post)
                        @if(in_array($post->status->value, ['ready_for_client']))
                            <button wire:click="sendToClient" class="btn btn-p social-btn-client">
                                <i data-lucide="send" width="16"></i> Genera Link e Invia al Cliente
                            </button>
                        @endif
                        
                        @if(in_array($post->status->value, ['sent_to_client', 'client_approved', 'client_changes_requested']))
                            <button wire:click="sendToClient" class="btn btn-g social-btn-client-active">
                                <i data-lucide="link" width="16"></i> Visualizza Link Cliente Attivo
                            </button>
                        @endif
                    @endcan

                    @can('schedule', $post)
                        @if($post->isPlannable())
                            <button wire:click="$set('showScheduleModal', true)" class="btn btn-p" style="background: var(--accent); border-color: var(--accent); width: 100%; margin-top: 8px;">
                                <i data-lucide="calendar" width="16"></i> Pianifica Pubblicazione
                            </button>
                        @endif
                        
                        @if($post->activeEditorialSlot)
                            <div class="social-panel-section" style="background: var(--bg2); padding: 12px; border-radius: 8px; margin-top: 12px;">
                                <div style="font-size: 12px; font-weight: 600; color: var(--text1); margin-bottom: 4px;">Slot Attivo</div>
                                <div style="font-size: 11px; color: var(--text2);">
                                    <i data-lucide="calendar-check" width="12" style="display: inline-block; vertical-align: middle; margin-right: 4px;"></i>
                                    {{ $post->activeEditorialSlot->scheduled_at->format('d/m/Y H:i') }} su {{ ucfirst($post->activeEditorialSlot->platform->value) }}
                                    <br>
                                    <span style="color: {{ $post->activeEditorialSlot->status->color() }}; font-weight: 600;">{{ $post->activeEditorialSlot->status->label() }}</span>
                                </div>
                                
                                @if($post->activeEditorialSlot->status->value === 'scheduled')
                                    <div style="display: flex; gap: 8px; margin-top: 12px;">
                                        @can('publish', $post->activeEditorialSlot)
                                        <button wire:click="publishSlot" class="btn btn-p" style="flex: 1; font-size: 10px; padding: 4px; background: var(--green); border-color: var(--green);">
                                            Segna Pubblicato
                                        </button>
                                        @endcan
                                        
                                        @can('cancel', $post->activeEditorialSlot)
                                        <button wire:click="cancelSlot" class="btn btn-g" style="flex: 1; font-size: 10px; padding: 4px; color: var(--red);" onclick="return confirm('Sicuro di voler annullare questa pianificazione?') || event.stopImmediatePropagation()">
                                            Annulla Slot
                                        </button>
                                        @endcan
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endcan
                </div>
            </x-panel>

            <x-panel title="Discussione Interna & Cliente" dot="var(--blue)">
                <div class="social-comments-container" style="padding: 16px;">
                    @forelse($post->comments as $comment)
                        <div class="social-comment-card {{ $comment->visibility->value === 'client' ? 'client' : 'internal' }}">
                            <div class="social-comment-header">
                                <strong>{{ $comment->user->name ?? $comment->client_name }}</strong>
                                <span>{{ $comment->created_at->format('d/m/Y H:i') }} (v{{ $comment->version->version_number ?? '?' }})</span>
                            </div>
                            
                            @if($comment->type->value === 'change_request')
                                <div class="social-comment-change-req">
                                    RICHIESTA MODIFICA
                                </div>
                            @endif
                            
                            <div class="social-comment-body">{{ $comment->body }}</div>
                        </div>
                    @empty
                        <div class="social-empty-state">Nessun commento finora.</div>
                    @endforelse
                </div>
                <div class="social-panel-section bordered">
                    <form wire:submit="addInternalComment">
                        <textarea wire:model="newCommentBody" class="form-in" style="width: 100%; min-height: 80px; margin-bottom: 12px; resize: vertical;" placeholder="Scrivi un commento interno..." required></textarea>
                        <div style="text-align: right;">
                            <button type="submit" class="btn btn-p">Aggiungi Commento</button>
                        </div>
                    </form>
                </div>
            </x-panel>
            
        </div>
    </div>

    {{-- MODAL RIGENERA --}}
    @if($showRegenerateModal)
    <div class="overlay open">
        <div class="modal" @click.stop>
            <div class="modal-hd">
                <div class="modal-title">Richiedi Rigenerazione</div>
                <button class="modal-close" wire:click="$set('showRegenerateModal', false)"><i data-lucide="x" width="14"></i></button>
            </div>
            <div style="font-family: var(--mono); font-size: 11px; color: var(--text2); margin-bottom: 20px;">
                Indica nel prompt le modifiche che desideri apportare. N8n elaborerà la richiesta e creerà una nuova versione di questo post (v{{ ($post->currentVersion->version_number ?? 1) + 1 }}).
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
    @endif
    
    {{-- MODAL LINK CLIENTE --}}
    @if($showSendClientModal)
    <div class="overlay open">
        <div class="modal" @click.stop>
            <div class="modal-hd">
                <div class="modal-title">Link Pubblico Cliente</div>
                <button class="modal-close" wire:click="$set('showSendClientModal', false)"><i data-lucide="x" width="14"></i></button>
            </div>
            <div style="font-family: var(--mono); font-size: 11px; color: var(--text2); margin-bottom: 20px;">
                Copia questo link e invialo al cliente per la revisione e approvazione.
            </div>
            <div class="form-g">
                <input type="text" class="form-in" value="{{ $clientLink }}" readonly onclick="this.select(); document.execCommand('copy'); window.dispatchEvent(new CustomEvent('notify', { detail: 'Link copiato!' }));">
            </div>
            <div class="modal-ft">
                <button type="button" class="btn btn-p" wire:click="$set('showSendClientModal', false)">Chiudi</button>
            </div>
        </div>
    </div>
    @endif
    {{-- MODAL PIANIFICAZIONE --}}
    @if($showScheduleModal)
    <div class="overlay open">
        <div class="modal" @click.stop>
            <div class="modal-hd">
                <div class="modal-title">Pianifica nel Calendario Editoriale</div>
                <button class="modal-close" wire:click="$set('showScheduleModal', false)"><i data-lucide="x" width="14"></i></button>
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
                        @foreach(\App\Enums\Social\SocialPlatform::cases() as $platform)
                            <option value="{{ $platform->value }}">{{ $platform->label() }}</option>
                        @endforeach
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
    @endif
</div>
