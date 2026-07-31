@props(['log'])

@can('system.admin')
<div class="audit-item">
    <span class="audit-time">{{ $log->created_at->format('H:i:s') }}</span>
    <div
        class="audit-icon bg-{{ str_replace('_', '-', $log->action) }}"
        aria-hidden="true"
    ></div>
    <div class="audit-body">
        <b>{{ $log->user?->name ?? 'Sistema' }}</b>
        {{ $log->display_action_text }}
        <div class="audit-foot">
            {{ $log->display_action_label }} · {{ $log->created_at->locale('it')->diffForHumans() }}
        </div>
    </div>
</div>
@endcan
