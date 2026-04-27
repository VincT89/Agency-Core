@props(['percent' => 0])
<div class="wl-track">
    <div class="wl-fill {{ $percent >= 85 ? 'warn' : '' }}" style="width:{{ $percent }}%"></div>
</div>