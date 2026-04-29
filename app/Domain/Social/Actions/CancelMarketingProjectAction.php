<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingProject;
use App\Enums\Social\MarketingProjectStatus;

class CancelMarketingProjectAction
{
    public function execute(MarketingProject $project): void
    {
        $project->update([
            'status' => MarketingProjectStatus::Cancelled->value,
        ]);
        
        if ($project->editorialPlan) {
            // Segna come completato il piano editoriale correlato se presente
            $project->editorialPlan->update(['status' => \App\Enums\Social\EditorialPlanStatus::Completed->value]);
        }
    }
}
