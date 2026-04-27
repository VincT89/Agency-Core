<span class="badge {{ match($status->value) {
    'waiting_photographer' => 'badge-purple',
    'waiting_client' => 'badge-yellow',
    'client_rejected' => 'badge-red',
    'photographer_rejected' => 'badge-red',
    'client_confirmed' => 'badge-green',
    'scheduled' => 'badge-green',
    'cancelled' => 'badge-gray',
    default => 'badge-gray',
} }}">
    {{ $status->labelForContext($context) }}
</span>
