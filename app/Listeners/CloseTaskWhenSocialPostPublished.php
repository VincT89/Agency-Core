<?php

namespace App\Listeners;

use App\Events\EditorialSlotPublished;
use App\Models\Task;

class CloseTaskWhenSocialPostPublished
{

    public function handle(EditorialSlotPublished $event): void
    {
        $slot = $event->slot;

        // Cerca task associati allo slot o al post correlato
        $query = Task::where('editorial_plan_slot_id', $slot->id);
        
        if ($slot->social_post_id) {
            $query->orWhere('social_post_id', $slot->social_post_id);
        }

        $tasks = $query->get();

        foreach ($tasks as $task) {
            if ($task && !in_array($task->status, ['done', 'cancelled', 'blocked'])) {
                $task->update([
                    'status' => 'done',
                    'completed_at' => now(),
                ]);
            }
        }
    }
}
