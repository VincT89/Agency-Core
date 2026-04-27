<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['status' => '', 'type' => null, 'label' => null]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['status' => '', 'type' => null, 'label' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
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
?>

<span class="badge <?php echo e($class); ?>"><?php echo e($label ?? ($slot->isEmpty() ? $status : $slot)); ?></span><?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/components/badge.blade.php ENDPATH**/ ?>