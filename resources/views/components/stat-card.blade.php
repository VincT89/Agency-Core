@props(['label' => '', 'value' => '', 'sub' => '', 'highlight' => false, 'subAlert' => false, 'color' => null])
<div class="stat-card {{ $highlight ? 'hi' : '' }}">
    <div class="stat-lbl">{{ $label }}</div>
    <div class="stat-val" style="{{ $color ? 'color: ' . $color . ';' : '' }}">{{ $value }}</div>
    @if($sub)
        <div class="stat-sub {{ $subAlert ? 'al' : '' }}" style="{{ $color ? 'color: ' . $color . ';' : '' }}">{{ $sub }}</div>
    @endif
</div>