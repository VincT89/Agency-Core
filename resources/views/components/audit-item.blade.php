@props(['log'])
@php
$icons = [
    'created'              => ['icon' => '+', 'color' => 'rgba(232,91,42,.15)'],
    'updated'              => ['icon' => '✎', 'color' => 'rgba(91,142,245,.15)'],
    'deleted'              => ['icon' => '✕', 'color' => 'rgba(245,75,75,.15)'],
    'status_changed'       => ['icon' => '↻', 'color' => 'rgba(245,200,66,.15)'],
    'payment_registered'   => ['icon' => '✓', 'color' => 'rgba(62,207,142,.15)'],
    'uploaded_attachment'  => ['icon' => '↑', 'color' => 'rgba(91,142,245,.15)'],
    'deleted_attachment'   => ['icon' => '✕', 'color' => 'rgba(245,75,75,.15)'],
];
$cfg = $icons[$log->action] ?? ['icon' => '·', 'color' => 'var(--bg3)'];
@endphp
<div class="audit-item">
    <span class="audit-time">{{ $log->created_at->format('H:i:s') }}</span>
    <div class="audit-icon bg-{{ str_replace('_', '-', $log->action) }}">{{ $cfg['icon'] }}</div>
    <div class="audit-body">
        <b>{{ $log->user?->name ?? 'Sistema' }}</b>
        — azione: <span class="ent">{{ $log->action }}</span>
        su {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
        @if($log->description)
            — {{ $log->description }}
        @endif
        <div class="audit-foot">{{ $log->created_at->diffForHumans() }}</div>
    </div>
</div>