@props([
    'title' => null,
    'message' => 'Nessun elemento trovato.', 
    'icon' => 'inbox',
    'actionLabel' => null,
    'actionHref' => null
])

<div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:32px 16px; text-align:center; color:var(--text3); border-radius:var(--r); background:var(--bg2); border:1px dashed var(--line);">
    <div style="margin-bottom:12px; opacity:0.6;">
        <i data-lucide="{{ $icon }}" @if($icon === 'loader') class="spin" @endif style="width:32px; height:32px;"></i>
    </div>
    
    @if($title)
        <div style="font-size:16px; font-weight:600; color:var(--text1); margin-bottom:4px;">
            {{ $title }}
        </div>
    @endif
    
    <div style="font-size:14px; font-weight:400; max-width:400px; margin:0 auto;">
        {{ $message }}
    </div>

    @if($actionLabel && $actionHref)
        <div style="margin-top:16px;">
            <a href="{{ $actionHref }}" class="btn btn-p" style="padding:8px 16px; font-size:12px; display:inline-flex; align-items:center; justify-content:center;">
                {{ $actionLabel }}
            </a>
        </div>
    @endif
</div>
