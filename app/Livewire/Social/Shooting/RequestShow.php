<?php

namespace App\Livewire\Social\Shooting;

use App\Models\Shooting\Shoot;
use Livewire\Component;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class RequestShow extends Component
{
    use AuthorizesRequests;

    public Shoot $shoot;

    public function mount(Shoot $shoot)
    {
        if (auth()->user()->isPhotographer() && !auth()->user()->canManageSystem()) {
            abort(403, 'Accesso negato: sezione riservata a team interno.');
        }

        $this->authorize('view', $shoot);
        $this->shoot = $shoot->load(['project', 'slots', 'photographer', 'calendarEvent', 'task']);
    }

    public function render()
    {
        return view('livewire.social.shooting.request-show')->layout('layouts.app');
    }
}
