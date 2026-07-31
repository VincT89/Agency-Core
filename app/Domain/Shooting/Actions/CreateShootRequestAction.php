<?php

namespace App\Domain\Shooting\Actions;

use App\Enums\Shooting\ShootingWorkflowEvent;
use App\Enums\Shooting\ShootSlotPeriod;
use App\Enums\Shooting\ShootStatus;
use App\Enums\UserRole;
use App\Helpers\ShootingRouteResolver;
use App\Models\MarketingCampaign;
use App\Models\Project;
use App\Models\Shooting\Shoot;
use App\Models\User;
use App\Notifications\ShootingWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateShootRequestAction
{
    public function execute(array $data, int $creatorId): Shoot
    {
        return DB::transaction(function () use ($data, $creatorId) {
            $project = ! empty($data['project_id'])
                ? Project::query()->lockForUpdate()->findOrFail($data['project_id'])
                : null;
            $campaign = ! empty($data['marketing_campaign_id'])
                ? MarketingCampaign::query()->lockForUpdate()->findOrFail($data['marketing_campaign_id'])
                : null;

            if ($project && $campaign && $project->client_id !== $campaign->client_id) {
                throw ValidationException::withMessages([
                    'marketing_campaign_id' => 'Il progetto e la campagna devono appartenere allo stesso cliente.',
                ]);
            }

            $photographer = User::query()
                ->whereKey($data['photographer_id'] ?? null)
                ->where('role', UserRole::Photographer->value)
                ->first();

            if (! $photographer) {
                throw ValidationException::withMessages([
                    'photographer_id' => 'Seleziona un fotografo valido.',
                ]);
            }

            $defaultTitle = 'Shooting: '.($project ? $project->name : ($campaign ? $campaign->name : 'Nuovo'));

            $shoot = Shoot::create([
                'project_id' => $data['project_id'] ?? null,
                'marketing_campaign_id' => $data['marketing_campaign_id'] ?? null,
                'photographer_id' => $photographer->id,
                'created_by' => $creatorId,
                'title' => $data['title'] ?? $defaultTitle,
                'code' => 'SHT-'.strtoupper(Str::random(8)),
                'location' => $data['location'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'client_notes' => $data['client_notes'] ?? null,
                'status' => ShootStatus::WaitingPhotographer,
            ]);

            foreach ($data['slots'] ?? [] as $slotData) {
                $period = ShootSlotPeriod::tryFrom((string) ($slotData['period'] ?? ''));
                if (! $period || blank($slotData['date'] ?? null)) {
                    continue;
                }

                $shoot->slots()->create([
                    'date' => $slotData['date'],
                    'period' => $period,
                    'starts_at' => $period->getStartTime(),
                    'ends_at' => $period->getEndTime(),
                ]);
            }

            if ($shoot->slots()->doesntExist()) {
                throw ValidationException::withMessages([
                    'slots' => 'Aggiungi almeno una data completa.',
                ]);
            }

            $url = ShootingRouteResolver::showRouteFor($photographer, $shoot);
            $photographer->notify(new ShootingWorkflowNotification(
                ShootingWorkflowEvent::RequestCreated,
                'Nuova richiesta shooting',
                "Sei stato assegnato allo shooting \"{$shoot->title}\". Verifica le date proposte.",
                $url,
                $shoot->id
            ));

            return $shoot;
        });
    }
}
