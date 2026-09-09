<?php

namespace App\Domain\Core\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Task;

class TaskAssigned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $task;

    public readonly ?int $actorId;

    public function __construct(Task $task)
    {
        $this->task = $task;
        $this->actorId = auth()->id();
    }
}
