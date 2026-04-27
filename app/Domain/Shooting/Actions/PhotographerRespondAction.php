<?php

namespace App\Domain\Shooting\Actions;

use App\Models\Shooting\Shoot;
use App\Models\Shooting\ShootSlot;
use App\Models\User;
use App\Enums\Shooting\ShootStatus;
use App\Enums\Shooting\ShootSlotStatus;
use App\Enums\Shooting\ShootingWorkflowEvent;
use App\Notifications\ShootingWorkflowNotification;
use Illuminate\Support\Facades\DB;

class PhotographerRespondAction
{
    /**
     * @param Shoot $shoot
     * @param int|null $acceptedSlotId If null, it means the photographer rejected all slots.
     */
    public function execute(Shoot $shoot, ?int $acceptedSlotId, ?string $note = null): void
    {
        if ($shoot->status !== ShootStatus::WaitingPhotographer) {
            throw new \Exception('Lo shooting non è in attesa del fotografo.');
        }

        if ($shoot->selected_slot_id !== null && $acceptedSlotId !== null) {
            throw new \Exception('Slot già selezionato per questo shooting.');
        }

        DB::transaction(function () use ($shoot, $acceptedSlotId, $note) {
            
            // Mark slots
            foreach ($shoot->slots as $slot) {
                if ($acceptedSlotId && $slot->id === $acceptedSlotId) {
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
            
            if ($acceptedSlotId) {
                $shoot->update([
                    'selected_slot_id' => $acceptedSlotId,
                    'status' => ShootStatus::WaitingClient,
                ]);
                
                $this->notifyAdminsAndCreator($shoot, ShootingWorkflowEvent::PhotographerAccepted, 'Fotografo ha accettato', "Il fotografo ha accettato uno slot per lo shooting {$shoot->code}.");
            } else {
                $shoot->update([
                    'status' => ShootStatus::PhotographerRejected,
                ]);
                
                $this->notifyAdminsAndCreator($shoot, ShootingWorkflowEvent::PhotographerRejected, 'Fotografo ha rifiutato', "Il fotografo ha rifiutato tutti gli slot per lo shooting {$shoot->code}. Note: $note");
            }
            
        });
    }
    
    private function notifyAdminsAndCreator(Shoot $shoot, ShootingWorkflowEvent $event, string $title, string $message): void
    {
        $usersToNotify = User::where('role', 'admin')
            ->orWhere('id', $shoot->created_by)
            ->get()
            ->unique('id');
            
        foreach ($usersToNotify as $user) {
            $url = \App\Helpers\ShootingRouteResolver::showRouteFor($user, $shoot);
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
