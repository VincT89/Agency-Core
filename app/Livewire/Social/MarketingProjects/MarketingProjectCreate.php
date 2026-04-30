<?php

namespace App\Livewire\Social\MarketingProjects;

use Livewire\Component;
use App\Models\Client;
use App\Models\Project;
use App\Domain\Social\Actions\CreateMarketingCampaignAction;
use App\Domain\Social\Actions\CreateEditorialPlanAction;
use App\Domain\Social\Actions\CreateEditorialPlanSlotsAction;

class MarketingProjectCreate extends Component
{
    public int $step = 1;

    // Step 1: Selezione Cliente e Progetto
    public $client_id = '';
    public $project_mode = 'existing';
    public $project_id = '';
    public $new_project_name = '';
    public $new_project_description = '';
    public $new_project_budget = '';
    public $new_project_deadline = '';
    
    // Step 2: Tipologia Progetto (Singolo o Piano)
    public $type = 'one_shot';
    
    // Step 3: Brief, Piattaforme e Produzione Contenuti
    public $title = '';
    public $brief = '';
    public $platforms = [];
    public $publication_mode = 'manual';
    public $shooting_mode = 'none';
    public $existing_shoot_id = null;
    public $photographer_id = null;
    public $shooting_location = '';
    public $shooting_brief = '';
    public array $shooting_proposed_slots = [];
    
    // Step 4: Dettagli Piano Editoriale (Solo se tipo = piano)
    public $duration_days = 30;
    public $start_date = '';
    public $end_date = '';
    public array $planSlots = [];

    public function mount()
    {
        $this->start_date = now()->addDays(2)->format('Y-m-d');
        $this->end_date = now()->addDays(32)->format('Y-m-d');
    }

    public function nextStep()
    {
        if ($this->step == 1) {
            $rules = [
                'client_id' => 'required|exists:clients,id',
                'project_mode' => 'required|in:existing,new',
            ];
            if ($this->project_mode === 'existing') {
                $rules['project_id'] = 'required|exists:projects,id';
            } else {
                $rules['new_project_name'] = 'required|string|max:255';
                $rules['new_project_description'] = 'nullable|string';
                $rules['new_project_budget'] = 'nullable|numeric|min:0';
                $rules['new_project_deadline'] = 'nullable|date';
            }
            $this->validate($rules);
        } elseif ($this->step == 2) {
            $this->validate([
                'type' => 'required|in:one_shot,editorial_plan',
            ]);
        } elseif ($this->step == 3) {
            $rules = [
                'title' => 'required|string|max:255',
                'brief' => 'required|string',
                'platforms' => 'required|array|min:1',
                'platforms.*' => 'in:facebook,instagram,tiktok',
                'publication_mode' => 'required|in:manual,automatic',
                'shooting_mode' => 'required|in:none,existing,new',
            ];
            
            if ($this->shooting_mode === 'existing') {
                $rules['existing_shoot_id'] = 'required|exists:shoots,id';
            } elseif ($this->shooting_mode === 'new') {
                $rules['photographer_id'] = 'required|exists:users,id';
                $rules['shooting_location'] = 'required|string';
                $rules['shooting_brief'] = 'required|string';
                $rules['shooting_proposed_slots'] = 'required|array|min:1';
                $rules['shooting_proposed_slots.*.date'] = 'required|date';
                $rules['shooting_proposed_slots.*.period'] = 'required|string';
            }

            $this->validate($rules);
            
            if ($this->type == 'one_shot') {
                $this->step = 5;
                return;
            }
        } elseif ($this->step == 4) {
            $this->validate([
                'duration_days' => 'required|integer|min:1',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'planSlots' => 'required|array|min:1',
                'planSlots.*.date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:end_date'],
                'planSlots.*.time' => 'required|string',
                'planSlots.*.topic' => 'nullable|string',
                'planSlots.*.platforms' => 'required|array|min:1',
            ]);

            $dates = collect($this->planSlots)->pluck('date');
            if ($dates->duplicates()->isNotEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['planSlots' => 'Hai inserito più di uno slot per la stessa data.']);
            }
        }
        
        $this->step++;
    }

    public function prevStep()
    {
        if ($this->step == 5 && $this->type == 'one_shot') {
            $this->step = 3;
            return;
        }
        $this->step--;
    }

    public function addSlot()
    {
        $this->planSlots[] = [
            'date' => '',
            'time' => '12:00',
            'topic' => '',
            'platforms' => $this->platforms,
        ];
    }

    public function removeSlot($index)
    {
        unset($this->planSlots[$index]);
        $this->planSlots = array_values($this->planSlots);
    }

    public function addShootingSlot()
    {
        $this->shooting_proposed_slots[] = [
            'date' => '',
            'period' => 'morning',
        ];
    }

    public function removeShootingSlot($index)
    {
        unset($this->shooting_proposed_slots[$index]);
        $this->shooting_proposed_slots = array_values($this->shooting_proposed_slots);
    }

    public function save(
        CreateMarketingCampaignAction $createProjectAction,
        CreateEditorialPlanAction $createPlanAction,
        CreateEditorialPlanSlotsAction $createSlotsAction
    ) {
        $project = $createProjectAction->execute([
            'client_id' => $this->client_id,
            'project_mode' => $this->project_mode,
            'project_id' => $this->project_id,
            'new_project_name' => $this->new_project_name,
            'new_project_description' => $this->new_project_description,
            'new_project_budget' => $this->new_project_budget,
            'new_project_deadline' => $this->new_project_deadline,
            'title' => $this->title,
            'brief' => $this->brief,
            'description' => $this->brief,
            'type' => $this->type,
            'platforms' => $this->platforms,
            'publication_mode' => $this->publication_mode,
            'shooting_mode' => $this->shooting_mode,
            'existing_shoot_id' => $this->existing_shoot_id,
            'photographer_id' => $this->photographer_id,
            'shooting_location' => $this->shooting_location,
            'shooting_brief' => $this->shooting_brief,
            'shooting_proposed_slots' => $this->shooting_proposed_slots,
        ]);

        if ($this->type === 'editorial_plan') {
            $plan = $createPlanAction->execute($project, [
                'duration_days' => $this->duration_days,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'post_count' => count($this->planSlots),
            ]);

            if (count($this->planSlots) > 0) {
                $createSlotsAction->execute($plan, $this->planSlots);
            }
        }

        session()->flash('success', 'Progetto creato con successo e pronto per l\'invio a n8n.');
        return $this->redirectRoute('marketing-projects.show', ['project' => $project->id]);
    }

    public function getClientSocialStatusProperty()
    {
        if (!$this->client_id) return null;
        $client = Client::find($this->client_id);
        if (!$client) return null;

        $tiktokAccount = $client->socialAccountFor(\App\Enums\Social\SocialPlatform::Tiktok->value);

        return [
            'is_meta_ready' => $client->isMetaReady(),
            'is_tiktok_ready' => $tiktokAccount?->isReadyToPublish() ?? false,
        ];
    }

    public function render()
    {
        $clients = Client::orderBy('name')->get();
        $projects = [];
        $availableShoots = [];
        
        if ($this->client_id) {
            $projects = Project::where('client_id', $this->client_id)->orderBy('name')->get();
        }
        if ($this->project_mode === 'existing' && $this->project_id) {
            $availableShoots = \App\Models\Shooting\Shoot::where('project_id', $this->project_id)
                ->whereNull('marketing_project_id')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        $photographers = \App\Models\User::where('role', \App\Enums\UserRole::Photographer->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.social.marketing-projects.create', [
            'clients' => $clients,
            'projects' => $projects,
            'availableShoots' => $availableShoots,
            'photographers' => $photographers,
            'availablePlatforms' => ['facebook', 'instagram', 'tiktok'],
        ]);
    }
}
