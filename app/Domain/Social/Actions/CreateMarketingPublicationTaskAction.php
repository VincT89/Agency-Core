<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingProject;
use App\Models\Task;

class CreateMarketingPublicationTaskAction
{
    public function execute(MarketingProject $project, ?int $assignedTo = null): Task
    {
        return Task::create([
            'project_id' => $project->project_id,
            'marketing_project_id' => $project->id,
            'created_by' => auth()->id() ?? 1,
            'assigned_to' => $assignedTo,
            'title' => 'Pubblicare progetto: ' . $project->title,
            'description' => 'Pubblicare i contenuti per il progetto di marketing.',
            'status' => 'todo',
            'priority' => 'high',
        ]);
    }
}
