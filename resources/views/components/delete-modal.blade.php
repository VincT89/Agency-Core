@props([
    'action' => null,
    'wireClick' => null, 
    'title' => 'Conferma Eliminazione', 
    'message' => 'Sei sicuro di voler eliminare questo elemento? Questa azione non può essere annullata.',
    'confirmText' => null,
])

<div x-data="{ open: false, confirm: '' }" style="display: inline-block;">
    <div @click.stop="open = true; confirm = ''">
        {{ $slot }}
    </div>

    <div x-show="open" x-cloak style="display: none; align-items: center; justify-content: center; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000;" @click.self="open = false" @keydown.escape.window="open = false">
        <div style="background: var(--bg2); border: 1px solid var(--line2); border-radius: var(--r); width: 100%; max-width: 400px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);" @click.stop>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <div style="background: rgba(245, 75, 75, 0.1); color: var(--red); padding: 8px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                </div>
                <h3 style="font-family: var(--sans); font-size: 16px; font-weight: 600; color: var(--text); margin: 0;">
                    {{ $title }}
                </h3>
            </div>
            
            <p style="color: var(--text2); font-size: 13.5px; margin-bottom: 24px; line-height: 1.5;">
                {{ $message }}
            </p>

            @if($confirmText)
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 11px; color: var(--text3); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">
                    Digita <strong style="color:var(--text);user-select:all">{{ $confirmText }}</strong> per confermare
                </label>
                <input type="text" x-model="confirm" class="form-in" placeholder="{{ $confirmText }}" style="width: 100%; padding: 8px 12px; font-family: var(--sans);">
            </div>
            @endif
            
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" @click="open = false" class="btn btn-g" style="padding: 8px 16px;">Annulla</button>
                @if($wireClick)
                    <button type="button" wire:click="{{ $wireClick }}" class="btn" 
                            style="background: var(--red); border-color: var(--red); color: white; padding: 8px 16px; transition: opacity 0.2s;"
                            @click="open = false">
                        Sì, elimina
                    </button>
                @else
                <form action="{{ $action }}" method="POST" style="margin: 0;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn" 
                            style="background: var(--red); border-color: var(--red); color: white; padding: 8px 16px; transition: opacity 0.2s;"
                            @if($confirmText) :disabled="confirm !== '{{ addslashes($confirmText) }}'" :style="confirm !== '{{ addslashes($confirmText) }}' ? 'opacity: 0.5; cursor: not-allowed;' : 'opacity: 1;'" @endif>
                        Sì, elimina
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
