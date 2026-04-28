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
        $requiresMeta = in_array('facebook', $plan->project->platforms) || in_array('instagram', $plan->project->platforms);
        if ($requiresMeta && !$plan->project->client->isMetaReady()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'social_access' => "Il cliente non ha gli accessi Meta Business configurati o verificati. L'invio a n8n è bloccato.",
            ]);
        }
        $plan->update([
            'status' => EditorialPlanStatus::SubmittedToN8n->value,
        ]);

        $plan->slots()->where('status', EditorialPlanSlotStatus::Empty->value)->update([
            'status' => EditorialPlanSlotStatus::SubmittedToN8n->value,
        ]);

        $this->requestAction->execute($plan);
    }
}
