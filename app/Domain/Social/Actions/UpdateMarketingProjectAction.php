<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingProject;

class UpdateMarketingProjectAction
{
    public function execute(MarketingProject $project, array $data): MarketingProject
    {
        $project->update([
            'title' => $data['title'] ?? $project->title,
            'brief' => $data['brief'] ?? $project->brief,
            'description' => $data['description'] ?? $project->description,
            'platforms' => $data['platforms'] ?? $project->platforms,
            'publication_mode' => $data['publication_mode'] ?? $project->publication_mode,
        ]);

        return $project;
    }
}
