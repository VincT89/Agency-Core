<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

use App\Domain\Dashboard\Queries\PhotographerDashboardQuery;

class PhotographerDashboard extends Component
{
    public function render(PhotographerDashboardQuery $query)
    {
        $data = $query->getDashboardData(auth()->user());
        
        return view('livewire.dashboard.photographer-dashboard', [
            'data' => $data
        ]);
    }
}
