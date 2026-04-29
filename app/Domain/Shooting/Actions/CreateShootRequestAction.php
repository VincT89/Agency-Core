<?php

namespace App\Domain\Shooting\Actions;

use App\Models\Shooting\Shoot;
use App\Models\Project;
use App\Enums\Shooting\ShootStatus;
use App\Enums\Shooting\ShootSlotPeriod;
use App\Notifications\ShootingWorkflowNotification;
use App\Enums\Shooting\ShootingWorkflowEvent;

class CreateShootRequestAction
{
    public function execute(array $data, int $creatorId): Shoot
    {
        return DB::transaction(function () use ($data, $creatorId) {
            $project = Project::findOrFail($data['project_id']);
            
            $shoot = Shoot::create([
                'project_id' => $data['project_id'],
                'photographer_id' => $data['photographer_id'] ?? null,
                'created_by' => $creatorId,
                'title' => $data['title'] ?? 'Shooting: ' . $project->name,
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
                    "Sei stato assegnato a un nuovo shooting per il progetto {$project->name}. Verifica gli slot.",
                    $url,
                    $shoot->id
                ));
            }
            
            return $shoot;
        });
    }
}
