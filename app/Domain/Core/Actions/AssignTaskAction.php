<?php

namespace App\Domain\Core\Actions;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

class AssignTaskAction
{
    public function execute(Task $task, int $assigneeId): Task
    {
        return DB::transaction(function () use ($task, $assigneeId) {
            $task->update(['assigned_to' => $assigneeId]);

            event(new \App\Domain\Core\Events\TaskAssigned($task));

            return $task;
        });
    }
}
