<?php

namespace App\Livewire\Admin\Availability;

use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Component;

class TeamAvailability extends Component
{
    public $weekStart = '';

    public $selectedUserId = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->canManageSystem(), 403);

        $this->weekStart = CarbonImmutable::now()->startOfWeek()->toDateString();
    }

    public function previousWeek(): void
    {
        $this->weekStart = $this->weekStartDate()->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = $this->weekStartDate()->addWeek()->toDateString();
    }

    public function currentWeek(): void
    {
        $this->weekStart = CarbonImmutable::now()->startOfWeek()->toDateString();
    }

    public function render()
    {
        $weekStart = $this->weekStartDate();
        $weekEnd = $weekStart->endOfWeek();
        $days = collect(range(0, 6))->map(
            fn (int $offset) => $weekStart->addDays($offset)
        );

        $users = User::query()
            ->when(
                $this->selectedUserId !== '',
                fn ($query) => $query->whereKey((int) $this->selectedUserId)
            )
            ->with(['availabilities' => fn ($query) => $query
                ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->orderBy('date')
                ->orderBy('starts_at')])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return view('livewire.admin.availability.team-availability', [
            'days' => $days,
            'weekStartDate' => $weekStart,
            'weekEndDate' => $weekEnd,
            'users' => $users,
            'userOptions' => User::query()->orderBy('name')->get(['id', 'name', 'status']),
        ])->layout('layouts.app', ['title' => 'Disponibilità team']);
    }

    private function weekStartDate(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->weekStart)->startOfWeek();
        } catch (\Throwable) {
            return CarbonImmutable::now()->startOfWeek();
        }
    }
}
