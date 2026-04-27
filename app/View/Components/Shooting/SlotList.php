<?php

namespace App\View\Components\Shooting;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use App\Models\Shooting\Shoot;

class SlotList extends Component
{
    public function __construct(
        public Shoot $shoot,
        public bool $interactive = false,
        public bool $showWarning = false
    ) {}

    public function render(): View|Closure|string
    {
        return view('components.shooting.slot-list');
    }
}
