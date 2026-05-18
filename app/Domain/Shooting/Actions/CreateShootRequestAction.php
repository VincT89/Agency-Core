<?php

namespace App\Domain\Shooting\Actions;

use App\Models\Shooting\Shoot;
use App\Models\Project;
use App\Enums\Shooting\ShootStatus;
use App\Enums\Shooting\ShootSlotPeriod;
use App\Notifications\ShootingWorkflowNotification;
use App\Enums\Shooting\ShootingWorkflowEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateShootRequestAction
{
    public function execute(array $data, int $creatorId): Shoot
    {
        return DB::transaction(function () use ($data, $creatorId) {
            $project = !empty($data['project_id']) ? Project::find($data['project_id']) : null;
            $campaign = !empty($data['marketing_campaign_id']) ? \App\Models\MarketingCampaign::find($data['marketing_campaign_id']) : null;
            
            $defaultTitle = 'Shooting: ' . ($project ? $project->name : ($campaign ? $campaign->name : 'Nuovo'));

            $shoot = Shoot::create([
                'project_id' => $data['project_id'] ?? null,
                'marketing_campaign_id' => $data['marketing_campaign_id'] ?? null,
                'photographer_id' => $data['photographer_id'] ?? null,
                'created_by' => $creatorId,
                'title' => $data['title'] ?? $defaultTitle,
                'code' => 'SHT-' . strtoupper(Str::random(8)),
                'location' => $data['location'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'client_notes' => $data['client_notes'] ?? null,
                'status' => ShootStatus::WaitingPhotographer,
            ]);
            
            if (!empty($data['slots'])) {
                foreach ($data['slots'] as $slotData) {
                    $period = ShootSlotPeriod::tryFrom($slotData['period']);
                    if (!$period) continue;
                    
                    $shoot->slots()->create([
                        'date' => $slotData['date'],
                        'period' => $period,
                        'starts_at' => $period->getStartTime(),
                        'ends_at' => $period->getEndTime(),
                    ]);
                }
            }
            
            // Avvisa il fotografo della nuova assegnazione
            if ($shoot->photographer) {
                $url = \App\Helpers\ShootingRouteResolver::showRouteFor($shoot->photographer, $shoot);
                $shoot->photographer->notify(new ShootingWorkflowNotification(
                    ShootingWorkflowEvent::RequestCreated,
                    'Nuova Richiesta Shooting',
                    "Sei stato assegnato a un nuovo shooting: {$defaultTitle}. Verifica gli slot.",
                    $url,
                    $shoot->id
                ));
            }
            
            return $shoot;
        });
    }
}
