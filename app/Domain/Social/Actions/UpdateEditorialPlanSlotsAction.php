<?php

namespace App\Domain\Social\Actions;

use App\Models\EditorialPlanSlot;

class UpdateEditorialPlanSlotsAction
{
    public function execute(EditorialPlanSlot $slot, array $data): EditorialPlanSlot
    {
        $slot->update([
            'scheduled_date' => $data['date'] ?? $slot->scheduled_date,
            'scheduled_time' => $data['time'] ?? $slot->scheduled_time,
            'platforms' => $data['platforms'] ?? $slot->platforms,
            'topic' => $data['topic'] ?? $slot->topic,
        ]);

        return $slot;
    }
}
