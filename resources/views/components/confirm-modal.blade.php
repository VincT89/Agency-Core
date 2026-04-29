@props([
    'title' => 'Conferma', 
    'message' => 'Sei sicuro di voler procedere?',
    'confirmText' => 'Conferma',
    'confirmMethod' => null,
    'btnClass' => 'btn btn-p',
    'btnStyle' => '',
    'icon' => 'alert-circle',
    'iconColor' => 'var(--orange)',
    'iconBg' => 'rgba(255, 150, 0, 0.1)',
    'disabled' => false
])

<div x-data="{ open: false }" style="display: inline-block;">
    <div @click="if(!{{ $disabled ? 'true' : 'false' }}) open = true">
        {{ $slot }}
    </div>

    <div x-show="open" x-cloak style="display: none; align-items: center; justify-content: center; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000;" @click.self="open = false" @keydown.escape.window="open = false">
        <div style="background: var(--bg2); border: 1px solid var(--line2); border-radius: var(--r); width: 100%; max-width: 400px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);" @click.stop>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <div style="background: {{ $iconBg }}; color: {{ $iconColor }}; padding: 8px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="{{ $icon }}" style="width: 20px; height: 20px;"></i>
                </div>
                <h3 style="font-family: var(--sans); font-size: 16px; font-weight: 600; color: var(--text); margin: 0;">
                    {{ $title }}
                </h3>
            </div>
            
            <p style="color: var(--text2); font-size: 13.5px; margin-bottom: 24px; line-height: 1.5;">
                {{ $message }}
            </p>
            
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" @click="open = false" class="btn btn-g" style="padding: 8px 16px;">Annulla</button>
                <button type="button" class="{{ $btnClass }}" 
                        style="padding: 8px 16px; {{ $btnStyle }}"
                        @if($confirmMethod) wire:click="{{ $confirmMethod }}" @endif
                        @click="open = false">
                    {{ $confirmText }}
                </button>
            </div>
        </div>
    </div>
</div>
