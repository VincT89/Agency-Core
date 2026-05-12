<div>

<div class="u-mt-lg">
    <x-panel title="Commenti / Note operative" dot="var(--blue)" padded>
        
        {{-- Lista Commenti --}}
        <div class="comments-scroll-container" 
             x-data="{ count: {{ $ticket->comments->count() }} }"
             x-init="setTimeout(() => $el.scrollTop = $el.scrollHeight, 10); $watch('count', () => setTimeout(() => $el.scrollTop = $el.scrollHeight, 50))">
             
            @forelse($ticket->comments->sortBy('created_at') as $comment)
                <div class="ticket-comment-item">
                    <div class="u-flex-between u-mb-sm">
                        <span class="u-flex u-items-center u-gap-xs">
                            <strong class="u-text-strong">
                                @if($comment->source === \App\Enums\Social\CommentSource::Client)
                                    [Cliente]
                                @else
                                    {{ $comment->user?->name ?? 'Sistema' }}
                                @endif
                            </strong>
                            @if($comment->source === \App\Enums\Social\CommentSource::Client)
                                <span class="cmp-client-badge">Risposta cliente</span>
                            @endif
                        </span>
                        <div class="u-text-right">
                            <div class="u-text-meta">{{ $comment->created_at->format('d/m/Y H:i') }}</div>
                            @if($comment->delivery_channel === 'sody')
                                <div class="u-mt-xs">
                                    @if(in_array($comment->delivery_status, ['pending', 'processing']))
                                        <span class="badge badge-yellow">In invio a Sody</span>
                                    @elseif($comment->delivery_status === 'sent')
                                        <span class="badge badge-green">Inviato al cliente</span>
                                    @elseif($comment->delivery_status === 'failed')
                                        <span class="badge badge-red" title="{{ $comment->delivery_error }}">Invio fallito</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="ticket-comment-body">{{ $comment->body }}</div>
                </div>
            @empty
                <div class="u-empty-state-sm comments-empty">Nessun commento ancora presente.</div>
            @endforelse
        </div>

        {{-- Form --}}
        @can('update', $ticket)
            <div class="comments-composer">
                <form wire:submit="addComment">
                    <textarea wire:model="body"
                              class="form-ta @error('body') is-invalid @enderror"
                              rows="3"
                              placeholder="Aggiungi un aggiornamento, una nota o un'azione intrapresa..."
                              required></textarea>
                    @error('body')
                        <div class="u-text-red u-mt-sm">{{ $message }}</div>
                    @enderror
                    
                    <div class="u-mt-sm u-flex u-items-center u-gap-md u-flex-between">
                        <div class="u-flex u-items-center u-gap-md">
                            <label class="u-flex u-items-center u-gap-xs">
                                <input type="radio" wire:model="delivery_mode" value="internal">
                                <span class="u-text-meta">Nota interna</span>
                            </label>
                            <label class="u-flex u-items-center u-gap-xs">
                                <input type="radio" wire:model="delivery_mode" value="send_to_client_via_sody">
                                <span class="u-text-meta">Invia al cliente con Sody</span>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-p" wire:loading.attr="disabled" wire:target="addComment">
                            <span wire:loading.remove wire:target="addComment">Aggiungi commento</span>
                            <span wire:loading wire:target="addComment">Invio in corso...</span>
                        </button>
                    </div>
                </form>
            </div>
        @endcan
    </x-panel>
</div>
</div>
