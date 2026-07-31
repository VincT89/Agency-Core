<?php

namespace App\Domain\Shooting\Actions;

use App\Enums\Shooting\ShootingWorkflowEvent;
use App\Enums\Shooting\ShootSlotPeriod;
use App\Enums\Shooting\ShootStatus;
use App\Enums\UserRole;
use App\Helpers\ShootingRouteResolver;
use App\Models\Shooting\Shoot;
use App\Models\User;
use App\Notifications\ShootingWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReopenShootProposalAction
{
    public function execute(Shoot $shoot, int $photographerId, array $slots): void
    {
        if (! in_array($shoot->status, [
            ShootStatus::PhotographerRejected,
            ShootStatus::ClientRejected,
        ], true)) {
            throw ValidationException::withMessages([
                'revisionSlots' => 'La proposta può essere rivista soltanto dopo un rifiuto.',
            ]);
        }

        $photographer = User::query()
            ->whereKey($photographerId)
            ->where('role', UserRole::Photographer->value)
            ->first();

        if (! $photographer) {
            throw ValidationException::withMessages([
                'revisionPhotographerId' => 'Seleziona un fotografo valido.',
            ]);
        }

        $normalizedSlots = collect($slots)
            ->map(function (array $slot): ?array {
                $period = ShootSlotPeriod::tryFrom((string) ($slot['period'] ?? ''));
                $date = $slot['date'] ?? null;

                if (! $period || blank($date)) {
                    return null;
                }

                return [
                    'date' => $date,
                    'period' => $period,
                    'starts_at' => $period->getStartTime(),
                    'ends_at' => $period->getEndTime(),
                ];
            })
            ->filter()
            ->values();

        if ($normalizedSlots->isEmpty()) {
            throw ValidationException::withMessages([
                'revisionSlots' => 'Aggiungi almeno una nuova proposta completa.',
            ]);
        }

        DB::transaction(function () use ($shoot, $photographer, $normalizedSlots) {
            $shoot->update([
                'photographer_id' => $photographer->id,
                'selected_slot_id' => null,
                'status' => ShootStatus::WaitingPhotographer,
                'client_confirmation_status' => null,
                'client_confirmed_at' => null,
                'client_confirmation_channel' => null,
                'client_notification_recipient' => null,
                'client_notified_at' => null,
                'whatsapp_message_id' => null,
            ]);

            $shoot->slots()->delete();

            foreach ($normalizedSlots as $slot) {
                $shoot->slots()->create($slot);
            }

            $url = ShootingRouteResolver::showRouteFor($photographer, $shoot);
            $photographer->notify(new ShootingWorkflowNotification(
                ShootingWorkflowEvent::RequestReopened,
                'Nuove date per uno shooting',
                "Sono state proposte nuove date per lo shooting {$shoot->code}.",
                $url,
                $shoot->id
            ));
        });
    }
}
