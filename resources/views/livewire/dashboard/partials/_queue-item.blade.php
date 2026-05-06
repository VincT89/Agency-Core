<div class="u-flex-between hover-bg" style="padding:16px 20px; border-bottom:1px solid var(--line);">
    <div style="display:flex; flex-direction:column; gap:4px;">
        <div style="display:flex; align-items:center; gap:8px;">
            <a href="{{ $item->action_url }}" style="font-family:var(--sans); font-size:14px; font-weight:600; color:var(--text); text-decoration:none;">{{ $item->shoot_name }}</a>
            @if($highlight === 'red')
                <span style="font-size:10px; font-weight:600; padding:2px 6px; border-radius:4px; background:rgba(245,75,75,0.1); color:var(--red);">{{ $item->status_label }}</span>
            @elseif($highlight === 'blue')
                <span style="font-size:10px; font-weight:600; padding:2px 6px; border-radius:4px; background:rgba(66,133,244,0.1); color:var(--blue);">{{ $item->status_label }}</span>
            @else
                <span style="font-size:10px; font-weight:600; padding:2px 6px; border-radius:4px; background:var(--bg3); color:var(--text2);">{{ $item->status_label }}</span>
            @endif
        </div>
        <div style="font-size:12px; color:var(--text3);">
            {{ $item->project_name }} <span style="margin:0 4px; opacity:0.5;">&bull;</span> {{ $item->shoot_code }}
        </div>
    </div>
    <div>
        <a href="{{ $item->action_url }}" class="btn btn-sm" style="background:var(--bg); border:1px solid var(--line); color:var(--text2); text-decoration:none;">{{ $item->action_label }}</a>
    </div>
</div>
