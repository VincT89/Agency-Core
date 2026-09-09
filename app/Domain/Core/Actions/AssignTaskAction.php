<?php

namespace App\Domain\Core\Actions;

use App\Models\Task;

class AssignTaskAction
{
    public function execute(Task $task, int $assigneeId): Task
    {
        return app(UpdateTaskAction::class)->execute($task, ['assigned_to' => $assigneeId]);
    }
}
