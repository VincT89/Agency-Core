<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingProject;
use App\Models\EditorialPlan;
use App\Enums\Social\EditorialPlanStatus;

class CreateEditorialPlanAction
{
    public function execute(MarketingProject $project, array $data): EditorialPlan
    {
        return EditorialPlan::create([
            'marketing_project_id' => $project->id,
            'duration_days' => $data['duration_days'] ?? 30,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'post_count' => $data['post_count'] ?? 0,
            'status' => EditorialPlanStatus::Draft->value,
        ]);
    }
}
