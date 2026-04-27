<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

use App\Domain\Dashboard\Queries\AdminDashboardQuery;

class AdminDashboard extends Component
{
    public function render(AdminDashboardQuery $query)
    {
        $data = $query->getDashboardData();
        
        return view('livewire.dashboard.admin-dashboard', [
            'data' => $data
        ]);
    }
}
