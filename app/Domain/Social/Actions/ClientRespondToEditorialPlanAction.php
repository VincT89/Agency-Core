<?php

namespace App\Domain\Social\Actions;

use App\Models\EditorialPlan;
use App\Enums\Social\EditorialPlanStatus;

class ClientRespondToEditorialPlanAction
{
    public function execute(EditorialPlan $plan, string $actionType, ?string $commentBody = null): void
    {
        if ($actionType === 'approve') {
            $alreadyApproved = $plan->status->value === EditorialPlanStatus::ClientApproved->value;

            $plan->update(['status' => EditorialPlanStatus::ClientApproved->value]);
            $plan->marketingProject->update(['status' => \App\Enums\Social\MarketingProjectStatus::ClientApproved->value]);
            $plan->slots()->update(['status' => \App\Enums\Social\EditorialPlanSlotStatus::ClientApproved->value]);
            
            if (!$alreadyApproved) {
                event(new \App\Events\EditorialPlanApprovedByClient($plan));
            }
        } elseif ($actionType === 'request_changes') {
            $plan->update(['status' => EditorialPlanStatus::ClientChangesRequested->value]);
            $plan->marketingProject->update(['status' => \App\Enums\Social\MarketingProjectStatus::ClientChangesRequested->value]);
            $plan->slots()->update(['status' => \App\Enums\Social\EditorialPlanSlotStatus::ClientChangesRequested->value]);
        }
    }
}
