<?php

namespace App\Domain\Shooting\Actions;

use App\Enums\Shooting\ShootingWorkflowEvent;
use App\Enums\Shooting\ShootSlotStatus;
use App\Enums\Shooting\ShootStatus;
use App\Helpers\ShootingRouteResolver;
use App\Models\Shooting\Shoot;
use App\Models\User;
use App\Notifications\ShootingWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PhotographerRespondAction
{
    public function execute(Shoot $shoot, ?int $acceptedSlotId, ?string $note = null): void
    {
        if ($shoot->status !== ShootStatus::WaitingPhotographer) {
            throw ValidationException::withMessages([
                'slot' => 'Lo shooting non è più in attesa del fotografo.',
            ]);
        }

        if ($shoot->selected_slot_id !== null) {
            throw ValidationException::withMessages([
                'slot' => 'È già stato selezionato uno slot per questo shooting.',
            ]);
        }

        if (
            $acceptedSlotId !== null
            && ! $shoot->slots()->whereKey($acceptedSlotId)->exists()
        ) {
            throw ValidationException::withMessages([
                'slot' => 'Lo slot selezionato non appartiene a questo shooting.',
            ]);
        }

        DB::transaction(function () use ($shoot, $acceptedSlotId, $note) {
            foreach ($shoot->slots as $slot) {
                if ($acceptedSlotId !== null && $slot->id === $acceptedSlotId) {
                    $slot->update([
                        'status' => ShootSlotStatus::Accepted,
                        'responded_at' => now(),
                        'photographer_note' => $note,
                    ]);
                } else {
                    $slot->update([
                        'status' => ShootSlotStatus::Rejected,
                        'responded_at' => now(),
                    ]);
                }
            }

            if ($acceptedSlotId !== null) {
                $shoot->update([
                    'selected_slot_id' => $acceptedSlotId,
                    'status' => ShootStatus::WaitingClient,
                    'client_confirmation_status' => null,
                    'client_confirmed_at' => null,
                    'client_confirmation_channel' => null,
                    'client_notification_recipient' => null,
                    'client_notified_at' => null,
                ]);

                $this->notifyAdminsAndCreator(
                    $shoot,
                    ShootingWorkflowEvent::PhotographerAccepted,
                    'Fotografo disponibile',
                    "Il fotografo ha confermato uno slot per lo shooting {$shoot->code}. Ora il cliente deve essere informato."
                );

                return;
            }

            $shoot->update([
                'status' => ShootStatus::PhotographerRejected,
            ]);

            $this->notifyAdminsAndCreator(
                $shoot,
                ShootingWorkflowEvent::PhotographerRejected,
                'Nuove date necessarie',
                "Il fotografo ha rifiutato le date dello shooting {$shoot->code}. "
                    .($note ? "Nota: {$note}" : 'Prepara una nuova proposta.')
            );
        });
    }

    private function notifyAdminsAndCreator(
        Shoot $shoot,
        ShootingWorkflowEvent $event,
        string $title,
        string $message
    ): void {
        $usersToNotify = User::query()
            ->where('role', 'admin')
            ->orWhere('id', $shoot->created_by)
            ->get()
            ->unique('id');

        foreach ($usersToNotify as $user) {
            $url = ShootingRouteResolver::showRouteFor($user, $shoot);
            $user->notify(new ShootingWorkflowNotification(
                $event,
                $title,
                $message,
                $url,
                $shoot->id
            ));
        }
    }
}
