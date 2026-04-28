<?php

namespace App\Domain\Social\Actions;

use App\Models\EditorialPlan;
use App\Enums\Social\EditorialPlanStatus;
use App\Enums\Social\EditorialPlanSlotStatus;

class SubmitEditorialPlanToN8nAction
{
    public function __construct(private RequestEditorialPlanGenerationAction $requestAction) {}

    public function execute(EditorialPlan $plan): void
    {
        $plan->update([
            'status' => EditorialPlanStatus::SubmittedToN8n->value,
        ]);

        $plan->slots()->where('status', EditorialPlanSlotStatus::Empty->value)->update([
            'status' => EditorialPlanSlotStatus::SubmittedToN8n->value,
        ]);

        $this->requestAction->execute($plan);
    }
}
