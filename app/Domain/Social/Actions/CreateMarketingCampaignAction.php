<?php

namespace App\Domain\Social\Actions;

use App\Models\MarketingProject;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use App\Enums\Social\MarketingProjectStatus;
use App\Domain\Shooting\Actions\CreateShootRequestAction;
use App\Models\Shooting\Shoot;

class CreateMarketingCampaignAction
{
    public function __construct(private CreateShootRequestAction $createShootAction) {}

    public function execute(array $data): MarketingProject
    {
        $projectId = $data['project_id'] ?? null;

        if ($data['project_mode'] === 'new') {
            $project = Project::create([
                'client_id' => $data['client_id'],
                'name' => $data['new_project_name'],
                'description' => $data['new_project_description'] ?? null,
                'budget' => $data['new_project_budget'] ?? null,
                'deadline' => $data['new_project_deadline'] ?? null,
                'status' => 'active', // assuming 'active' is default or you have an enum
            ]);
            $projectId = $project->id;
        }

        // Product/UI name: Marketing Campaign
        // Technical model: MarketingProject
        $marketingProject = MarketingProject::create([
            'client_id' => $data['client_id'],
            'project_id' => $projectId,
            'created_by' => Auth::id(),
            'title' => $data['title'],
            'brief' => $data['brief'] ?? null,
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'status' => MarketingProjectStatus::Draft->value,
            'platforms' => $data['platforms'] ?? [],
            'publication_mode' => $data['publication_mode'] ?? 'manual',
        ]);

        if (($data['shooting_mode'] ?? 'none') === 'existing' && !empty($data['existing_shoot_id'])) {
            $shoot = Shoot::where('id', $data['existing_shoot_id'])
                ->where('project_id', $projectId)
                ->whereNull('marketing_project_id')
                ->first();
                
            if ($shoot) {
                $shoot->update(['marketing_project_id' => $marketingProject->id]);
            }
        } elseif (($data['shooting_mode'] ?? 'none') === 'new') {
            $shootData = [
                'project_id' => $projectId,
                'photographer_id' => $data['photographer_id'] ?? null,
                'location' => $data['shooting_location'] ?? null,
                'internal_notes' => $data['shooting_brief'] ?? null,
                'title' => 'Shooting per ' . $marketingProject->title,
                'slots' => $data['shooting_proposed_slots'] ?? [],
            ];
            
            $shoot = $this->createShootAction->execute($shootData, Auth::id());
            $shoot->update(['marketing_project_id' => $marketingProject->id]);
        }

        return $marketingProject;
    }
}
