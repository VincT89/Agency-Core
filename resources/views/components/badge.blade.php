@props(['status' => '', 'type' => null, 'label' => null])

@php
// Mappa status → classe badge
$map = [
    // generici
    'active'          => 'bg',
    'inactive'        => 'bd',
    // ticket / task status
    'open'            => 'ba',
    'in_progress'     => 'bb',
    'waiting'         => 'ba',
    'resolved'        => 'bg',
    'closed'          => 'bd',
    'todo'            => 'bd',
    'done'            => 'bg',
    // ticket priority
    'low'             => 'bd',
    'medium'          => 'bd',
    'high'            => 'ba',
    'urgent'          => 'br',
    // invoice status
    'draft'           => 'bd',
    'issued'          => 'bb',
    'partially_paid'  => 'ba',
    'paid'            => 'bg',
    'overdue'         => 'br',
    'cancelled'       => 'bd',
    // ticket type
    'bug'             => 'br',
    'support'         => 'bd',
    'request'         => 'bb',
    'change'          => 'ba',
    'admin'           => 'bk',
    // calendar event type
    'internal_meeting'=> 'bb',
    'client_meeting'  => 'bk',
    'deadline'        => 'br',
    'review'          => 'ba',
    'delivery'        => 'bg',
    'other'           => 'bd',
    // payment method
    'bank_transfer'   => 'bb',
    'cash'            => 'bg',
    'card'            => 'bb',
    // marketing statuses
    'draft'           => 'bd',
    'submitted_to_n8n'=> 'bb',
    'posts_received'  => 'bb',
    'internal_review' => 'ba',
    'sent_to_client'  => 'ba',
    'client_changes_requested' => 'br',
    'client_approved' => 'bg',
    'ready_to_publish'=> 'bb',
    // project status
    'completed'       => 'bg',
    'on_hold'         => 'ba',
    'cancelled'       => 'br',
    // team roles
    'member'          => 'bg',
    'lead'            => 'ba',
    'support'         => 'bb',
];
$class = $map[$status] ?? 'bd';
@endphp

<span class="badge {{ $class }}">{{ $label ?? ($slot->isEmpty() ? $status : $slot) }}</span>