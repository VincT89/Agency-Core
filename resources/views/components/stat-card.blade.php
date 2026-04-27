@props(['label' => '', 'value' => '', 'sub' => '', 'highlight' => false, 'subAlert' => false])
<div class="stat-card {{ $highlight ? 'hi' : '' }}">
    <div class="stat-lbl">{{ $label }}</div>
    <div class="stat-val">{{ $value }}</div>
    @if($sub)
        <div class="stat-sub {{ $subAlert ? 'al' : '' }}">{{ $sub }}</div>
    @endif
</div>