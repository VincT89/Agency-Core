<?php

namespace App\View\Components\Shooting;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use App\Models\Shooting\Shoot;

class WorkflowTimeline extends Component
{
    public function __construct(
        public Shoot $shoot
    ) {}

    public function render(): View|Closure|string
    {
        return view('components.shooting.workflow-timeline');
    }
}
