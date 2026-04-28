<?php

namespace App\Domain\Social\Actions;

use App\Models\EditorialPlan;
use App\Enums\Social\EditorialPlanSlotStatus;

class CreateEditorialPlanSlotsAction
{
    public function execute(EditorialPlan $plan, array $slotsData): void
    {
        foreach ($slotsData as $slotData) {
            $plan->slots()->create([
                'scheduled_date' => $slotData['date'] ?? null,
                'scheduled_time' => $slotData['time'] ?? null,
                'platforms' => $slotData['platforms'] ?? [],
                'topic' => $slotData['topic'] ?? null,
                'status' => EditorialPlanSlotStatus::Empty->value,
            ]);
        }
        
        $plan->update([
            'post_count' => $plan->slots()->count(),
            'status' => \App\Enums\Social\EditorialPlanStatus::DatesSelected->value,
        ]);
    }
}
