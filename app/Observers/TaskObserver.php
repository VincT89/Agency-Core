<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\AuditLogService;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCreatedAdminNotification;
use App\Models\User;
use App\Enums\UserRole;

class TaskObserver
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function created(Task $task): void
    {
        $this->auditLog->log('created', $task, null, $task->getAttributes());

        // 1. Notifica all'assegnatario (se presente e non è colui che ha creato la task)
        if ($task->assigned_to && $task->assigned_to !== $task->created_by) {
            $task->assignee?->notify(new TaskAssignedNotification($task));
        }

        // 2. Notifica agli Admin (escluso chi l'ha creata e l'eventuale assegnatario se admin)
        $admins = User::where('role', UserRole::Admin)->get();
        foreach ($admins as $admin) {
            if ($admin->id !== $task->created_by && $admin->id !== $task->assigned_to) {
                $admin->notify(new TaskCreatedAdminNotification($task));
            }
        }
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
