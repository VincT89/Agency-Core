@props(['id' => '', 'title' => ''])
<div class="overlay" id="{{ $id }}">
    <div class="modal">
        <div class="modal-hd">
            <div class="modal-title">{{ $title }}</div>
            <button class="modal-close" onclick="cm('{{ $id }}')">✕</button>
        </div>
        {{ $slot }}
        @if(isset($footer))
            <div class="modal-ft">{{ $footer }}</div>
        @endif
    </div>
</div>