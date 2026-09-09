<?php

namespace App\Domain\Core\Listeners;

use App\Domain\Core\Events\TaskAssigned;

class SendTaskAssignedNotification
{
    public function handle(TaskAssigned $event)
    {
        $task = $event->task;

        // Rilegge il destinatario dopo una riassegnazione, anche se la relazione era già caricata.
        $task->load('assignee');

        if ($task->assigned_to && (int) $task->assigned_to !== (int) $event->actorId && $task->assignee) {
            $task->assignee->notify(new \App\Notifications\TaskAssignedNotification($task));
        }
    }
}
