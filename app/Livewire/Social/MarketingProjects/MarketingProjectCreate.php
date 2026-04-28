<?php

namespace App\Livewire\Social\MarketingProjects;

use Livewire\Component;
use App\Models\Client;
use App\Models\Project;
use App\Domain\Social\Actions\CreateMarketingProjectAction;
use App\Domain\Social\Actions\CreateEditorialPlanAction;
use App\Domain\Social\Actions\CreateEditorialPlanSlotsAction;

class MarketingProjectCreate extends Component
{
    public int $step = 1;

    // Step 1: Client & Project
    public $client_id = '';
    public $project_id = '';
    
    // Step 2: Type
    public $type = 'one_shot';
    
    // Step 3: Brief & Platforms
    public $title = '';
    public $brief = '';
    public $platforms = [];
    public $publication_mode = 'manual';
    
    // Step 4: Editorial Plan Details
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
            $this->validate([
                'client_id' => 'required|exists:clients,id',
                'project_id' => 'nullable|exists:projects,id',
            ]);
        } elseif ($this->step == 2) {
            $this->validate([
                'type' => 'required|in:one_shot,editorial_plan',
            ]);
        } elseif ($this->step == 3) {
            $this->validate([
                'title' => 'required|string|max:255',
                'brief' => 'required|string',
                'platforms' => 'required|array|min:1',
                'publication_mode' => 'required|in:manual,automatic',
            ]);
            
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

    public function save(
        CreateMarketingProjectAction $createProjectAction,
        CreateEditorialPlanAction $createPlanAction,
        CreateEditorialPlanSlotsAction $createSlotsAction
    ) {
        $project = $createProjectAction->execute([
            'client_id' => $this->client_id,
            'project_id' => $this->project_id ?: null,
            'title' => $this->title,
            'brief' => $this->brief,
            'description' => $this->brief,
            'type' => $this->type,
            'platforms' => $this->platforms,
            'publication_mode' => $this->publication_mode,
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
        return $this->redirectRoute('marketing-projects.show', ['project' => $project->id], navigate: true);
    }

    public function render()
    {
        $clients = Client::orderBy('name')->get();
        $projects = [];
        if ($this->client_id) {
            $projects = Project::where('client_id', $this->client_id)->orderBy('name')->get();
        }

        return view('livewire.social.marketing-projects.create', [
            'clients' => $clients,
            'projects' => $projects,
            'availablePlatforms' => ['facebook', 'instagram', 'linkedin', 'tiktok', 'twitter'],
        ]);
    }
}
