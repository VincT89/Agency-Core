<?php

namespace App\Listeners;

use App\Events\EditorialSlotPublished;
use App\Models\Task;

class CloseTaskWhenSocialPostPublished
{
    /**
     * Handle the event.
     */
    public function handle(EditorialSlotPublished $event): void
    {
        $slot = $event->slot;

        // Cerca prima per editorial_plan_slot_id, poi per social_post_id (se the slot has one)
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
