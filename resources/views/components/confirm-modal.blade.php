@props([
    'title' => 'Conferma', 
    'message' => 'Sei sicuro di voler procedere?',
    'confirmText' => 'Conferma',
    'confirmMethod' => null,
    'confirmClass' => 'btn btn-p',
    'icon' => 'alert-circle',
    'variant' => 'warning',
    'disabled' => false
])

<div x-data="{ open: false }" class="confirm-modal-trigger">
    <div @click="if(!{{ $disabled ? 'true' : 'false' }}) open = true">
        {{ $slot }}
    </div>

    <template x-teleport="body" wire:ignore>
        <div x-show="open" x-cloak class="confirm-modal-overlay" @click.self="open = false" @keydown.escape.window="open = false">
        <div class="confirm-modal-box" @click.stop>
            <div class="confirm-modal-header">
                <div class="confirm-modal-icon {{ $variant }}">
                    <i data-lucide="{{ $icon }}" class="confirm-modal-icon-svg"></i>
                </div>
                <h3 class="confirm-modal-title">
                    {{ $title }}
                </h3>
            </div>
            
            <p class="confirm-modal-message">
                {{ $message }}
            </p>
            
            <div class="confirm-modal-footer">
                <button type="button" @click="open = false" class="btn btn-g confirm-modal-btn">Annulla</button>
                <button type="button" class="{{ $confirmClass }} confirm-modal-btn" 
                        @if($confirmMethod) wire:click="{{ $confirmMethod }}" @endif
                        @click="open = false">
                    {{ $confirmText }}
                </button>
            </div>
        </div>
        </div>
    </template>
</div>
