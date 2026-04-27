<?php

namespace App\Livewire\Admin\Dashboard;

use Livewire\Component;
use App\Models\Shooting\Shoot;
use App\Enums\Shooting\ShootStatus;

class ShootingOverview extends Component
{
    public function render()
    {
        // Ottieni tutti gli shooting non completati/archiviati che richiedono azione
        $actionShoots = Shoot::whereIn('status', [
                ShootStatus::WaitingClient->value, 
                ShootStatus::ClientRejected->value, 
                ShootStatus::WaitingPhotographer->value,
                ShootStatus::Scheduled->value
            ])
            ->get()
            ->sortBy(function($shoot) {
                // Ordine: 1. waiting_client, 2. client_rejected, 3. waiting_photographer, 4. scheduled
                return match($shoot->status->value) {
                    ShootStatus::WaitingClient->value => 1,
                    ShootStatus::ClientRejected->value => 2,
                    ShootStatus::WaitingPhotographer->value => 3,
                    ShootStatus::Scheduled->value => 4,
                    default => 5,
                };
            })
            ->take(6); // Prendi i primi 6

        return view('livewire.admin.dashboard.shooting-overview', [
            'waitingPhotographer' => Shoot::whereStatus(ShootStatus::WaitingPhotographer)->count(),
            'waitingClient' => Shoot::whereStatus(ShootStatus::WaitingClient)->count(),
            'clientRejected' => Shoot::whereStatus(ShootStatus::ClientRejected)->count(),
            'actionShoots' => $actionShoots,
        ]);
    }
}
