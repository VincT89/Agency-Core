<?php

namespace App\Livewire\Admin\Dashboard;

use Livewire\Component;
use App\Models\Shooting\Shoot;
use App\Enums\Shooting\ShootStatus;

class ShootingOverview extends Component
{
    public function render()
    {
        // Filtra gli shooting operativi che richiedono un'azione immediata
        $actionShoots = Shoot::whereIn('status', [
                ShootStatus::WaitingClient->value, 
                ShootStatus::ClientRejected->value, 
                ShootStatus::WaitingPhotographer->value,
                ShootStatus::Scheduled->value
            ])
            ->get()
            ->sortBy(function($shoot) {
                // Ordina per priorità di sblocco operativo
                return match($shoot->status->value) {
                    ShootStatus::WaitingClient->value => 1,
                    ShootStatus::ClientRejected->value => 2,
                    ShootStatus::WaitingPhotographer->value => 3,
                    ShootStatus::Scheduled->value => 4,
                    default => 5,
                };
            })
            ->take(6); // Limita gli elementi per non saturare la dashboard

        return view('livewire.admin.dashboard.shooting-overview', [
            'waitingPhotographer' => Shoot::whereStatus(ShootStatus::WaitingPhotographer)->count(),
            'waitingClient' => Shoot::whereStatus(ShootStatus::WaitingClient)->count(),
            'clientRejected' => Shoot::whereStatus(ShootStatus::ClientRejected)->count(),
            'actionShoots' => $actionShoots,
        ]);
    }
}
