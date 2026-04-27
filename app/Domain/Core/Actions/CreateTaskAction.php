<?php

namespace App\Domain\Core\Actions;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

class CreateTaskAction
{
    public function execute(array $data): Task
    {
        return DB::transaction(function () use ($data) {
            $data['created_by'] = auth()->id();
            
            $task = Task::create($data);

            if (!empty($data['assigned_to'])) {
                // Evento comandato da UI
                event(new \App\Domain\Core\Events\TaskAssigned($task));
            }

            return $task;
        });
    }
}
