<?php

namespace App\Livewire\Social\MarketingProjects;

use Livewire\Component;
use App\Models\MarketingProject;
use App\Domain\Social\Actions\SubmitMarketingProjectToN8nAction;
use App\Domain\Social\Actions\SubmitEditorialPlanToN8nAction;
use Exception;

class MarketingProjectShow extends Component
{
    public MarketingProject $project;

    public function mount(MarketingProject $project)
    {
        $this->project = $project->load(['client', 'project', 'creator', 'editorialPlan.slots']);
    }

    public function submitToN8n(
        SubmitMarketingProjectToN8nAction $submitSingle,
        SubmitEditorialPlanToN8nAction $submitPlan
    ) {
        try {
            if ($this->project->status->value !== 'draft') {
                session()->flash('error', 'Azione non permessa: il progetto non è in stato Bozza.');
                return;
            }

            if ($this->project->type->value === 'one_shot') {
                $submitSingle->execute($this->project);
            } else {
                if ($this->project->editorialPlan) {
                    $submitPlan->execute($this->project->editorialPlan);
                }
            }

            session()->flash('success', 'Progetto in coda per l\'invio a n8n.');
            $this->project->refresh();
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            session()->flash('error', 'Errore durante l\'invio: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.social.marketing-projects.show');
    }
}
