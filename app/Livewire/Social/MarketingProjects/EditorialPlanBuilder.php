<?php

namespace App\Livewire\Social\MarketingProjects;

use Livewire\Component;
use App\Models\EditorialPlan;

class EditorialPlanBuilder extends Component
{
    public EditorialPlan $plan;

    public function mount(EditorialPlan $plan)
    {
        $this->plan = $plan->load('slots');
    }

    public function render()
    {
        return view('livewire.social.marketing-projects.editorial-plan-builder');
    }
}
