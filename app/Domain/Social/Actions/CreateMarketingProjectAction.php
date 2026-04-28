<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingProject;
use Illuminate\Support\Facades\Auth;
use App\Enums\Social\MarketingProjectStatus;

class CreateMarketingProjectAction
{
    public function execute(array $data): MarketingProject
    {
        $project = MarketingProject::create([
            'client_id' => $data['client_id'],
            'project_id' => $data['project_id'] ?? null,
            'created_by' => Auth::id(),
            'title' => $data['title'],
            'brief' => $data['brief'] ?? null,
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'status' => MarketingProjectStatus::Draft->value,
            'platforms' => $data['platforms'] ?? [],
            'publication_mode' => $data['publication_mode'] ?? 'manual',
        ]);

        return $project;
    }
}
