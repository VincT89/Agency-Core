<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
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
        return [
            'type'    => 'task_assigned',
            'title'   => 'Nuova task assegnata',
            'message' => 'Ti è stata assegnata la task: ' . $this->task->title,
            'url'     => route('tasks.show', $this->task),
            'task_id' => $this->task->id,
        ];
    }
}
