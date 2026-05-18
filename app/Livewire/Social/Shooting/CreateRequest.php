<?php

namespace App\Livewire\Social\Shooting;

use App\Models\Shooting\Shoot;
use App\Models\Project;
use App\Models\User;
use Livewire\Component;
use App\Domain\Shooting\Actions\CreateShootRequestAction;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CreateRequest extends Component
{
    use AuthorizesRequests;

    public $title;
    public $project_id;
    public $marketing_campaign_id;
    public $photographer_id;
    public $location;
    public $internal_notes;
    public $client_notes;
    
    public $proposedSlots = [];

    public function mount()
    {
        if (auth()->user()->isPhotographer() && !auth()->user()->canManageSystem()) {
            abort(403, 'Accesso negato: sezione riservata a team interno.');
        }

        $this->authorize('create', Shoot::class);
        $this->addSlot();
    }

    public function addSlot()
    {
        $this->proposedSlots[] = ['date' => '', 'period' => 'morning'];
    }
    
    public function removeSlot($index)
    {
        unset($this->proposedSlots[$index]);
        $this->proposedSlots = array_values($this->proposedSlots);
    }

    public function rules()
    {
        return [
            'title' => 'nullable|string|max:255',
            'project_id' => 'required_without:marketing_campaign_id|nullable|exists:projects,id',
            'marketing_campaign_id' => 'required_without:project_id|nullable|exists:marketing_campaigns,id',
            'photographer_id' => 'nullable|exists:users,id',
            'location' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'client_notes' => 'nullable|string',
            'proposedSlots.*.date' => 'required|date',
        ];
    }

    public function save(CreateShootRequestAction $action)
    {
        $this->authorize('create', Shoot::class);
        
        $this->validate();

        $user = auth()->user();
        if (!$user->canManageSystem()) {
            if ($this->project_id && !$user->projects()->where('projects.id', $this->project_id)->exists()) {
                abort(403, 'Non hai accesso a questo progetto.');
            }
        }

        // Mappa e formatta gli slot temporali per il salvataggio
        $formattedSlots = [];
        foreach ($this->proposedSlots as $slot) {
            if (!empty($slot['date']) && !empty($slot['period'])) {
                $formattedSlots[] = ['date' => $slot['date'], 'period' => $slot['period']];
            }
        }

        if (empty($formattedSlots)) {
            $this->addError('slots', 'Compila tutti i dettagli degli slot temporali.');
            return;
        }

        $data = [
            'title' => $this->title,
            'project_id' => $this->project_id,
            'marketing_campaign_id' => $this->marketing_campaign_id,
            'photographer_id' => $this->photographer_id,
            'location' => $this->location,
            'internal_notes' => $this->internal_notes,
            'client_notes' => $this->client_notes,
            'slots' => $formattedSlots,
        ];

        $shoot = $action->execute($data, auth()->id());

        session()->flash('success', 'Richiesta di shooting creata con successo.');

        return redirect()->route('social.shooting.show', $shoot->id);
    }

    public function render()
    {
        $user = auth()->user();
        $projects = $user->canManageSystem() 
            ? Project::all() 
            : $user->projects;

        $campaigns = $user->canManageSystem() || $user->isMarketing()
            ? \App\Models\MarketingCampaign::with('client')->orderBy('name')->get()
            : \App\Models\MarketingCampaign::with('client')
                ->whereHas('client.users', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->orderBy('name')
                ->get();
            
        $photographers = User::where('role', 'photographer')->get(); 

        return view('livewire.social.shooting.create-request', [
            'projects' => $projects,
            'campaigns' => $campaigns,
            'photographers' => $photographers,
        ])->layout('layouts.app', ['title' => 'Nuova Richiesta Shooting']);
    }
}
