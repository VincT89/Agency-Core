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
                'slug' => \Illuminate\Support\Str::slug($data['new_project_name'] . '-' . uniqid()),
                'description' => $data['new_project_description'] ?? null,
                'budget' => $data['new_project_budget'] ?? null,
                'deadline' => $data['new_project_deadline'] ?? null,
                'status' => 'active', // assuming 'active' is default or you have an enum
            ]);
            
            if (Auth::check()) {
                $project->users()->attach(Auth::id(), [
                    'role' => 'manager',
                    'assignment_status' => 'active',
                    'assigned_at' => now(),
                ]);
            }
            
            $projectId = $project->id;
        }

        // Legacy mapping come da indicazioni
        $legacyType = ($data['service_type'] ?? '') === 'editorial_plan' ? 'editorial_plan' : 'one_shot';

        // Product/UI name: Marketing Campaign
        // Technical model: MarketingProject
        $marketingProject = MarketingProject::create([
            'client_id' => $data['client_id'],
            'project_id' => $projectId,
            'created_by' => Auth::id(),
            'title' => $data['title'],
            'brief' => $data['brief'] ?? null,
            'description' => $data['description'] ?? null,
            'type' => $legacyType,
            'service_type' => $data['service_type'] ?? 'other',
            'campaign_structure' => $data['campaign_structure'] ?? 'one_shot',
            'service_options' => $data['service_options'] ?? [],
            'status' => MarketingProjectStatus::Draft->value,
            'platforms' => $data['service_options']['platforms'] ?? [], // fallback temporaneo
        ]);

        if (($data['shooting_mode'] ?? 'none') === 'existing' && !empty($data['existing_shoot_id'])) {
            $shoot = Shoot::where('id', $data['existing_shoot_id'])
                ->where('project_id', $projectId)
                ->whereNull('marketing_project_id')
                ->first();
                
            if (!$shoot) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'existing_shoot_id' => "Lo shooting selezionato non è valido, appartiene ad un'altra commessa o è già stato assegnato a un'altra campagna."
                ]);
            }
            
            $shoot->update(['marketing_project_id' => $marketingProject->id]);
            
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
