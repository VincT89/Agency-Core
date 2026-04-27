<?php

namespace App\Livewire\Admin\Shooting;

use App\Models\Shooting\Shoot;
use Livewire\Component;
use App\Domain\Shooting\Actions\ClientConfirmAction;
use App\Domain\Shooting\Actions\PhotographerRespondAction;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ShootShow extends Component
{
    use AuthorizesRequests;

    public Shoot $shoot;
    public $photographerNote = '';

    public function mount(Shoot $shoot)
    {
        if (!auth()->user()->canManageSystem()) {
            abort(403);
        }

        $this->authorize('view', $shoot);
        $this->shoot = $shoot->load(['project', 'slots', 'photographer', 'calendarEvent', 'task']);
    }

    public function acceptSlot($slotId)
    {
        $this->authorize('respond', $this->shoot);
        
        app(PhotographerRespondAction::class)->execute($this->shoot, $slotId, $this->photographerNote);
        $this->shoot->refresh();
        session()->flash('success', 'Hai accettato lo slot come admin.');
    }
    
    public function rejectAllSlots()
    {
        $this->authorize('respond', $this->shoot);
        
        app(PhotographerRespondAction::class)->execute($this->shoot, null, $this->photographerNote);
        $this->shoot->refresh();
        session()->flash('success', 'Hai rifiutato la richiesta come admin.');
    }

    public function confirmForClient()
    {
        $this->authorize('confirmClient', $this->shoot);
        
        app(ClientConfirmAction::class)->execute($this->shoot, true, auth()->id());
        $this->shoot->refresh();
        session()->flash('success', 'Shooting confermato. Evento e Task creati.');
    }
    
    public function rejectForClient()
    {
        $this->authorize('confirmClient', $this->shoot);
        
        app(ClientConfirmAction::class)->execute($this->shoot, false, auth()->id());
        $this->shoot->refresh();
        session()->flash('success', 'Shooting rifiutato dal cliente.');
    }

    public function render()
    {
        return view('livewire.admin.shooting.shoot-show')->layout('layouts.app');
    }
}
