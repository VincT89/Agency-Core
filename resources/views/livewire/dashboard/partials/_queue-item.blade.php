<div class="u-flex-between hover-bg u-p-lg u-border-b">
    <div class="u-flex-col u-gap-xs">
        <div class="u-flex u-items-center u-gap-xs">
            <a href="{{ $item->action_url }}" class="u-font-sans u-text-md u-text-strong u-text-body u-no-underline">{{ $item->shoot_name }}</a>
            @php
                $highlightClass = $highlight === 'red' ? 'u-badge-red' : ($highlight === 'blue' ? 'u-badge-blue' : 'u-badge-neutral');
            @endphp
            <span class="u-badge-micro {{ $highlightClass }}">{{ $item->status_label }}</span>
        </div>
        <div class="u-text-sm u-text-muted">
            {{ $item->project_name }} <span class="u-mx-xs u-opacity-50">&bull;</span> {{ $item->shoot_code }}
        </div>
    </div>
    <div>
        <a href="{{ $item->action_url }}" class="btn btn-sm btn-outline-secondary">{{ $item->action_label }}</a>
    </div>
</div>
