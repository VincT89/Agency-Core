@props([
    'action' => null,
    'wireClick' => null, 
    'title' => 'Conferma Eliminazione', 
    'message' => 'Sei sicuro di voler eliminare questo elemento? Questa azione non può essere annullata.',
    'confirmText' => null,
])

<div x-data="{ open: false, confirm: '' }" class="confirm-modal-trigger">
    <div @click.stop="open = true; confirm = ''">
        {{ $slot }}
    </div>

    <div x-show="open" x-cloak class="confirm-modal-overlay" @click.self="open = false" @keydown.escape.window="open = false">
        <div class="confirm-modal-box" @click.stop>
            <div class="confirm-modal-header">
                <div class="confirm-modal-icon danger">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                </div>
                <h3 class="confirm-modal-title">
                    {{ $title }}
                </h3>
            </div>
            
            <p class="confirm-modal-message">
                {{ $message }}
            </p>

            @if($confirmText)
            <div class="u-mb-lg">
                <label class="u-text-label u-w-full delete-modal-confirm-label">
                    Digita <strong class="u-text-strong delete-modal-confirm-token">{{ $confirmText }}</strong> per confermare
                </label>
                <input type="text" x-model="confirm" class="form-in u-w-full delete-modal-confirm-input" placeholder="{{ $confirmText }}">
            </div>
            @endif
            
            <div class="confirm-modal-footer">
                <button type="button" @click="open = false" class="btn btn-g confirm-modal-btn">Annulla</button>
                @if($wireClick)
                    <button type="button" wire:click="{{ $wireClick }}" class="btn btn-p btn-danger confirm-modal-btn" 
                            @click="open = false">
                        Sì, elimina
                    </button>
                @else
                <form action="{{ $action }}" method="POST" class="u-m-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-p btn-danger confirm-modal-btn" 
                            @if($confirmText) :disabled="confirm !== '{{ addslashes($confirmText) }}'" :class="{ 'is-disabled': confirm !== '{{ addslashes($confirmText) }}' }" @endif>
                        Sì, elimina
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
