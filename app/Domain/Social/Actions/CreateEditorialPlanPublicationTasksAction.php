<?php

namespace App\Domain\Social\Actions;

use App\Models\EditorialPlan;

class CreateEditorialPlanPublicationTasksAction
{
    public function execute(EditorialPlan $plan, ?int $assignedTo = null): void
    {
        foreach ($plan->slots as $slot) {
            if ($slot->status->value === \App\Enums\Social\EditorialPlanSlotStatus::ClientApproved->value) {
                \App\Models\Task::create([
                    'project_id' => $plan->marketingProject->project_id,
                    'marketing_project_id' => $plan->marketingProject->id,
                    'editorial_plan_slot_id' => $slot->id,
                    'social_post_id' => $slot->social_post_id,
                    'created_by' => auth()->id() ?? 1,
                    'assigned_to' => $assignedTo,
                    'title' => 'Pubblicare post per slot: ' . $slot->scheduled_date?->format('d/m/Y'),
                    'description' => 'Pubblicare il post sui social.',
                    'status' => 'todo',
                    'priority' => 'high',
                    'due_date' => $slot->scheduled_date,
                ]);

                $slot->update(['status' => \App\Enums\Social\EditorialPlanSlotStatus::TaskCreated->value]);
            }
        }
    }
}
