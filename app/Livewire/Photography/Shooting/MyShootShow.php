<?php

namespace App\Livewire\Photography\Shooting;

use App\Models\Shooting\Shoot;
use Livewire\Component;
use App\Domain\Shooting\Actions\PhotographerRespondAction;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MyShootShow extends Component
{
    use AuthorizesRequests;

    public Shoot $shoot;
    public $photographerNote = '';

    public function mount(Shoot $shoot)
    {
        if (!auth()->user()->isPhotographer()) {
            abort(403, 'Accesso negato: sezione riservata ai fotografi.');
        }

        $this->authorize('view', $shoot);
        $this->shoot = $shoot->load(['project', 'slots', 'photographer', 'calendarEvent', 'task']);
    }

    public function acceptSlot($slotId)
    {
        $this->authorize('respond', $this->shoot);
        
        app(PhotographerRespondAction::class)->execute($this->shoot, $slotId, $this->photographerNote);
        $this->shoot->refresh();
        session()->flash('success', 'Hai accettato lo slot.');
    }
    
    public function rejectAllSlots()
    {
        $this->authorize('respond', $this->shoot);
        
        app(PhotographerRespondAction::class)->execute($this->shoot, null, $this->photographerNote);
        $this->shoot->refresh();
        session()->flash('success', 'Hai rifiutato la richiesta.');
    }

    public function render()
    {
        return view('livewire.photography.shooting.my-shoot-show')->layout('layouts.app');
    }
}
