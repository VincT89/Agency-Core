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
            if ($this->project->type->value === 'one_shot') {
                $submitSingle->execute($this->project);
            } else {
                if ($this->project->editorialPlan) {
                    $submitPlan->execute($this->project->editorialPlan);
                    $this->project->update(['status' => \App\Enums\Social\MarketingProjectStatus::SubmittedToN8n->value]);
                }
            }

            session()->flash('success', 'Progetto inviato con successo a n8n per la generazione.');
            $this->project->refresh();
            
        } catch (Exception $e) {
            session()->flash('error', 'Errore durante l\'invio: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.social.marketing-projects.show');
    }
}
