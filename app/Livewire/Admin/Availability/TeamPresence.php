<?php

namespace App\Livewire\Admin\Availability;

use App\Domain\Availability\Queries\PresenceSummary;
use Livewire\Component;

class TeamPresence extends Component
{
    public function render(PresenceSummary $summary)
    {
        abort_unless(auth()->user()?->status === 'active' && auth()->user()->canManageSystem(), 403);

        return view('livewire.admin.availability.team-presence', ['operators' => $summary->team()]);
    }
}
