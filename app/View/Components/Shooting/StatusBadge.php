<?php

namespace App\View\Components\Shooting;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use App\Enums\Shooting\ShootStatus;

class StatusBadge extends Component
{
    public function __construct(
        public ShootStatus $status,
        public string $context = 'admin'
    ) {}

    public function render(): View|Closure|string
    {
        return view('components.shooting.status-badge');
    }
}
