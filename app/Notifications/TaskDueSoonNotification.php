<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskDueSoonNotification extends Notification
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
            'type'    => 'task_due_soon',
            'title'   => 'Task in scadenza',
            'message' => 'La task "' . $this->task->title . '" scade domani.',
            'url'     => route('tasks.show', $this->task),
            'task_id' => $this->task->id,
        ];
    }
}
