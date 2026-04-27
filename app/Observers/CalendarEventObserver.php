<?php

namespace App\Observers;

use App\Models\CalendarEvent;
use App\Services\AuditLogService;

class CalendarEventObserver
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function created(CalendarEvent $calendarEvent): void
    {
        $this->auditLog->log('created', $calendarEvent, null, $calendarEvent->getAttributes());
    }

    public function updated(CalendarEvent $calendarEvent): void
    {
        $old = array_intersect_key($calendarEvent->getOriginal(), $calendarEvent->getDirty());
        $new = $calendarEvent->getDirty();

        $this->auditLog->log('updated', $calendarEvent, $old, $new);
    }

    public function deleted(CalendarEvent $calendarEvent): void
    {
        $this->auditLog->log('deleted', $calendarEvent, $calendarEvent->getOriginal(), null);
    }
}
