<?php

namespace App\Domain\Core\Listeners;

use App\Domain\Core\Events\TaskAssigned;

class SendTaskAssignedNotification
{
    public function handle(TaskAssigned $event)
    {
        $task = $event->task;

        // Invia notifica o effettua altre procedure previste prima in Observer
        if ($task->assigned_to && $task->assigned_to !== $task->created_by && $task->assignee) {
             $task->assignee->notify(new \App\Notifications\TaskAssignedNotification($task));
        }
    }
}
