<?php

namespace App\Livewire\Availability;

use App\Domain\Availability\Queries\PresenceSummary;
use App\Models\UserPresenceSession;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class PresenceHistory extends Component
{
    use WithPagination;

    #[Locked]
    public string $weekStart;
    #[Locked]
    public bool $team = false;
    #[Locked]
    public ?int $userId = null;

    public function render(PresenceSummary $summary)
    {
        abort_unless(auth()->user()?->status === 'active', 403);
        abort_if($this->team && ! auth()->user()->canManageSystem(), 403);
        $from = CarbonImmutable::parse($this->weekStart)->startOfWeek();
        $userId = $this->team ? $this->userId : auth()->id();
        $sessions = UserPresenceSession::query()->with(['user:id,name', 'intervals'])
            ->whereBetween('date', [$from->toDateString(), $from->endOfWeek()->toDateString()])
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('started_at')->orderByDesc('id')->paginate(20, ['*'], 'presencePage');

        return view('livewire.availability.presence-history', compact('sessions', 'summary'));
    }
}
