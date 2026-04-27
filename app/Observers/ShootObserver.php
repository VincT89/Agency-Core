<?php

namespace App\Observers;

use App\Models\Shooting\Shoot;
use App\Services\AuditLogService;

class ShootObserver
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function created(Shoot $shoot): void
    {
        $this->auditLog->log('created', $shoot, null, $shoot->getAttributes());
    }

    public function updated(Shoot $shoot): void
    {
        $old = array_intersect_key($shoot->getOriginal(), $shoot->getDirty());
        $new = $shoot->getDirty();

        if (array_key_exists('selected_slot_id', $new)) {
            $slot = \App\Models\Shooting\ShootSlot::find($new['selected_slot_id']);
            if ($slot) {
                $new['selected_slot_date'] = $slot->date->format('Y-m-d');
                $new['selected_slot_period'] = $slot->period->value;
            }
        }

        $action = isset($new['status']) ? 'status_changed' : 'updated';

        $this->auditLog->log($action, $shoot, $old, $new);
    }

    public function deleted(Shoot $shoot): void
    {
        $this->auditLog->log('deleted', $shoot, $shoot->getOriginal(), null);
    }
}
