<span class="badge <?php echo e(match($status->value) {
    'waiting_photographer' => 'badge-purple',
    'waiting_client' => 'badge-yellow',
    'client_rejected' => 'badge-red',
    'photographer_rejected' => 'badge-red',
    'client_confirmed' => 'badge-green',
    'scheduled' => 'badge-green',
    'cancelled' => 'badge-gray',
    default => 'badge-gray',
}); ?>">
    <?php echo e($status->labelForContext($context)); ?>

</span>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views\components\shooting\status-badge.blade.php ENDPATH**/ ?>