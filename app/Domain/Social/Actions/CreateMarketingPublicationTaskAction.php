<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingProject;
use App\Models\Task;

class CreateMarketingPublicationTaskAction
{
    public function execute(MarketingProject $project, ?\App\Models\SocialPost $post = null, ?int $assignedTo = null): Task
    {
        return Task::create([
            'project_id' => $project->project_id,
            'marketing_project_id' => $project->id,
            'social_post_id' => $post?->id,
            'created_by' => auth()->id() ?? 1,
            'assigned_to' => $assignedTo,
            'title' => 'Pubblicare post: ' . ($post?->title ?? $project->title),
            'description' => implode("\n", [
                'Il cliente ha approvato il contenuto.',
                'Piattaforme: ' . implode(', ', $project->platforms ?? []),
                'Caption: ' . str($post?->currentVersion?->caption)->limit(300),
                'Media: ' . ($post?->currentVersion?->preview_url ?? 'Nessun media'),
                'Apri la Publication Board per pubblicare manualmente e chiudere il task.',
            ]),
            'status' => 'todo',
            'priority' => 'high',
        ]);
    }
}
