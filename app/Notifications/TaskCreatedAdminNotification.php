<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskCreatedAdminNotification extends Notification
{
    use Queueable;

    public function __construct(public Task $task)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $assigneeInfo = $this->task->assignee ? ' assegnata a ' . $this->task->assignee->name : ' (non assegnata)';
        return [
            'type'    => 'task_created',
            'title'   => 'Nuova task creata',
            'message' => 'È stata creata la task: "' . $this->task->title . '"' . $assigneeInfo,
            'url'     => route('tasks.show', $this->task),
            'task_id' => $this->task->id,
        ];
    }
}
