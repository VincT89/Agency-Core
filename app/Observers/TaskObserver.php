<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\AuditLogService;
use App\Notifications\TaskAssignedNotification;

class TaskObserver
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function created(Task $task): void
    {
        $this->auditLog->log('created', $task, null, $task->getAttributes());
    }

    public function updated(Task $task): void
    {
        $old = array_intersect_key($task->getOriginal(), $task->getDirty());
        $new = $task->getDirty();

        $action = isset($new['status']) ? 'status_changed' : 'updated';

        $this->auditLog->log($action, $task, $old, $new);
    }

    public function deleted(Task $task): void
    {
        $this->auditLog->log('deleted', $task, $task->getOriginal(), null);
    }
}
