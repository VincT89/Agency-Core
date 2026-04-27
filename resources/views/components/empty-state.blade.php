@props(['message' => 'Nessun elemento trovato.', 'icon' => 'inbox'])

<div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:32px 16px; text-align:center; color:var(--text3); border-radius:var(--r); background:var(--bg2); border:1px dashed var(--line);">
    <div style="margin-bottom:12px; opacity:0.6;">
        <i data-lucide="{{ $icon }}" style="width:32px; height:32px;"></i>
    </div>
    <div style="font-size:14px; font-weight:500;">
        {{ $message }}
    </div>
</div>
