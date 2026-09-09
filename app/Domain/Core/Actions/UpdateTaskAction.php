<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Events\TaskAssigned;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class UpdateTaskAction
{
    public function execute(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $task->fill($data);
            $assignmentChanged = $task->isDirty('assigned_to');
            $task->save();

            if ($assignmentChanged && $task->assigned_to) {
                event(new TaskAssigned($task));
            }

            return $task;
        });
    }
}
