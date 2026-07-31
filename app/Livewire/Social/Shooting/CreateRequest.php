<?php

namespace App\Livewire\Social\Shooting;

use App\Domain\Shooting\Actions\CreateShootRequestAction;
use App\Models\MarketingCampaign;
use App\Models\Project;
use App\Models\Shooting\Shoot;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

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
        if (auth()->user()->isPhotographer() && ! auth()->user()->canManageSystem()) {
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
            'photographer_id' => 'required|exists:users,id',
            'location' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'client_notes' => 'nullable|string',
            'proposedSlots' => 'required|array|min:1',
            'proposedSlots.*.date' => 'required|date|after_or_equal:today',
            'proposedSlots.*.period' => 'required|in:morning,intermediate,afternoon,full_day',
        ];
    }

    public function save(CreateShootRequestAction $action)
    {
        $this->authorize('create', Shoot::class);

        $this->validate();

        $user = auth()->user();
        $project = $this->project_id
            ? Project::query()->findOrFail($this->project_id)
            : null;
        $campaign = $this->marketing_campaign_id
            ? MarketingCampaign::query()->findOrFail($this->marketing_campaign_id)
            : null;

        if (! $user->canManageSystem()) {
            if ($project && ! $user->projects()->where('projects.id', $project->id)->exists()) {
                abort(403, 'Non hai accesso a questo progetto.');
            }

            if (
                $campaign
                && ! MarketingCampaign::query()
                    ->visibleTo($user)
                    ->whereKey($campaign->id)
                    ->exists()
            ) {
                abort(403, 'Non hai accesso a questa campagna.');
            }
        }

        if ($project && $campaign && $project->client_id !== $campaign->client_id) {
            $this->addError(
                'marketing_campaign_id',
                'Il progetto e la campagna devono appartenere allo stesso cliente.'
            );

            return;
        }

        // Mappa e formatta gli slot temporali per il salvataggio
        $formattedSlots = [];
        foreach ($this->proposedSlots as $slot) {
            if (! empty($slot['date']) && ! empty($slot['period'])) {
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

        $campaigns = MarketingCampaign::query()
            ->visibleTo($user)
            ->with('client')
            ->orderBy('name')
            ->get();

        $photographers = User::where('role', 'photographer')
            ->orderBy('name')
            ->get();

        return view('livewire.social.shooting.create-request', [
            'projects' => $projects,
            'campaigns' => $campaigns,
            'photographers' => $photographers,
        ])->layout('layouts.app', ['title' => 'Nuova Richiesta Shooting']);
    }
}
